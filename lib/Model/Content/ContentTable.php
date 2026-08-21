<?php

namespace Uplab\Api\Model\Content;

use Bitrix\Main\Entity;
use Bitrix\Main\Loader;
use Uplab\Api\Utils\Config\BaseConfig;

class ContentTable extends Entity\DataManager
{
    public const CACHE_TAG = 'update_contenttable';

    /**
     * Возвращает название HlBlock.
     * @return string
     */
    public static function getHLBlockName(): string
    {
        return 'Content';
    }

    /**
     * Возвращает название таблицы HLBlock в базе данных.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_hlbd_content';
    }

    /**
     * Описание полей таблицы:
     * - ID          — первичный ключ, автоинкремент
     * - UF_ACTIVE   — флаг активности записи (по умолчанию true)
     * - UF_PAGE     — символьный код страницы (обязательное)
     * - UF_BLOCK    — символьный код блока внутри страницы
     * - UF_CODE     — символьный код переменной (обязательное)
     * - UF_VALUE    — значение переменной (поддерживает плейсхолдер #YEAR#)
     * - UF_DESCRIPTION — описание записи
     * - UF_LANG     — язык записи (обязательное, по умолчанию 'ru')
     * @return array
     */
    public static function getMap(): array
    {
        return [
            (new Entity\IntegerField('ID'))
                ->configurePrimary()
                ->configureAutocomplete(),
            (new Entity\BooleanField('UF_ACTIVE'))
                ->configureDefaultValue(true),
            (new Entity\StringField('UF_PAGE'))
                ->configureRequired(),
            new Entity\StringField('UF_BLOCK'),
            (new Entity\StringField('UF_CODE'))
                ->configureRequired(),
            new Entity\StringField('UF_VALUE'),
            new Entity\StringField('UF_DESCRIPTION'),
            (new Entity\StringField('UF_LANG'))
                ->configureDefaultValue('ru')
                ->configureRequired()
        ];
    }

    /**
     * Получить tag для страницы.
     * @param string $ufPage
     * @return string
     */
    public static function getPageTag(string $ufPage): string
    {
        return 'update_contenttable_' . $ufPage;
    }

    /**
     * Возвращает активные записи контента для указанной страницы.
     *
     * Результат структурирован как: ['UF_BLOCK']['UF_CODE'] => 'UF_VALUE'.
     * Данные кешируются с тегом страницы {@see getPageTag()}.
     *
     * @param string $pageCode Символьный код страницы (UF_PAGE)
     * @param string|null $lang Значение поля UF_LANG для фильтрации и сегментации кеша.
     *                          ВНИМАНИЕ: исторически сюда передаётся SITE_ID (в UF_LANG записей
     *                          хранится ID сайта, а не код языка). Название параметра сохранено
     *                          для обратной совместимости с проектами, наследующими модуль.
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getPageData(string $pageCode, ?string $lang = null): array
    {
        $data = [];

        // Значения попадают в путь кеша, поэтому проверяются здесь, а не только в контроллере:
        // метод публичный и вызывается из проектного кода.
        BaseConfig::assertCacheSegment($pageCode, 'page code');
        if ($lang) {
            BaseConfig::assertCacheSegment($lang, 'siteId');
        }

        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cacheDir = '/content/' . $pageCode . ($lang ? '/' . $lang : '');

        if ($cache->initCache(86400, 'data', $cacheDir)) {
            $data = (array)$cache->getVars();
        } elseif ($cache->startDataCache() && Loader::includeModule('highloadblock')) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag(ContentTable::getPageTag($pageCode));

            if ((
                $obEntity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(static::getHLBlockName())
                )
                &&
                $strEntityDataClass = $obEntity?->getDataClass()
            ) {
                $arFilter = [
                    'UF_PAGE'   => $pageCode,
                    'UF_ACTIVE' => true,
                ];
                // $lang исторически = SITE_ID (см. docblock).
                if ($lang) {
                    $arFilter['UF_LANG'] = $lang;
                }

                $rsData = $strEntityDataClass::getList([
                    'select' => ['*'],
                    'filter' => $arFilter,
                    'order'  => ['ID' => 'ASC'],
                    'limit'  => 99999
                ]);

                while ($item = $rsData->fetch()) {
                    $data[$item['UF_BLOCK']][$item['UF_CODE']] = static::prepareValue($item['UF_VALUE']);
                }
            }

            if (!$data) {
                $cache->abortDataCache();
                $cacheManager->abortTagCache();
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        return $data;
    }


    /**
     * Подготавливает значение к выводу: заменяет плейсхолдер #YEAR# на текущий год.
     * @param string|null $value
     * @return string|null
     */
    protected static function prepareValue(?string $value): ?string
    {
        if ($value != '') {
            $value = str_replace(
                [
                    '#YEAR#'
                ],
                [
                    date('Y')
                ],
                $value
            );
        }
        return $value;
    }
}