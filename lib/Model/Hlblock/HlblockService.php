<?php

namespace Uplab\Api\Model\Hlblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Highloadblock\HighloadBlockRightsTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Application;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Entity;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CIBlockRights;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Helper;
use Uplab\Api\Security\CacheContext;
use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\Hlblock\HlblockListConfig;

class HlblockService
{
    /**
     * Время жизни кеша по умолчанию (24 часа)
     */
    protected static int $cacheTime = 86400;

    public static function getHlBlockTag(string|int $hlblockId)
    {
        return 'hlblock_id_' . $hlblockId;
    }

    private const TAG_HLBLOCK_TABLE = 'hlblock_table_';
    private const CACHE_BASE_PATH = '/hlblock/';

    /**
     * Штатная операция «Чтение элементов» HL-блока
     */
    protected const OPERATION_ELEMENT_READ = 'hl_element_read';

    /**
     * Получает сущность highload-блока по названию таблицы в БД
     *
     * @param string $tableName Название таблицы в БД highload-блока
     * @return Entity\Base|null Объект сущности или null, если не найден
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getEntityByTableName(string $tableName): ?Entity\Base
    {
        // Подключаем необходимые модули
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        // Ищем highload-блок по названию таблицы
        $hlBlock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
            'limit'  => 1
        ])->fetch();

        if (!$hlBlock) {
            return null;
        }

        // Получаем сущность highload-блока
        return HighloadBlockTable::compileEntity($hlBlock);
    }

    /**
     * Возвращает информацию о HlBlock
     * @param string $hlblockName
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getHlblockByName(string $hlblockName): array
    {
        if (!$hlblockName) {
            return [];
        }

        BaseConfig::assertCacheSegment($hlblockName, 'highload-block code');

        $cache = Cache::createInstance();
        $cacheId = 'info';
        $cachePath = self::CACHE_BASE_PATH . $hlblockName;

        $data = [];

        if ($cache->initCache(self::$cacheTime, $cacheId, $cachePath)) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache() && Loader::includeModule('highloadblock')) {
            $cacheManager = Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cachePath);

            $hlBlock = HighloadBlockTable::getList([
                'select' => ['*'],
                'filter' => ['NAME' => $hlblockName],
                'limit'  => 1
            ])->fetch();

            if ($hlBlock) {
                $data = $hlBlock;
                $cacheManager->registerTag(static::getHlBlockTag($hlBlock['ID']));
            } else {
                $cache->abortDataCache();
                $cacheManager->abortTagCache();
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($hlBlock);
        }

        return $data;
    }

    /**
     * Возвращает элементы HlBlock.
     * @param string $hlblockCode
     * @param \Uplab\Api\Utils\Config\Hlblock\HlblockListConfig|mixed $hlblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws \Throwable
     */
    public function getElementListAction(string $hlblockCode, mixed $hlblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('highloadblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'highloadblock'])
            ]);
        } else {
            $hlblock = static::getHlblockByName($hlblockCode);

            if (!$hlblock) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData(['message' => Loc::getMessage('UPLAB_API.HIGHLOADBLOCK_NOT_FOUND')]);
            } elseif (!$this->canReadHlblock((int)$hlblock['ID'])) {
                return $this->setAccessDeniedResponse($result);
            } else {
                $select = ['*'];
                $order = $hlblockConfig->getOrder() ?? [
                    'ID' => 'ASC'
                ];
                $filter = $hlblockConfig->getFilter() ?? [];
                $limit = $hlblockConfig->getLimit() ?? 10;
                $offset = $hlblockConfig->getOffset() ?? 0;

                $cache = Cache::createInstance();
                $cachePath = self::CACHE_BASE_PATH . $hlblockCode;
                $cacheId = self::buildCacheId('hl_list_', [
                    'table'  => $hlblockCode,
                    'select' => $select,
                    'order'  => $order,
                    'filter' => $filter,
                    'limit'  => $limit,
                    'offset' => $offset,
                    'userGroups' => CacheContext::getUserGroupsCacheId(),
                ]);

                $data = [];

                if ($cache->initCache(self::$cacheTime, $cacheId, $cachePath)) {
                    $data = (array)$cache->getVars();
                } elseif ($cache->startDataCache()) {
                    try {
                        $entity = HighloadBlockTable::compileEntity($hlblock['ID'])?->getDataClass();

                        $cacheManager = Application::getInstance()->getTaggedCache();
                        $cacheManager->startTagCache($cachePath);

                        $rsData = $entity::getList([
                            'select'      => $select,
                            'order'       => $order,
                            'filter'      => $filter,
                            'limit'       => $limit,
                            'offset'      => $offset,
                            'count_total' => $hlblockConfig->isShowTotal()
                        ]);

                        while ($arData = $rsData->Fetch()) {
                            $data['items'][] = $arData;
                        }

                        if ($hlblockConfig->isShowTotal()) {
                            $data['meta']['total'] = $rsData->getCount();
                        }
                        $data['meta']['limit'] = $limit;
                        $data['meta']['offset'] = $offset;

                        if (!empty($data['items'])) {
                            $hlblockFields = self::getHlblockFields($hlblock['ID']);
                            if (!empty($hlblockFields)) {
                                self::convertFieldsDataForFront($hlblockFields, $data['items']);
                            }
                        }

                        $cacheManager->registerTag(
                            static::getHlBlockTag($hlblock['ID'])
                        );
                        $cacheManager->endTagCache();
                    } catch (\Throwable $e) {
                        $cache->abortDataCache();
                        if (isset($cacheManager)) {
                            $cacheManager->abortTagCache();
                        }
                        throw $e;
                    }

                    $cache->endDataCache($data);
                }

                $result->setData($data);
            }
        }

        return $result;
    }

    /**
     * Возвращает все пользовательские поля HL-блока в виде массива FIELD_NAME => мета-данные.
     *
     * @param int $hlBlockId - id hlBlock
     * @param string|null $languageId ID языка. Внимание! При неуказании в фильтре ключа LANG со значением необходимого языка ('LANG' => 'ru'), поля "Подпись в форме редактирования", "Заголовок в списке" и т.д. не будут участвовать в выборке. ([EDIT_FORM_LABEL], [LIST_COLUMN_LABEL], [LIST_FILTER_LABEL], [ERROR_MESSAGE], [HELP_MESSAGE])
     * @return array|null
     */
    private static function getHlblockFields(int $hlBlockId, ?string $languageId = null): ?array
    {
        static $memo = [];

        $memoKey = $hlBlockId . '|' . ($languageId ?? '');
        if (array_key_exists($memoKey, $memo)) {
            return $memo[$memoKey];
        }

        $result = [];

        $filter = [
            'ENTITY_ID' => 'HLBLOCK_' . $hlBlockId
        ];
        if ($languageId) {
            $filter['LANG'] = $languageId;
        }

        $arUserTypes = \CUserTypeEntity::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], $filter);

        if ($arUserTypes) {
            while ($arUserType = $arUserTypes->Fetch()) {
                $result[$arUserType['FIELD_NAME']] = $arUserType;
            }
        }

        return $memo[$memoKey] = $result;
    }

    /**
     * Конвертирует значения ссылочных пользовательских полей (на элементы ИБ/HL) в читаемые значения (имена)
     * для фронтенда, модифицируя массив данных по ссылке.
     */
    private static function convertFieldsDataForFront(array $fields, array &$data): void
    {
        $iblockCache = [];
        $hlblockCache = [];

        if ($beforeEvents = GetModuleEvents('uplab.api', 'OnBeforeHlblockConvertFieldValue', true)) {
            foreach ($data as &$dataItem) {
                foreach ($beforeEvents as $arEvent) {
                    ExecuteModuleEventEx($arEvent, [$fields, &$dataItem]);
                }
            }
            unset($dataItem);
        }

        foreach ($fields as $field) {
            switch ($field['USER_TYPE_ID']) {
                case 'iblock_element':
                    // Привязка к элементу ИБ
                    if (empty($field['SETTINGS']['IBLOCK_ID'])) {
                        break;
                    }

                    // Проверяем модуль iblock перед использованием API
                    if (!Loader::includeModule('iblock')) {
                        break;
                    }

                    $iblockId = (int)$field['SETTINGS']['IBLOCK_ID'];
                    if (!isset($iblockCache[$iblockId])) {
                        $iblockData = [];

                        $res = \CIBlockElement::GetList(
                            [
                                'SORT' => 'ASC'
                            ],
                            [
                                'IBLOCK_ID'         => $iblockId,
                                'ACTIVE'            => 'Y',
                                'CHECK_PERMISSIONS' => 'Y',
                                'IBLOCK_ACTIVE'     => 'Y',
                                'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
                            ],
                            false,
                            false,
                            [
                                'ID',
                                'NAME',
                                'CODE',
                                'IBLOCK_ID'
                            ],
                        );

                        while ($el = $res->Fetch()) {
                            $iblockData[$el['ID']] = $el;
                        }

                        $iblockCache[$iblockId] = $iblockData;
                    } else {
                        $iblockData = $iblockCache[$iblockId];
                    }

                    if (empty($iblockData)) {
                        break;
                    }

                    foreach ($data as &$dataItem) {
                        $fieldValue = $dataItem[$field['FIELD_NAME']] ?? null;
                        if (empty($fieldValue)) {
                            continue;
                        }

                        // Множественное поле — массив ID
                        if (is_array($fieldValue)) {
                            $linked = [];
                            foreach ($fieldValue as $linkedId) {
                                if (!empty($iblockData[$linkedId])) {
                                    $linked[] = $iblockData[$linkedId];
                                }
                            }
                            if (!empty($linked)) {
                                $dataItem['INFO'][$field['FIELD_NAME']] = $linked;
                            }
                        } elseif (!empty($iblockData[$fieldValue])) {
                            $dataItem['INFO'][$field['FIELD_NAME']] = $iblockData[$fieldValue];
                        }
                    }
                    unset($dataItem);

                    break;
                case 'hlblock':
                    // Привязка к элементу ХЛБ
                    if (empty($field['SETTINGS']['HLBLOCK_ID'])) {
                        break;
                    }

                    $hlblockId = (int)$field['SETTINGS']['HLBLOCK_ID'];
                    if (!isset($hlblockCache[$hlblockId])) {
                        $hlblockData = [];

                        $hlblock = HighloadBlockTable::getById($hlblockId)->fetch();
                        if ($hlblock) {
                            $entity = HighloadBlockTable::compileEntity($hlblock);
                            $entity_data_class = $entity->getDataClass();

                            $rsData = $entity_data_class::getList([
                                'select' => ['*'],
                                'order'  => ['ID' => 'ASC'],
                                'filter' => []
                            ]);

                            while ($arData = $rsData->Fetch()) {
                                $hlblockData[$arData['ID']] = $arData;
                            }
                        }

                        $hlblockCache[$hlblockId] = $hlblockData;
                    } else {
                        $hlblockData = $hlblockCache[$hlblockId];
                    }

                    if (empty($hlblockData)) {
                        break;
                    }

                    foreach ($data as &$dataItem) {
                        $fieldValue = $dataItem[$field['FIELD_NAME']] ?? null;
                        if (empty($fieldValue)) {
                            continue;
                        }

                        // Множественное поле — массив ID
                        if (is_array($fieldValue)) {
                            $linked = [];
                            foreach ($fieldValue as $linkedId) {
                                if (!empty($hlblockData[$linkedId])) {
                                    $linked[] = $hlblockData[$linkedId];
                                }
                            }
                            if (!empty($linked)) {
                                $dataItem['INFO'][$field['FIELD_NAME']] = $linked;
                            }
                        } elseif (!empty($hlblockData[$fieldValue])) {
                            $dataItem['INFO'][$field['FIELD_NAME']] = $hlblockData[$fieldValue];
                        }
                    }
                    unset($dataItem);

                    break;
                case 'file':
                    foreach ($data as &$dataItem) {
                        if (empty($dataItem[$field['FIELD_NAME']])) {
                            continue;
                        }

                        $fileValue = $dataItem[$field['FIELD_NAME']];

                        // Множественное поле — массив ID файлов
                        if (is_array($fileValue)) {
                            $files = [];
                            foreach ($fileValue as $fileId) {
                                if ($fileId && ($file = \CFile::GetFileArray($fileId))) {
                                    $files[] = $file;
                                }
                            }
                            $dataItem['INFO'][$field['FIELD_NAME']] = $files;
                        } elseif ($file = \CFile::GetFileArray($fileValue)) {
                            $dataItem['INFO'][$field['FIELD_NAME']] = $file;
                        }
                    }
                    unset($dataItem);

                    break;
            }
        }

        if ($afterEvents = GetModuleEvents('uplab.api', 'OnAfterHlblockConvertFieldValue', true)) {
            foreach ($data as &$dataItem) {
                foreach ($afterEvents as $arEvent) {
                    ExecuteModuleEventEx($arEvent, [$fields, &$dataItem]);
                }
            }
            unset($dataItem);
        }
    }

    /**
     * Строит стабильный идентификатор кеша по произвольному набору параметров
     */
    private static function buildCacheId(string $prefix, array $data): string
    {
        // Порядок ключей в 'order' семантически значим (приоритет сортировки),
        // поэтому его не нормализуем; остальное — нормализуем, чтобы ключ не зависел от порядка полей.
        $orderRaw = $data['order'] ?? null;
        unset($data['order']);
        $normalized = self::normalizeForCache($data);
        return $prefix . md5(
            json_encode([$normalized, $orderRaw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private static function normalizeForCache($value)
    {
        if (is_array($value)) {
            ksort($value);
            foreach ($value as $k => $v) {
                $value[$k] = self::normalizeForCache($v);
            }
        }
        return $value;
    }

    /**
     * @param string $hlblockCode
     * @param HlblockListConfig|mixed $hlblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws \Throwable
     */
    public function getFilterElementsAction(string $hlblockCode, mixed $hlblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('highloadblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'highloadblock'])
            ]);
        } else {
            $hlblock = static::getHlblockByName($hlblockCode);

            if (!$hlblock) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData(['message' => Loc::getMessage('UPLAB_API.HIGHLOADBLOCK_NOT_FOUND')]);
            } elseif (!$this->canReadHlblock((int)$hlblock['ID'])) {
                return $this->setAccessDeniedResponse($result);
            } else {

                $hlblockId = (int)$hlblock['ID'];

                $cache = Cache::createInstance();
                $cachePath = self::CACHE_BASE_PATH . $hlblockCode;

                $filter = $hlblockConfig->getFilter() ?? [];

                $cacheId = self::buildCacheId('hl_filters_', [
                    'table' => $hlblockCode,
                    'filter' => $filter,
                    'userGroups' => CacheContext::getUserGroupsCacheId(),
                ]);

                $filterData = [];

                if ($cache->initCache(self::$cacheTime, $cacheId, $cachePath)) {
                    $filterData = (array)$cache->getVars();
                } elseif ($cache->startDataCache()) {
                    try {
                        $entity = HighloadBlockTable::compileEntity($hlblockId)?->getDataClass();

                        // Получаем метаданные пользовательских полей highload-блока
                        $hlblockFields = self::getHlblockFields($hlblockId) ?? [];

                        // Поля, участвующие в поиске
                        $searchableFields = [];
                        foreach ($hlblockFields as $fname => $fmeta) {
                            if (($fmeta['IS_SEARCHABLE'] ?? 'N') === 'Y') {
                                $searchableFields[$fname] = $fmeta;
                            }
                        }

                        // Поддерживаемые типы полей, для которых собираем фильтр
                        $supportedUserTypes = [
                            'iblock_element',
                            'hlblock',
                            'date',
                        ];

                        // Какие поля нужно выбрать из HL
                        $targetFields = [];
                        foreach ($searchableFields as $fname => $fmeta) {
                            if (!empty($fmeta['USER_TYPE_ID']) && in_array($fmeta['USER_TYPE_ID'], $supportedUserTypes, true)) {
                                $targetFields[] = $fname;
                            }
                        }

                        if (!empty($targetFields)) {
                            // Получаем элементы highload-блока
                            if ($entity) {
                                $select = array_values(array_unique(array_merge(['ID'], $targetFields)));
                                $rs = $entity::getList([
                                    'select' => $select,
                                    'filter' => $filter,
                                    'order'  => ['ID' => 'ASC'],
                                ]);

                                // Сбор уникальных значений по интересующим полям
                                $rawValues = [];
                                foreach ($targetFields as $tf) {
                                    $rawValues[$tf] = [];
                                }

                                while ($row = $rs->Fetch()) {
                                    foreach ($targetFields as $tf) {
                                        if (!array_key_exists($tf, $row)) {
                                            continue;
                                        }
                                        $val = $row[$tf];
                                        if (is_array($val)) {
                                            foreach ($val as $v) {
                                                if ($v !== null && $v !== '') {
                                                    $rawValues[$tf][(string)$v] = true;
                                                }
                                            }
                                        } else {
                                            if ($val !== null && $val !== '') {
                                                $rawValues[$tf][(string)$val] = true;
                                            }
                                        }
                                    }
                                }

                                // Сопоставляем и формируем данные фильтра
                                foreach ($searchableFields as $fieldName => $fieldMeta) {
                                    $userType = $fieldMeta['USER_TYPE_ID'] ?? '';
                                    if (!in_array($userType, $supportedUserTypes, true)) {
                                        continue;
                                    }

                                    $values = $rawValues[$fieldName] ?? [];
                                    if (empty($values)) {
                                        continue;
                                    }

                                    switch ($userType) {
                                        case 'iblock_element':
                                            $iblockId = (int)($fieldMeta['SETTINGS']['IBLOCK_ID'] ?? 0);
                                            if ($iblockId <= 0) {
                                                break;
                                            }
                                            $items = [];
                                            if (Loader::includeModule('iblock')) {
                                                $ids = array_map('intval', array_keys($values));
                                                if (!empty($ids)) {
                                                    $res = \CIBlockElement::GetList(
                                                        ['SORT' => 'ASC', 'NAME' => 'ASC'],
                                                        [
                                                            'IBLOCK_ID' => $iblockId,
                                                            'ACTIVE'    => 'Y',
                                                            'ID'        => $ids,
                                                        ],
                                                        false,
                                                        false,
                                                        ['ID', 'NAME']
                                                    );
                                                    while ($el = $res->Fetch()) {
                                                        $items[] = [
                                                            'text'  => $el['NAME'],
                                                            'value' => (int)$el['ID'],
                                                        ];
                                                    }
                                                }
                                            }
                                            if (!empty($items)) {
                                                $filterData[$fieldName] = $items;
                                            }
                                            break;

                                        case 'hlblock':
                                            $linkedHlId = (int)($fieldMeta['SETTINGS']['HLBLOCK_ID'] ?? 0);
                                            if ($linkedHlId <= 0) {
                                                break;
                                            }
                                            $items = [];
                                            $hlblock = HighloadBlockTable::getById($linkedHlId)->fetch();
                                            if ($hlblock) {
                                                $entityLinked = HighloadBlockTable::compileEntity($hlblock);
                                                $entityClass = $entityLinked->getDataClass();
                                                $ids = array_map('intval', array_keys($values));
                                                if (!empty($ids)) {
                                                    $rsLinked = $entityClass::getList([
                                                        'select' => ['ID', 'UF_NAME'],
                                                        'filter' => ['ID' => $ids],
                                                        'order'  => ['UF_NAME' => 'ASC'],
                                                    ]);
                                                    while ($rowL = $rsLinked->Fetch()) {
                                                        $items[] = [
                                                            'text'  => $rowL['UF_NAME'],
                                                            'value' => (int)$rowL['ID'],
                                                        ];
                                                    }
                                                }
                                            }
                                            if (!empty($items)) {
                                                $filterData[$fieldName] = $items;
                                            }
                                            break;

                                        case 'date':
                                            $years = [];
                                            foreach (array_keys($values) as $val) {
                                                if (!Helper::isValidDate($val)) {
                                                    continue;
                                                }
                                                $year = date('Y', strtotime($val));
                                                if (!empty($year)) {
                                                    $years[$year] = true;
                                                }
                                            }
                                            if (!empty($years)) {
                                                krsort($years);
                                                foreach (array_keys($years) as $y) {
                                                    $filterData[$fieldName][] = [
                                                        'text'  => $y,
                                                        'value' => $y,
                                                    ];
                                                }
                                            }
                                            break;

                                        default:
                                            break;
                                    }
                                }
                            }
                        }

                        // Тегированное кеширование
                        $cacheManager = Application::getInstance()->getTaggedCache();
                        $cacheManager->startTagCache($cachePath);

                        $cacheManager->registerTag(static::getHlBlockTag($hlblockId));

                        $cacheManager->registerTag(self::TAG_HLBLOCK_TABLE . $hlblockCode);
                        $cacheManager->endTagCache();

                        $cache->endDataCache($filterData);
                    } catch (\Throwable $e) {
                        $cache->abortDataCache();
                        throw $e;
                    }
                }

                $result->setData($filterData);
            }
        }

        return $result;
    }

    /**
     *
     * @param string $hlblockCode
     * @param BaseConfig|mixed $createDefault
     * @return Json
     * @throws ArgumentTypeException
     * @throws LoaderException
     */
    public function getFiedlsAction(string $hlblockCode, mixed $createDefault)
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('highloadblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'highloadblock'])
            ]);
        } else {

            $hlblock = static::getHlblockByName($hlblockCode);

            if (!$hlblock) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData(['message' => Loc::getMessage('UPLAB_API.HIGHLOADBLOCK_NOT_FOUND')]);
            } elseif (!$this->canReadHlblock((int)$hlblock['ID'])) {
                return $this->setAccessDeniedResponse($result);
            } else {

                $data = static::getHlblockFields($hlblock['ID'], $createDefault->getLanguageId());

                $result->setData($data);
            }
        }

        return $result;
    }

    /**
     * Алиас с корректным написанием имени.
     * Основной метод — {@see getFiedlsAction()} (историческая опечатка): его зовёт контроллер,
     * поэтому переопределения в проектах-наследниках продолжают работать.
     */
    public function getFieldsAction(string $hlblockCode, mixed $createDefault)
    {
        return $this->getFiedlsAction($hlblockCode, $createDefault);
    }

    /**
     * Право на чтение HL-блока.
     *
     * HL-блок публичен, пока никому не выдана операция `hl_element_read`: записей прав
     * «по умолчанию» ядро не создаёт, поэтому пустая вкладка «Права доступа» означает
     * «ограничение чтения не настроено», а не «доступ запрещён всем». Как только право
     * чтения кому-то выдано, блок читают только обладатели права и администратор.
     *
     * Штатный {@see HighloadBlockRightsTable::getOperationsName()} здесь непригоден как
     * единственная проверка: он резолвит права через `b_user_access`, куда
     * {@see \CAccess::UpdateCodes()} для неавторизованного пользователя не пишет ничего —
     * аноним не получил бы права ни при какой настройке. Поэтому коды доступа сверяются
     * напрямую, а штатный вызов остаётся фолбэком для кодов, которые без `b_user_access`
     * не раскрыть (отделы, соцгруппы) — он имеет смысл только для авторизованных.
     */
    protected function canReadHlblock(int $hlblockId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Пустой список — ограничение чтения на блоке не настроено
        $readAccessCodes = $this->fetchReadAccessCodes($hlblockId);
        if (!$readAccessCodes) {
            return true;
        }

        if (array_intersect($readAccessCodes, $this->getCurrentUserAccessCodes())) {
            return true;
        }

        return $this->isAuthorized()
            && in_array('hl_element_read', $this->fetchUserOperations($hlblockId), true);
    }

    /**
     * Коды доступа текущего пользователя в терминах прав Битрикс: группы (`G<ID>`),
     * все авторизованные (`AU`) и сам пользователь (`U<ID>`). Для анонима это `G2` —
     * группа «Все пользователи (в том числе неавторизованные)».
     *
     * @return string[]
     */
    protected function getCurrentUserAccessCodes(): array
    {
        $codes = [];
        foreach (CacheContext::getUserGroups() as $groupId) {
            $codes[] = 'G' . $groupId;
        }

        if ($this->isAuthorized()) {
            $codes[] = 'AU';
            $codes[] = 'U' . $this->getUserId();
        }

        return $codes;
    }

    private function setAccessDeniedResponse(Json $result): Json
    {
        $result->setStatus(HttpStatus::FORBIDDEN->value);
        $result->setData(['message' => Loc::getMessage('UPLAB_API.ACCESS_DENIED')]);

        return $result;
    }

    //<editor-fold desc="Обращения к API Битрикс">
    /**
     * @return string[]
     */
    protected function fetchReadAccessCodes(int $hlblockId): array
    {
        $res = HighloadBlockRightsTable::getList([
            'select' => ['ACCESS_CODE'],
            'filter' => [
                'HL_ID'                          => $hlblockId,
                '=TASK_OPERATION.OPERATION.NAME' => self::OPERATION_ELEMENT_READ,
            ],
        ]);

        $codes = [];
        while ($row = $res->fetch()) {
            $codes[] = (string)$row['ACCESS_CODE'];
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return string[]
     */
    protected function fetchUserOperations(int $hlblockId): array
    {
        $operationsById = HighloadBlockRightsTable::getOperationsName([$hlblockId]);

        return (array)($operationsById[$hlblockId] ?? []);
    }

    protected function isAdmin(): bool
    {
        return CurrentUser::get()->isAdmin();
    }

    protected function isAuthorized(): bool
    {
        return $this->getUserId() > 0;
    }

    protected function getUserId(): int
    {
        return (int)CurrentUser::get()->getId();
    }
    //</editor-fold>
}
