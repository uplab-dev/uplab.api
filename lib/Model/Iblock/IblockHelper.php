<?php

namespace Uplab\Api\Model\Iblock;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Uplab\Api\Main;
use Uplab\Api\Security\CacheContext;

class IblockHelper
{
	/**
	 * Ключи фильтра, которые управляют самой выборкой, а не добавляют условие:
	 * на верхнем уровне фильтра они переопределяют защитные условия модуля.
	 *
	 * - LOGIC=OR — превращает все условия верхнего уровня (ACTIVE, IBLOCK_ID,
	 *   IBLOCK_LID, IBLOCK_ACTIVE) в альтернативы, снимая привязку к инфоблоку,
	 *   сайту и активности;
	 * - PERMISSIONS_BY — заставляет ядро проверять права от имени другого пользователя;
	 * - CHECK_PERMISSIONS / MIN_PERMISSION — уровень проверки прав;
	 * - SHOW_HISTORY / SHOW_NEW / SHOW_BP_NEW / CHECK_BP_PERMISSIONS — выдача
	 *   черновиков и записей истории документооборота.
	 *
	 * Во вложенных группах фильтра эти ключи безопасны: группа присоединяется к
	 * запросу через AND и может только сузить выборку, поэтому чистится верхний уровень.
	 */
	public const FILTER_CONTROL_KEYS = [
		'LOGIC',
		'CHECK_PERMISSIONS',
		'MIN_PERMISSION',
		'PERMISSIONS_BY',
		'SHOW_HISTORY',
		'SHOW_NEW',
		'SHOW_BP_NEW',
		'CHECK_BP_PERMISSIONS',
	];

	/**
	 * Сегмент доступа для ключа кеша выборок инфоблока.
	 *
	 * В простом режиме прав (`RIGHTS_MODE = S`) видимость элементов и разделов — функция
	 * набора групп пользователя: ядро строит условие прав по `b_iblock_group`. В расширенном
	 * режиме (`RIGHTS_MODE = E`) права даются ещё и персонально (`U<ID>`), по отделу и на
	 * «создателя» (`CR`) через `b_user_access`, поэтому двум пользователям с одинаковыми
	 * группами доступны разные наборы элементов — кеш дополнительно делится по пользователю.
	 *
	 * @param int|null $iblockId ID инфоблока; `null` или 0 — режим неизвестен, берётся
	 *                          более строгий сегмент (по пользователю).
	 * @return string
	 */
	public static function getAccessCacheId(?int $iblockId = null): string
	{
		if (!$iblockId || static::isExtendedRightsMode($iblockId)) {
			return CacheContext::getUserCacheId();
		}

		return CacheContext::getUserGroupsCacheId();
	}

	public static function isExtendedRightsMode(int $iblockId): bool
	{
		return static::fetchIblockRightsMode($iblockId) === IblockTable::RIGHTS_EXTENDED;
	}

	/**
	 * Обращение к API Битрикс: режим прав инфоблока (значение кешируется ядром).
	 */
	protected static function fetchIblockRightsMode(int $iblockId): string
	{
		return (string)\CIBlock::GetArrayByID($iblockId, 'RIGHTS_MODE');
	}

	/**
	 * Добавляет к базовому (защитному) фильтру условия из запроса.
	 *
	 * Базовые ключи проставляются последними и переопределяют одноимённые ключи
	 * запроса, а управляющие ключи ({@see FILTER_CONTROL_KEYS}) из фильтра запроса
	 * удаляются: их нельзя нейтрализовать порядком слияния, потому что они не
	 * конкурируют с базовыми ключами, а меняют способ сборки запроса.
	 *
	 * @param array $requestFilter Фильтр из тела запроса
	 * @param array $baseFilter Базовый фильтр модуля
	 * @return array
	 */
	public static function mergeProtectedFilter(array $requestFilter, array $baseFilter): array
	{
		return array_merge(static::stripFilterControlKeys($requestFilter), $baseFilter);
	}

