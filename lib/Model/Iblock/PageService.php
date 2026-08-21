<?php

namespace Uplab\Api\Model\Iblock;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CIBlockRights;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Model\Content\ContentTable;
use Uplab\Api\Utils\Config\Iblock\IblockDetailElementConfig;

class PageService extends IblockService
{
    public const IBLOCK_CODE = 'pages';

    /**
     * @param string $code
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getPageAction(string $code, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock'])
            ]);
        } else {

            $iblock = $this->getIblockByCode(static::IBLOCK_CODE, $iblockConfig->getSiteId());

            if (!$iblock) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData(['message' => Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND')]);
            } else {

                $this->iblock = $iblock;

                $data = $this->getItemData($code, $iblockConfig);

                if (!$data) {
                    $result->setStatus(HttpStatus::NOT_FOUND->value);
                    $result->setData(['message' => Loc::getMessage('UPLAB_API.ELEMENT_NOT_FOUND')]);
                } else {
                    $result->setData($data);
                }
            }
        }

        return $result;
    }

    /**
     * @param string $code
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getItemData(string $code, mixed $iblockConfig): array
    {
        $data = [];

        // Нужно для convertFieldsDataForFront/convertPropertiesDataForFront (HIDE_DISPLAY_VALUES,
        // SHOW_PREFIX_FIELD_DATA, GET_PROPERTIES_PROPERTY).
        $this->config = $iblockConfig;

        //Default  filter
        $arFilter = [
            'ACTIVE'            => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'IBLOCK_CODE'       => static::IBLOCK_CODE,
            '=CODE'             => $code,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
        ];


        if ($siteId = $iblockConfig->getSiteId()) {
            $arFilter['IBLOCK_LID'] = $siteId;
        }

        //Request filter — базовые (защитные) ключи имеют приоритет, а управляющие ключи
        // фильтра (LOGIC, PERMISSIONS_BY, SHOW_HISTORY и др.) из запроса отбрасываются
        if ($filter = $iblockConfig->getFilter()) {
            $arFilter = IblockHelper::mergeProtectedFilter($filter, $arFilter);
        }

        //Select
        $arSelect = $this->getArSelectForDetail($iblockConfig);
        $arSelect[] = 'PREVIEW_TEXT';
        $arSelect[] = 'PREVIEW_TEXT_TYPE';

        $propertyCode = $this->getPropertiesCode($iblockConfig);

        $activeDateFormat = $this->getActiveDateFormat($iblockConfig);
        $activeToDateFormat = $iblockConfig->getActiveToDateFormat();
        $isHideDisplayValues = $iblockConfig->isHideDisplayValues();
        $isShowPrefixFieldData = $iblockConfig->isShowPrefixFieldData();
        $getPropertiesProperty = $iblockConfig->getPropertiesProperty();

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir(self::IBLOCK_CODE);

        if ($cache->initCache(
            static::$cacheTime,
            __FUNCTION__ . md5(
                serialize([
                    $this->getAccessCacheId((int)($this->iblock['ID'] ?? 0)),
                    $propertyCode,
                    $arFilter,
                    $arSelect,
                    $activeDateFormat,
                    $activeToDateFormat,
                    $isHideDisplayValues,
                    $isShowPrefixFieldData,
                    $getPropertiesProperty
                ])
            )
            ,
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);

            $res = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
            if ($obj = $res->GetNextElement(true, false)) {
                $data = $obj->GetFields();
                $this->convertFieldsDataForFront($data);

                $iblockId = $data['IBLOCK_ID'];
                $cacheManager->registerTag('iblock_id_' . $iblockId);

                //get property for view
                if (!$propertyCode && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures()) {
                    if ($list = \Bitrix\Iblock\Model\PropertyFeature::getDetailPageShowPropertyCodes(
                        $iblockId,
                        ['CODE' => 'Y']
                    )) {
                        $propertyCode = $list;
                    }
                }

                if ($propertyCode) {
                    $data['PROPERTIES'] = [];
                    $getAll = is_array($propertyCode) && $propertyCode[0] === '*';
                    if ($getAll) {
                        $data['PROPERTIES'] = $obj->GetProperties();
                    } else {
                        $prop = $obj->GetProperties();
                        foreach ($propertyCode as $propCode) {
                            if (isset($prop[$propCode])) {
                                $data['PROPERTIES'][$propCode] = $prop[$propCode];
                            }
                        }
                    }
                    $this->convertPropertiesDataForFront($data['PROPERTIES']);
                }
            } else {
                $cache->abortDataCache();
                $cacheManager->abortTagCache();
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        if (!empty($data['CODE'])) {
            $data['CONTENT'] = ContentTable::getPageData($data['CODE'], $iblockConfig->getSiteId());
        }

        return $data;
    }

    /**
     * Возвращает список статических страниц
     */
    public function getStaticPages(): array
    {
        $data = [];

        if ($iblock = $this->getIblockByCode($this::IBLOCK_CODE)) {
            $cache = Cache::createInstance();

            $iblockId = (int)$iblock['ID'];

            $cacheDir = $this->getCacheDir($iblockId);

            // Выборка идёт без CHECK_PERMISSIONS (ядро само права не проверяет),
            // поэтому от групп пользователя результат не зависит — кеш общий.
            if ($cache->initCache(static::$cacheTime, __FUNCTION__, $cacheDir)) {
                $data = (array)$cache->getVars();
            } elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {
                $cacheManager = Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);
                $cacheManager->registerTag('iblock_id_' . $iblockId);

                $pagesDb = \CIBlockElement::GetList(
                    ['SORT' => 'ASC', 'NAME' => 'ASC'],
                    [
                        'ACTIVE'               => 'Y',
                        'IBLOCK_ID'            => $iblockId,
                        '!PROPERTY_LINK_VALUE' => false
                    ],
                    false,
                    false,
                    ['ID', 'IBLOCK_ID', 'PROPERTY_LINK']
                );

                while ($pageElement = $pagesDb->Fetch()) {
                    if ($pageElement['PROPERTY_LINK_VALUE']) {
                        $p = ['LINK' => $pageElement['PROPERTY_LINK_VALUE']];

                        $data[$pageElement['ID']] = $p;
                    }
                }

                $cacheManager->endTagCache();
                $cache->endDataCache($data);
            }
        }

        return $data;
    }
}