	/**
	 * Убирает управляющие ключи с верхнего уровня фильтра. Сравнение не зависит от
	 * префикса-оператора Битрикс (<code>!</code>, <code>=</code>, <code>&gt;=</code> и т.п.).
	 *
	 * @param array $filter
	 * @return array
	 */
	public static function stripFilterControlKeys(array $filter): array
	{
		$result = [];
		foreach ($filter as $key => $value) {
			if (is_string($key)
				&& in_array(strtoupper(ltrim($key, '!<>=%?~+*')), static::FILTER_CONTROL_KEYS, true)) {
				continue;
			}
			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * Возвращает DETAIL_PAGE_URL для ORM элемента инфоблока.
	 * Обязательно нужно выбирать поля:
	 * 'ID',
	 * 'CODE',
	 * 'IBLOCK.DETAIL_PAGE_URL',
	 * 'IBLOCK_SECTION_ID',
	 * 'IBLOCK_ID'
	 * @param $element
	 * @param string $langDir
	 * @return string
	 */
	public static function getDetailUrl($element, string $langDir = '/'): string
	{
		return (string)\CIBlock::ReplaceDetailUrl(
			$element->getIblock()?->getDetailPageUrl(),
			[
				'LANG_DIR'          => $langDir,
				'IBLOCK_ID'         => $element->getIblockId(),
				'IBLOCK_SECTION_ID' => $element->getIblockSection()?->getId() ?? 0,
				'CODE'              => $element->getCode(),
				'ID'                => $element->getId()
			],
			false,
			'E'
		);
	}

	/**
	 * Возвращает id инфоблока.
	 *
	 * @param string $code - символьный код инфоблока
	 * @param string $LID - привязка к сайту
	 * @param string $type - тип инфоблока
	 * @return int
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public static function getIblockId(string $code, string $LID = Main::DEFAULT_LANG, string $type = ''): int
	{
		$iblockId = 0;

		$cache = Cache::createInstance();
		$cacheDir = '/' . $LID . '/iblock';

		if ($cache->initCache(36000, md5($code . $type . $LID), $cacheDir)) {
			$iblockId = (int)$cache->getVars();
		} elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {

			$query = IblockTable::query()
				->addSelect('ID')
				->where('ACTIVE', true)
				->where('CODE', $code);

			if ($type) {
				$query->where('IBLOCK_TYPE_ID', $type);
			}

			if ($LID) {
				$query->where('LID', $LID);
			}

			if ($iblock = $query->exec()->fetch()) {
				$iblockId = (int)$iblock['ID'];

				$cacheManager = Application::getInstance()->getTaggedCache();
				$cacheManager->startTagCache($cacheDir);
				$cacheManager->registerTag('iblock_id_' . $iblockId);
				$cacheManager->endTagCache();
			} else {
				$cache->abortDataCache();
			}

			$cache->endDataCache($iblockId);
		}

		return $iblockId;
	}

	/**
	 * Вернуть тип инфоблока.
	 *
	 * @param string $code
	 * @param string $LID
	 * @param string $type
	 * @return string
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public static function getIblockType(string $code, string $LID = Main::DEFAULT_LANG, string $type = ''): string
	{
		$iblockType = '';

		$cache = Cache::createInstance();
		$cacheDir = '/' . SITE_ID . '/iblock';
		if ($cache->initCache(36000, 'gettype' . md5($code . $LID . $type), $cacheDir)) {
			$iblockType = (string)$cache->getVars();
		} elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {

			$query = IblockTable::query()
				->addSelect('IBLOCK_TYPE_ID')
				->addSelect('LID')
				->addSelect('ID')
				->where('ACTIVE', true)
				->where('CODE', $code);
			if ($LID) {
				$query->where('LID', $LID);
			}
			if ($type) {
				$query->where('IBLOCK_TYPE_ID', $type);
			}
			if ($iblock = $query->exec()->fetch()) {
				$iblockType = (string)$iblock['IBLOCK_TYPE_ID'];

				$cacheManager = Application::getInstance()->getTaggedCache();
				$cacheManager->startTagCache($cacheDir);
				$cacheManager->registerTag('iblock_id_' . $iblock['ID']);
				$cacheManager->endTagCache();
			} else {
				$cache->abortDataCache();
			}

			$cache->endDataCache($iblockType);
		}

		return $iblockType;
	}

	/**
	 * Возвращает ссылку на картинку инфоблока.
	 * @param int $iblockId
	 * @return string
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public static function getIblockImage(int $iblockId): string
	{
		$src = '';

		$cache = Cache::createInstance();
		$cacheDir = '/iblock/' . $iblockId;

		if ($cache->initCache(36000, 'imagesrc', $cacheDir)) {
			$src = (string)$cache->getVars();
		} elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {

			$query = IblockTable::query()
				->addSelect('PICTURE')
				->where('ACTIVE', true)
				->where('ID', $iblockId);

			if ($iblock = $query->exec()->fetchObject()) {
				if ($imageId = $iblock->getPicture()) {
					$src = \CFile::GetPath($imageId) ?? '';
				}

				$cacheManager = Application::getInstance()->getTaggedCache();
				$cacheManager->startTagCache($cacheDir);
				$cacheManager->registerTag('iblock_id_' . $iblockId);
				$cacheManager->endTagCache();
			} else {
				$cache->abortDataCache();
			}

			$cache->endDataCache($src);
		}

		return $src;
	}

	/**
	 * Возвращает имя инфоблока для вывода на странице поиска.
	 * Выводится DESCRIPTION ?: NAME.
	 * @param int $iblockId - id инфоблока
	 * @return string
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public static function getSearchName(int $iblockId): string
	{
		$s = '';

		$cache = Cache::createInstance();
		$cacheDir = '/iblock/' . $iblockId;

		if ($cache->initCache(36000, 'searchName', $cacheDir)) {
			$s = (string)$cache->getVars();
		} elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {

			$query = IblockTable::query()
				->addSelect('NAME')
				->addSelect('DESCRIPTION')
				->where('ACTIVE', true)
				->where('ID', $iblockId);

			if ($iblock = $query->exec()->fetchObject()) {
				$s = $iblock->getDescription() ?: $iblock->getName();

				$cacheManager = Application::getInstance()->getTaggedCache();
				$cacheManager->startTagCache($cacheDir);
				$cacheManager->registerTag('iblock_id_' . $iblockId);
				$cacheManager->endTagCache();
			} else {
				$cache->abortDataCache();
			}

			$cache->endDataCache($s);
		}

		return $s;
	}

	/**
	 * Возвращает класс инфоблока по id элемента.
	 * @param int $elementId
	 * @param string $LID
	 * @param string $type
	 * @return string
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public static function getIblockDataClassByElementId(int $elementId, string $LID = Main::DEFAULT_LANG): string
	{
		$cache = Cache::createInstance();
		$cacheDir = '/' . $LID . '/iblock_element';

		$dataClass = '';

		if ($cache->initCache(36000, md5($elementId), $cacheDir)) {
			$dataClass = (string)$cache->getVars();
		} elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {

			$query = ElementTable::query()
				->addSelect('IBLOCK_ID')
				->where('ACTIVE', true)
				->where('ID', $elementId);

			if ($element = $query->exec()->fetchObject()) {
				$iblockId = $element->getIblockId();
				$dataClass = \Bitrix\Iblock\Iblock::wakeUp($iblockId)->getEntityDataClass();

				$cacheManager = Application::getInstance()->getTaggedCache();
				$cacheManager->startTagCache($cacheDir);
				$cacheManager->registerTag('iblock_id_' . $iblockId);
				$cacheManager->endTagCache();

			} else {
				$cache->abortDataCache();
			}

            $cache->endDataCache($dataClass);
		}

		return $dataClass;

	}
}