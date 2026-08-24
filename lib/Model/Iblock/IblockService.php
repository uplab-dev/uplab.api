<?php

namespace Uplab\Api\Model\Iblock;

use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use CIBlockElement;
use CIBlockRights;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Model\ControllerAction\BaseAction;
use Uplab\Api\Model\Hlblock\HlblockService;
use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\Iblock\IblockDetailConfig;
use Uplab\Api\Utils\Config\Iblock\IblockDetailElementConfig;
use Uplab\Api\Utils\Config\Iblock\IblockDetailSectionConfig;
use Uplab\Api\Utils\Config\Iblock\IblockFilterConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListElementsConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListSectionsConfig;

class IblockService extends BaseAction
{
    protected static int $cacheTime = 86400;
    protected static mixed $nameCache;

    protected array $iblock = [];
    /**
     * @var mixed|IblockListElementsConfig|null
     */
    protected mixed $config = null;

    //<editor-fold desc="Iblock">

    /**
     * Получить массив инфоблока.
     * @param string $iblockCode - символьный код инфоблока
     * @param string|null $siteId - SITE_ID
     * @param string|null $iblockType - TYPE
     * @return array
     * @throws ArgumentException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getIblockByCode(string $iblockCode, ?string $siteId = null, ?string $iblockType = null): array
    {
        BaseConfig::assertCacheSegment($iblockCode, 'iblock code');
        $data = [];
        if (!empty($iblockCode)) {
            $cache = \Bitrix\Main\Data\Cache::createInstance();
            $cacheDir = $this->getCacheDir($iblockCode);
            $cacheId = __FUNCTION__ . md5(serialize([
                $iblockCode,
                $siteId,
                $iblockType,
                // Режим прав инфоблока ещё не известен — берётся строгий сегмент
                $this->getAccessCacheId(),
            ]));
            if ($cache->initCache(static::$cacheTime, $cacheId, $cacheDir)) {
                $data = (array)$cache->getVars();
            } elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {
                $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);

                $arFilter = [
                    'ACTIVE'            => 'Y',
                    'CHECK_PERMISSIONS' => 'Y',
                    'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
                    'CODE'              => $iblockCode
                ];
                if ($siteId) {
                    $arFilter['SITE_ID'] = $siteId;
                }
                if ($iblockType) {
                    $arFilter['TYPE'] = $iblockType;
                }

                if ($iblock = \CIBlock::GetList([], $arFilter)->GetNext()) {
                    $data = $iblock;
                    $cacheManager->registerTag('iblock_id_' . $iblock['ID']);
                } else {
                    $cache->abortDataCache();
                    $cacheManager->abortTagCache();
                }

                $cacheManager->endTagCache();
                $cache->endDataCache($data);
            }
        }
        return $data;
    }


    /**
     * Метод end-point для получения информации об инфоблоке в формате для FRONT.
     * @param string $iblockCode
     * @param IblockDetailConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getIblockAction(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());

        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $result->setData($iblock);

        return $result;
    }
    //</editor-fold>

    //<editor-fold desc="Elements">
    /**
     * Метод end-point для получения списка элементов инфоблока в формате для FRONT
     * @param string $iblockCode
     * @param IblockListElementsConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getElementListAction(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $this->iblock = $iblock;
        $this->config = $iblockConfig;

        $iblockId = (int)$iblock['ID'];

        $data = [];

        //Filter
        $arFilter = $this->getArFilterForList($iblockId, $iblockConfig);
        //Select
        $arSelect = $this->getArSelectForList($iblockConfig);
        //Order
        $arOrder = $this->getArOrderForList($iblockConfig);

        $limit = $iblockConfig->getLimit() ?? 10;
        $offset = $iblockConfig->getOffset() ?? 0;
        $arNavStartParams = [
            'nTopCount' => $limit,
            'nOffset'   => $offset
        ];

        $activeDateFormat = $this->getActiveDateFormat($iblockConfig);
        $activeToDateFormat = $iblockConfig->getActiveToDateFormat();

        $isHideDisplayValues = $iblockConfig->isHideDisplayValues();
        $isShowPrefixFieldData = $iblockConfig->isShowPrefixFieldData();

        $propertyCode = $this->getPropertiesCode($iblockConfig);
        $propertiesPropertyCode = $iblockConfig->getPropertiesProperty();

        $isRandOrder = !empty(array_filter(
            array_keys($arOrder),
            static fn($k) => strtoupper($k) === 'RAND'
        ));

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir($iblockId);
        $effectiveCacheTime = $isRandOrder ? 30 : static::$cacheTime;

        // static::class в ключе — чтобы кеш наследника с изменённым payload не пересекался с базовым.
        $cacheKeyParts = $this->getElementListCacheKeyParts([
            static::class,
            $this->getAccessCacheId($iblockId),
            $propertiesPropertyCode,
            $propertyCode,
            $arFilter,
            $arOrder,
            $arSelect,
            $arNavStartParams,
            $activeToDateFormat,
            $activeDateFormat,
            $isShowPrefixFieldData,
            $isHideDisplayValues,
            $iblockConfig->isShowTotal(),
            $iblockConfig->isShowIpropertyValues()
        ], $iblockConfig);

        if ($cache->initCache(
            $effectiveCacheTime,
            __FUNCTION__ . md5(serialize($cacheKeyParts)),
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag('iblock_id_' . $iblockId);

            \CPageOption::SetOptionString("main", "nav_page_in_session", "N");

            //get property for list view
            if (!$propertyCode && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures()) {
                if ($list = \Bitrix\Iblock\Model\PropertyFeature::getListPageShowPropertyCodes(
                    $iblockId,
                    ['CODE' => 'Y']
                )) {
                    $propertyCode = $list;
                }
            }

            $res = \CIBlockElement::GetList($arOrder, $arFilter, false, $arNavStartParams, $arSelect);

            while ($obj = $res->GetNextElement()) {
                $arItem = $obj->GetFields();
                // Снимок сырых полей до преобразований для фронта — передаётся в хук appendElementListItemData().
                $rawFields = $arItem;

                \Bitrix\Iblock\InheritedProperty\ElementValues::queue($arItem["IBLOCK_ID"], $arItem["ID"]);

                $time = DateTime::createFromUserTime($arItem["TIMESTAMP_X"]);
                if (
                    !isset($data['meta']["timestampX"])
                    || $time->getTimestamp() > $data['meta']["timestampX"]->getTimestamp()
                ) {
                    $data['meta']["timestampX"] = $time;
                }

                $arItem["DISPLAY_ACTIVE_FROM"] = $this->getFormatDate($arItem["ACTIVE_FROM"] ?? '', $activeDateFormat);
                $arItem["DISPLAY_ACTIVE_TO"] = $this->getFormatDate($arItem["ACTIVE_TO"] ?? '', $activeToDateFormat);

                $this->convertFieldsDataForFront($arItem);

                if ($iblockConfig->isShowIpropertyValues() !== true) {
                    unset($arItem["IPROPERTY_VALUES"]);
                }

                // Сырые свойства (до преобразований) — передаются в хук appendElementListItemData().
                $rawProperties = [];

                if ($propertyCode) {
                    $rawProperties = $obj->GetProperties();
                    $arItem['PROPERTIES'] = [];
                    $getAll = is_array($propertyCode) && $propertyCode[0] === '*';

                    if ($getAll) {
                        $arItem['PROPERTIES'] = $rawProperties;
                    } else {
                        foreach ($propertyCode as $propCode) {
                            if (isset($rawProperties[$propCode])) {
                                $arItem['PROPERTIES'][$propCode] = $rawProperties[$propCode];
                            }
                        }
                    }

                    $this->convertPropertiesDataForFront($arItem['PROPERTIES']);
                }

                $data['items'][] = $this->appendElementListItemData($arItem, $rawFields, $rawProperties, $obj, $iblockConfig);
            }

            if ($propertyCode) {
                \CIBlockFormatProperties::clearCache();
            }

            if ($iblockConfig->isShowTotal()) {
                //Доп. запрос на получение общего кол-ва элементов. nOffset видимо ломает и возвращается не корректное число.
                $res = \CIBlockElement::GetList(['ID' => 'ASC'], $arFilter, false, false, ['ID']);
                $data['meta']['total'] = $res->SelectedRowsCount();
            }
            $data['meta']['limit'] = $limit;
            $data['meta']['offset'] = $offset;

            $data = $this->appendElementListData($data, $iblockConfig);

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        $result->setData($data);

        return $result;
    }

    /**
     * Метод end-point для получения фильтра по элементам в формате для FRONT
     * @param string $iblockCode
     * @param IblockFilterConfig|mixed $iblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getFilterElementsAction(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $result->setData(
            $this->getPropertyModel()
                ->setIblockId((int)$iblock['ID'])
                ->setSiteId($iblockConfig->getSiteId())
                ->getFilterProperties($iblockConfig->getFilter())
        );

        return $result;
    }

    /**
     * Модель значений умного фильтра.
     *
     * Переопределите в наследнике, чтобы подставить своего наследника {@see IblockProperty},
     * не переопределяя getFilterElementsAction(). Параметры модели задаются сеттерами
     * (setIblockId(), setSiteId(), setSectionId()), поэтому сигнатура не зависит от того,
     * какие данные модели понадобятся дальше.
     *
     * @return IblockProperty
     */
    protected function getPropertyModel()
    {
        return new IblockProperty();
    }

    /**
     * Метод end-point для получения элемента инфоблока по его символьному коду в формате для FRONT.
     * @param string $iblockCode
     * @param string $elementCode
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getElementDetailByCodeAction(string $iblockCode, string $elementCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        return $this->getElementDetail($iblockCode, null, $elementCode, $iblockConfig);
    }

    /**
     * Метод end-point для получения элемента инфоблока по его id в формате для FRONT.
     * @param string $iblockCode
     * @param int $elementId
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return Json
     */
    public function getElementDetailByIdAction(string $iblockCode, int $elementId, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        return $this->getElementDetail($iblockCode, $elementId, null, $iblockConfig);
    }

    /**
     * Метод end-point для получения раздела инфоблока по его символьному коду в формате для FRONT.
     * @param string $iblockCode
     * @param string $sectionCode
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getSectionDetailByCodeAction(string $iblockCode, string $sectionCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        return $this->getSectionDetail($iblockCode, null, $sectionCode, $iblockConfig);
    }

    /**
     * Метод end-point для получения элемента инфоблока по его id в формате для FRONT.
     * @param string $iblockCode
     * @param int $sectionId
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return Json
     */
    public function getSectionDetailByIdAction(string $iblockCode, int $sectionId, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        return $this->getSectionDetail($iblockCode, $sectionId, null, $iblockConfig);
    }

    /**
     * Получить элемент инфоблока
     * @param string $iblockCode
     * @param int|null $elementId
     * @param string|null $elementCode
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    protected function getElementDetail(string $iblockCode, ?int $elementId = null, ?string $elementCode = null, mixed $iblockConfig = null): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $this->iblock = $iblock;
        $this->config = $iblockConfig;

        $data = $this->getIblockElementData((int)$iblock['ID'], $elementId, $elementCode, $iblockConfig);
        if (!$data) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.ELEMENT_NOT_FOUND'));
        }

        $result->setData($data);

        return $result;
    }

    /**
     * Возвращает данные для вывода элемента инфоблока.
     * @param int $iblockId
     * @param int|null $elementId
     * @param string|null $elementCode
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return array|null
     * @throws ArgumentException
     * @throws LoaderException
     * @throws SystemException
     */
    protected function getIblockElementData(int $iblockId, ?int $elementId = null, ?string $elementCode = null, mixed $iblockConfig = null): ?array
    {
        $data = null;

        //Filter
        $arFilter = [
            'ACTIVE'            => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
        ];

        if ($elementId > 0) {
            $arFilter['ID'] = $elementId;
        } else {
            $arFilter['=CODE'] = $elementCode;
        }

        if ($siteId = $iblockConfig?->getSiteId()) {
            $arFilter['IBLOCK_LID'] = $siteId;
        }

        //Request filter
        if ($filter = $iblockConfig?->getFilter()) {
            // Базовые (защитные) ключи имеют приоритет, а управляющие ключи фильтра
            // (LOGIC, PERMISSIONS_BY, SHOW_HISTORY и др.) из запроса отбрасываются.
            $arFilter = IblockHelper::mergeProtectedFilter($filter, $arFilter);
        }

        //Select
        $arSelect = $this->getArSelectForDetail($iblockConfig);

        $propertyCode = $this->getPropertiesCode($iblockConfig);

        $activeDateFormat = $this->getActiveDateFormat($iblockConfig);
        $activeToDateFormat = $iblockConfig->getActiveToDateFormat();
        $isHideDisplayValues = $iblockConfig->isHideDisplayValues();
        $isShowPrefixFieldData = $iblockConfig->isShowPrefixFieldData();
        $getPropertiesProperty = $iblockConfig->getPropertiesProperty();

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir($iblockId);

        // static::class в ключе — чтобы кеш наследника с изменённым payload не пересекался с базовым.
        $cacheKeyParts = $this->getElementDetailCacheKeyParts([
            static::class,
            $this->getAccessCacheId($iblockId),
            $propertyCode,
            $arFilter,
            $arSelect,
            $activeDateFormat,
            $activeToDateFormat,
            $getPropertiesProperty,
            $isHideDisplayValues,
            $isShowPrefixFieldData
        ], $iblockConfig);

        if ($cache->initCache(
            static::$cacheTime,
            __FUNCTION__ . md5(serialize($cacheKeyParts)),
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag('iblock_id_' . $iblockId);

            $res = \CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
            if ($obj = $res->GetNextElement()) {
                $data = $obj->GetFields();
                // Снимок сырых полей до преобразований для фронта — передаётся в хук appendElementDetailData().
                $rawFields = $data;

                $data["DISPLAY_ACTIVE_FROM"] = $this->getFormatDate($data["ACTIVE_FROM"] ?? '', $activeDateFormat);
                $data["DISPLAY_ACTIVE_TO"] = $this->getFormatDate($data["ACTIVE_TO"] ?? '', $activeToDateFormat);

                $this->convertFieldsDataForFront($data);

                //get property for view
                if (!$propertyCode && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures()) {
                    if ($list = \Bitrix\Iblock\Model\PropertyFeature::getDetailPageShowPropertyCodes(
                        $iblockId,
                        ['CODE' => 'Y']
                    )) {
                        $propertyCode = $list;
                    }
                }

                // Сырые свойства (до преобразований) — передаются в хук appendElementDetailData().
                $rawProperties = [];

                if ($propertyCode) {
                    $rawProperties = $obj->GetProperties();
                    $data['PROPERTIES'] = [];
                    $getAll = is_array($propertyCode) && $propertyCode[0] === '*';
                    if ($getAll) {
                        $data['PROPERTIES'] = $rawProperties;
                    } else {
                        foreach ($propertyCode as $propCode) {
                            if (isset($rawProperties[$propCode])) {
                                $data['PROPERTIES'][$propCode] = $rawProperties[$propCode];
                            }
                        }
                    }
                    $this->convertPropertiesDataForFront($data['PROPERTIES']);
                }

                $data['BREADCRUMBS'] = [];

                if ($this->iblock) {
                    $data['BREADCRUMBS'][] = [
                        'TEXT' => $this->iblock["NAME"],
                        'HREF' => $data["LIST_PAGE_URL"]
                    ];
                }

                $data["SECTION_URL"] = "";

                $sectionPath = ["PATH" => []];

                if ($data["IBLOCK_SECTION_ID"] > 0) {
                    $rsPath = \CIBlockSection::GetNavChain(
                        $data["IBLOCK_ID"],
                        $data["IBLOCK_SECTION_ID"],
                        [
                            "ID", "CODE", "XML_ID", "EXTERNAL_ID", "IBLOCK_ID",
                            "IBLOCK_SECTION_ID", "SORT", "NAME", "ACTIVE",
                            "DEPTH_LEVEL", "SECTION_PAGE_URL"
                        ]
                    );

                    while ($arPath = $rsPath->GetNext()) {
                        $ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($data["IBLOCK_ID"], $arPath["ID"]);
                        $arPath["IPROPERTY_VALUES"] = $ipropValues->getValues();
                        $sectionPath["PATH"][] = $arPath;
                        $data["SECTION_URL"] = $arPath["~SECTION_PAGE_URL"];
                    }
                }


                if ($sectionPath) {
                    foreach ($sectionPath["PATH"] as $arPath) {
                        $text = $arPath["NAME"];
                        if ($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"] != "") {
                            $text = $arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"];
                        }
                        $data['BREADCRUMBS'][] = [
                            'TEXT' => $text,
                            'HREF' => $arPath["SECTION_PAGE_URL"]
                        ];
                    }
                }

                $data['BREADCRUMBS'][] = [
                    'TEXT' => (
                    (string)($data["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"] ?? '') !== ''
                        ? $data["IPROPERTY_VALUES"]["ELEMENT_PAGE_TITLE"]
                        : $data["NAME"]
                    )
                ];

                $data = $this->appendElementDetailData($data, $rawFields, $rawProperties, $obj, $iblockConfig);
            } else {
                $cache->abortDataCache();
                $cacheManager->abortTagCache();
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }
        return $data;
    }
    //</editor-fold>

    //<editor-fold desc="Sections">
    /**
     * Метод end-point для получения списка разделов инфоблока в формате для FRONT
     * @param string $iblockCode
     * @param IblockListSectionsConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getSectionListAction(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $data = [];

        $iblockId = (int)$iblock['ID'];

        //Filter
        $arFilter = $this->getArFilterForList($iblockId, $iblockConfig);
        //Select
        $arSelect = $this->getArSelectForSectionList($iblockConfig);
        //bIncCnt
        $bIncCnt = $iblockConfig->getBIncCnt() ?? false;
        //Order
        $arOrder = $this->getArOrderForList($iblockConfig);

        $limit = $iblockConfig->getLimit() ?? 10;
        $offset = $iblockConfig->getOffset() ?? 0;
        $arNavStartParams = [
            'nTopCount' => $limit,
            'nOffset'   => $offset
        ];

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir($iblockId);

        // static::class в ключе — чтобы кеш наследника с изменённым payload не пересекался с базовым.
        $cacheKeyParts = $this->getSectionListCacheKeyParts([
            static::class,
            $this->getAccessCacheId($iblockId),
            $arFilter,
            $bIncCnt,
            $arOrder,
            $arSelect,
            $arNavStartParams,
            $iblockConfig->isShowTotal()
        ], $iblockConfig);

        if ($cache->initCache(
            static::$cacheTime,
            __FUNCTION__ . md5(serialize($cacheKeyParts)),
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag('iblock_id_' . $iblockId);

            \CPageOption::SetOptionString("main", "nav_page_in_session", "N");

            $res = \CIBlockSection::GetList($arOrder, $arFilter, $bIncCnt, $arSelect, $arNavStartParams);

            $sectionUfFields = $this->getSectionUfFields($iblockId, $iblockConfig->getSiteId() ?: 'ru');

            while ($arSection = $res->GetNext()) {
                // Снимок сырых полей до преобразований для фронта — передаётся в хук appendSectionListItemData().
                $rawFields = $arSection;

                \Bitrix\Iblock\InheritedProperty\SectionValues::queue($arSection["IBLOCK_ID"], $arSection["ID"]);

                $time = DateTime::createFromUserTime($arSection["TIMESTAMP_X"]);
                if (
                    !isset($data['meta']["timestampX"])
                    || $time->getTimestamp() > $data['meta']["timestampX"]->getTimestamp()
                ) {
                    $data['meta']["timestampX"] = $time;
                }

                $this->convertSectionFieldsDataForFront($arSection, $sectionUfFields);

                $data['items'][] = $this->appendSectionListItemData($arSection, $rawFields, $iblockConfig);
            }

            if ($iblockConfig->isShowTotal()) {
                //Доп. запрос на получение общего кол-ва разделов: nOffset/nTopCount искажают SelectedRowsCount.
                $resCount = \CIBlockSection::GetList([], $arFilter, false, ['ID']);
                $data['meta']['total'] = $resCount->SelectedRowsCount();
            }
            $data['meta']['limit'] = $limit;
            $data['meta']['offset'] = $offset;

            $data = $this->appendSectionListData($data, $iblockConfig);

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        $result->setData($data);

        return $result;
    }

    /**
     * Возвращает uf field для разделов инфоблока.
     * @param $iblockId
     * @param string|null $languageId
     * @return array
     */
    protected function getSectionUfFields(int $iblockId, ?string $languageId = null): array
    {
        $sectionUfFields = [];
        $filter = [
            'ENTITY_ID' => "IBLOCK_{$iblockId}_SECTION"
        ];
        if ($languageId) {
            $filter['LANG'] = $languageId;
        }
        if ($arUserTypes = \CUserTypeEntity::GetList(['SORT' => 'ASC', 'NAME' => 'ASC'], $filter)) {
            while ($arUserType = $arUserTypes->Fetch()) {
                $sectionUfFields[$arUserType['FIELD_NAME']] = $arUserType;
            }
        }
        return $sectionUfFields;
    }

    /**
     * Получить элемент инфоблока
     * @param string $iblockCode
     * @param int|null $sectionId
     * @param string|null $sectionCode
     * @param \Uplab\Api\Utils\Config\Iblock\IblockDetailSectionConfig $iblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    protected function getSectionDetail(string $iblockCode, ?int $sectionId, ?string $sectionCode, IblockDetailSectionConfig $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $this->iblock = $iblock;
        $this->config = $iblockConfig;

        $data = $this->getIblockSectionData((int)$iblock['ID'], $sectionId, $sectionCode, $iblockConfig);
        if (!$data) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.ELEMENT_NOT_FOUND'));
        }

        $result->setData($data);

        return $result;
    }

    /**
     * Получить информацию о детальной странице раздела.
     * @param int $iblockId
     * @param int|null $sectionId
     * @param string|null $sectionCode
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return null|array
     * @throws ArgumentException
     * @throws LoaderException
     * @throws SystemException
     */
    protected function getIblockSectionData(int $iblockId, ?int $sectionId, ?string $sectionCode, mixed $iblockConfig): ?array
    {
        $data = null;

        //Filter
        $arFilter = [
            'ACTIVE'            => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
        ];
        if ($sectionId > 0) {
            $arFilter['ID'] = $sectionId;
        } else {
            $arFilter['=CODE'] = $sectionCode;
        }

        if ($siteId = $iblockConfig->getSiteId()) {
            $arFilter['IBLOCK_LID'] = $siteId;
        }

        //Request filter
        if ($filter = $iblockConfig->getFilter()) {
            // Базовые (защитные) ключи имеют приоритет, а управляющие ключи фильтра
            // (LOGIC, PERMISSIONS_BY, SHOW_HISTORY и др.) из запроса отбрасываются.
            $arFilter = IblockHelper::mergeProtectedFilter($filter, $arFilter);
        }

        $bIncCnt = $iblockConfig->getBIncCnt() ?? false;

        //Select
        $arSelect = $this->getArSelectForSectionDetail($iblockConfig);

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir($iblockId);

        // static::class в ключе — чтобы кеш наследника с изменённым payload не пересекался с базовым.
        $cacheKeyParts = $this->getSectionDetailCacheKeyParts([
            static::class,
            $this->getAccessCacheId($iblockId),
            $arFilter,
            $arSelect,
            $bIncCnt
        ], $iblockConfig);

        if ($cache->initCache(
            static::$cacheTime,
            __FUNCTION__ . md5(serialize($cacheKeyParts)),
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag('iblock_id_' . $iblockId);

            $res = \CIBlockSection::GetList([], $arFilter, $bIncCnt, $arSelect);
            if ($obj = $res->GetNextElement()) {
                $data = $obj->GetFields();
                // Снимок сырых полей до преобразований для фронта — передаётся в хук appendSectionDetailData().
                $rawFields = $data;

                $ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($data["IBLOCK_ID"], $data["ID"]);
                $data["IPROPERTY_VALUES"] = $ipropValues->getValues();

                $sectionUfFields = $this->getSectionUfFields($iblockId, $iblockConfig->getSiteId() ?: 'ru');
                $this->convertSectionFieldsDataForFront($data, $sectionUfFields);

                $data["BREADCRUMBS"] = [];
                //iblock
                if ($this->iblock) {
                    $data['BREADCRUMBS'][] = [
                        'TEXT' => $this->iblock["NAME"],
                        'HREF' => $data["LIST_PAGE_URL"]
                    ];
                }

                //sections
                $sectionsPath = [];
                $rsPath = \CIBlockSection::GetNavChain(
                    $data["IBLOCK_ID"],
                    $data["ID"],
                    [
                        "ID", "CODE", "XML_ID", "EXTERNAL_ID", "IBLOCK_ID",
                        "IBLOCK_SECTION_ID", "SORT", "NAME", "ACTIVE",
                        "DEPTH_LEVEL", "SECTION_PAGE_URL"
                    ]
                );
                while ($arPath = $rsPath->GetNext()) {
                    $ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($iblockId, $arPath["ID"]);
                    $arPath["IPROPERTY_VALUES"] = $ipropValues->getValues();
                    $sectionsPath["PATH"][] = $arPath;
                }
                unset($rsPath);

                if (!empty($sectionsPath["PATH"])) {
                    foreach ($sectionsPath["PATH"] as $arPath) {
                        $text = $arPath["NAME"];
                        if (
                            isset($arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"])
                            && $arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"] !== ""
                        ) {
                            $text = $arPath["IPROPERTY_VALUES"]["SECTION_PAGE_TITLE"];
                        }
                        $data["BREADCRUMBS"][] = [
                            'TEXT' => $text,
                            'HREF' => $arPath['ID'] != $data['ID'] ? $arPath["SECTION_PAGE_URL"] : ''
                        ];
                    }
                }

                $data = $this->appendSectionDetailData($data, $rawFields, $obj, $iblockConfig);
            } else {
                $cache->abortDataCache();
                $cacheManager->abortTagCache();
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        return $data;
    }
    //</editor-fold>

    //<editor-fold desc="Properties">
    /**
     * Метод end-point для получения свойств инфоблока в формате для FRONT.
     * @param string $iblockCode
     * @param IblockListConfig|mixed $iblockConfig
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getIblockPropertiesAction(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            return $this->setErrorResponse($result, HttpStatus::FAILED_DEPENDENCY->value, Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock']));
        }

        $iblock = $this->getIblockByCode($iblockCode, $iblockConfig->getSiteId());
        if (!$iblock) {
            return $this->setErrorResponse($result, HttpStatus::NOT_FOUND->value, Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND'));
        }

        $iblockId = (int)$iblock['ID'];

        $this->iblock = $iblock;

        $data = [];

        //Default  filter
        $arFilter = [
            'ACTIVE'            => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
        ];

        //Request filter
        if ($filter = $iblockConfig->getFilter()) {
            // Базовые (защитные) ключи имеют приоритет, а управляющие ключи фильтра
            // (LOGIC, PERMISSIONS_BY, SHOW_HISTORY и др.) из запроса отбрасываются.
            $arFilter = IblockHelper::mergeProtectedFilter($filter, $arFilter);
        }

        //Default Order
        $arOrder = ['sort' => 'asc', 'name' => 'asc'];

        //request order
        if ($order = $iblockConfig->getOrder()) {
            $arOrder = $order;
        }

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        $cacheDir = $this->getCacheDir($iblockId);
        if ($cache->initCache(
            static::$cacheTime,
            __FUNCTION__ . md5(
                serialize([
                    $this->getAccessCacheId($iblockId),
                    $arOrder,
                    $arFilter
                ])
            )
            ,
            $cacheDir
        )) {
            $data = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
            $cacheManager->startTagCache($cacheDir);
            $cacheManager->registerTag('iblock_id_' . $iblockId);

            $properties = \CIBlockProperty::GetList($arOrder, $arFilter);
            while ($propFields = $properties->GetNext(true, false)) {
                $data['items'][] = $propFields;
            }

            $cacheManager->endTagCache();
            $cache->endDataCache($data);
        }

        $result->setData($data);

        return $result;
    }
    //</editor-fold>

    //<editor-fold desc="Extension points">
    /*
     * Точки расширения для проектов-наследников.
     *
     * append*Data() вызываются ВНУТРИ кешируемой области, непосредственно перед endDataCache()
     * (поэлементные — внутри цикла выборки): всё, что добавит наследник, попадает в кеш
     * (по умолчанию ~24 часа, тег iblock_id_{ID}) — не полагайтесь в них на per-request данные
     * (текущий пользователь, время и т.п.).
     * В момент вызова тег-кеш открыт: если доп. данные берутся из другого инфоблока, зарегистрируйте
     * его тег — Application::getInstance()->getTaggedCache()->registerTag('iblock_id_' . $id).
     *
     * Порядок параметров: данные → сырые поля → сырые свойства → объект выборки → конфиг
     * (конфиг последним — как в остальных методах сервиса).
     *
     * $rawFields — снимок полей сразу после выборки (GetFields()/GetNext()), ДО преобразований
     * для фронта: с '~'-полями и системными полями, которые из итогового $data вырезаются.
     * $rawProperties — свойства элемента из $obj->GetProperties() до преобразований. Заполнены,
     * только если свойства запрошены (передан propertyCode или коды определены через property
     * features); иначе пустой массив — при необходимости вызовите $obj->GetProperties() сами.
     * $obj — объект строки выборки (\_CIBElement), где есть — можно дёрнуть его методы.
     *
     * get*CacheKeyParts() формируют ключ кеша. Если доп. данные зависят от входных параметров
     * (настройка, поле конфига и т.д.) — добавьте эти параметры к $parts в наследнике, иначе
     * разные входы склеятся в одну кеш-запись. Базовые части (включая static::class) не удаляйте.
     */

    /**
     * Хук: дополнить payload списка элементов целиком (items + meta) перед сохранением в кеш.
     * @param array $data
     * @param IblockListElementsConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendElementListData(array $data, mixed $iblockConfig): array
    {
        return $data;
    }

    /**
     * Хук: дополнить один элемент списка (вызывается в цикле выборки).
     * @param array $arItem - подготовленный для фронта элемент
     * @param array $rawFields - сырые поля элемента до преобразований
     * @param array $rawProperties - сырые свойства из GetProperties() (пусто, если свойства не запрошены)
     * @param \_CIBElement|mixed $obj - объект строки выборки
     * @param IblockListElementsConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendElementListItemData(array $arItem, array $rawFields, array $rawProperties, mixed $obj, mixed $iblockConfig): array
    {
        return $arItem;
    }

    /**
     * Хук: дополнить payload детальной карточки элемента перед сохранением в кеш.
     * @param array $data - подготовленные для фронта данные
     * @param array $rawFields - сырые поля элемента до преобразований
     * @param array $rawProperties - сырые свойства из GetProperties() (пусто, если свойства не запрошены)
     * @param \_CIBElement|mixed $obj - объект строки выборки
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendElementDetailData(array $data, array $rawFields, array $rawProperties, mixed $obj, mixed $iblockConfig): array
    {
        return $data;
    }

    /**
     * Хук: дополнить payload списка разделов целиком (items + meta) перед сохранением в кеш.
     * @param array $data
     * @param IblockListSectionsConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendSectionListData(array $data, mixed $iblockConfig): array
    {
        return $data;
    }

    /**
     * Хук: дополнить один раздел списка (вызывается в цикле выборки).
     * @param array $arSection - подготовленный для фронта раздел
     * @param array $rawFields - сырые поля раздела до преобразований
     * @param IblockListSectionsConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendSectionListItemData(array $arSection, array $rawFields, mixed $iblockConfig): array
    {
        return $arSection;
    }

    /**
     * Хук: дополнить payload детальной страницы раздела перед сохранением в кеш.
     * @param array $data - подготовленные для фронта данные
     * @param array $rawFields - сырые поля раздела до преобразований (включая UF-поля)
     * @param \_CIBElement|mixed $obj - объект строки выборки
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return array
     */
    protected function appendSectionDetailData(array $data, array $rawFields, mixed $obj, mixed $iblockConfig): array
    {
        return $data;
    }

    /**
     * Хук: части ключа кеша списка элементов.
     * @param array $parts
     * @param IblockListElementsConfig|mixed $iblockConfig
     * @return array
     */
    protected function getElementListCacheKeyParts(array $parts, mixed $iblockConfig): array
    {
        return $parts;
    }

    /**
     * Хук: части ключа кеша детальной карточки элемента.
     * @param array $parts
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return array
     */
    protected function getElementDetailCacheKeyParts(array $parts, mixed $iblockConfig): array
    {
        return $parts;
    }

    /**
     * Хук: части ключа кеша списка разделов.
     * @param array $parts
     * @param IblockListSectionsConfig|mixed $iblockConfig
     * @return array
     */
    protected function getSectionListCacheKeyParts(array $parts, mixed $iblockConfig): array
    {
        return $parts;
    }

    /**
     * Хук: части ключа кеша детальной страницы раздела.
     * @param array $parts
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return array
     */
    protected function getSectionDetailCacheKeyParts(array $parts, mixed $iblockConfig): array
    {
        return $parts;
    }
    //</editor-fold>

    //<editor-fold desc="Utils">
    /**
     * Путь для кеша инфоблока.
     * @param string|int $iblockId
     * @return string
     */
    /**
     * Сегмент доступа для ключа кеша: в простом режиме прав видимость данных — функция
     * набора групп пользователя, в расширенном кеш делится ещё и по пользователю
     * (персональные права, отдел, «создатель»). Точка расширения: переопределите, если
     * наследник добавляет в payload данные, зависящие от пользователя.
     *
     * @param int|null $iblockId ID инфоблока; null — режим прав неизвестен
     * @return string
     */
    protected function getAccessCacheId(?int $iblockId = null): string
    {
        return IblockHelper::getAccessCacheId($iblockId);
    }

    protected function getCacheDir(string|int $iblockId): string
    {
        return '/iblock/' . $iblockId;
    }
    //</editor-fold>

    //<editor-fold desc="Utils Sections">
    /**
     * Преобразует массив FIELDS из Битрикс данных в данные для FRONT.
     * @param array $arSection
     * @return void
     */
    protected function convertSectionFieldsDataForFront(array &$arSection, array $sectionUfFields = []): void
    {
        if (!empty($arSection)) {
            if ($sectionUfFields) {
                foreach ($sectionUfFields as $ufCode => $ufData) {
                    if (isset($arSection[$ufCode])) {
                        $currentValue = $arSection[$ufCode];
                        $currentValueTilda = $arSection['~' . $ufCode] ?? null;

                        $arSection['FIELDS'][$ufCode] = $ufData;
                        $sectionUfField =& $arSection['FIELDS'][$ufCode];
                        $sectionUfField['VALUE'] = $currentValue;
                        $sectionUfField['~VALUE'] = $currentValueTilda;

                        $arDisplayValue = [];

                        if (is_array($sectionUfField['VALUE']) ? !empty($sectionUfField['VALUE']) : strlen(
                                $sectionUfField['VALUE']
                            ) > 0) {
                            $arValues = (array)$sectionUfField['~VALUE'];

                            switch ($sectionUfField['USER_TYPE_ID']) {
                                case 'file':
                                    $arFiles = [];
                                    foreach ($arValues as $val) {
                                        if ($arFile = \CFile::GetFileArray($val)) {
                                            $arFiles[] = $arFile;
                                            $arDisplayValue[] = htmlspecialcharsbx($arFile['SRC']);
                                        }
                                    }
                                    $fileCount = count($arFiles);
                                    if ($fileCount == 1) {
                                        $sectionUfField['FILE_VALUE'] = $arFiles[0];
                                    } elseif ($fileCount > 1) {
                                        $sectionUfField['FILE_VALUE'] = $arFiles;
                                    } else {
                                        $sectionUfField['FILE_VALUE'] = false;
                                    }
                                    unset($fileCount, $arFiles);
                                    break;
                                default:
                                    foreach ($arValues as $val) {
                                        $arDisplayValue[] = $val;
                                    }
                                    break;
                            }

                            if ($events = GetModuleEvents('uplab.api', 'OnIblockConvertSectionDisplayValue', true)) {
                                foreach ($events as $arEvent) {
                                    ExecuteModuleEventEx($arEvent, [$sectionUfField, &$arDisplayValue]);
                                }
                            }

                            if ($sectionUfField['USER_TYPE_ID'] === 'sprint_editor') {
                                unset($sectionUfField['VALUE'], $sectionUfField['~VALUE'], $arSection[$ufCode], $arSection['~' . $ufCode]);
                            }
                        }
                        $sectionUfField['DISPLAY_VALUE'] = $arDisplayValue;

                        unset($sectionUfField);
                    }
                }
            }

            $this->hidePrefixData($arSection);

            $ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionValues($arSection["IBLOCK_ID"], $arSection["ID"]);

            $arSection["IPROPERTY_VALUES"] = $ipropValues->getValues();
            \Bitrix\Iblock\Component\Tools::getFieldImageData(
                $arSection,
                ['PICTURE', 'DETAIL_PICTURE'],
                \Bitrix\Iblock\Component\Tools::IPROPERTY_ENTITY_SECTION,
                'IPROPERTY_VALUES'
            );

            //hide systems data
            foreach (
                [
                    'EXTERNAL_ID',
                    'IBLOCK_TYPE_ID',
                    'IBLOCK_EXTERNAL_ID',
                    'PREVIEW_TEXT_TYPE',
                    'DETAIL_TEXT_TYPE'
                ] as $code
            ) {
                if (isset($arSection[$code])) {
                    unset($arSection[$code]);
                }
            }
        }
    }

    /**
     * Возвращает массив для выборки
     * @param IblockListElementsConfig|mixed|null $iblockConfig
     * @return string[]|null
     * @throws LoaderException
     */
    protected function getArSelectForList(mixed $iblockConfig): ?array
    {
        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'ACTIVE',
            'NAME',
            'CODE',
            'PREVIEW_TEXT',
            'PREVIEW_PICTURE',
            'DETAIL_PAGE_URL',
            'ACTIVE_TO',
            'ACTIVE_FROM',
            'IBLOCK_SECTION_ID',
            'SHOW_COUNTER',
            'TIMESTAMP_X'
        ];
        if (Loader::includeModule('workflow')) {
            $arSelect[] = 'WF_STATUS_ID';
            $arSelect[] = 'WF_PARENT_ELEMENT_ID';
            $arSelect[] = 'WF_NEW';
        }
        //Request select
        if ($select = $iblockConfig?->getSelect()) {
            $arSelect = array_merge($arSelect, $select);
        }
        return $arSelect;
    }

    /**
     * Возвращает массив для выборки
     * @param IblockListSectionsConfig | mixed $iblockConfig
     * @return string[]|null
     * @throws LoaderException
     */
    protected function getArSelectForSectionList(mixed $iblockConfig): ?array
    {
        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'ACTIVE',
            'NAME',
            'CODE',
            'DESCRIPTION',
            'DEPTH_LEVEL',
            'PICTURE',
            'SECTION_PAGE_URL',
            'IBLOCK_SECTION_ID',
            'TIMESTAMP_X',
            //'CNT_ACTIVE',
            //'UF_*'
        ];
        if (Loader::includeModule('workflow')) {
            $arSelect[] = 'WF_STATUS_ID';
            $arSelect[] = 'WF_PARENT_ELEMENT_ID';
            $arSelect[] = 'WF_NEW';
        }
        //Request select
        if ($select = $iblockConfig?->getSelect()) {
            $arSelect = array_merge($arSelect, $select);
        }
        return $arSelect;
    }

    /**
     * @param IblockDetailSectionConfig|mixed $iblockConfig
     * @return string[]|null
     * @throws LoaderException
     */
    protected function getArSelectForSectionDetail(mixed $iblockConfig): ?array
    {
        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'ACTIVE',
            'NAME',
            'CODE',
            'DESCRIPTION',
            'DEPTH_LEVEL',
            'PICTURE',
            'SECTION_PAGE_URL',
            'LIST_PAGE_URL',
            'IBLOCK_SECTION_ID',
            'TIMESTAMP_X',
            //'CNT_ACTIVE',
            //'UF_*'
        ];
        if (Loader::includeModule('workflow')) {
            $arSelect[] = 'WF_STATUS_ID';
            $arSelect[] = 'WF_PARENT_ELEMENT_ID';
            $arSelect[] = 'WF_NEW';
        }
        //Request select
        if ($select = $iblockConfig?->getSelect()) {
            $arSelect = array_merge($arSelect, $select);
        }
        return $arSelect;
    }

    /**
     * Возвращает массив для выборки
     * @param IblockDetailElementConfig|mixed $iblockConfig
     * @return string[]|null
     * @throws LoaderException
     */
    protected function getArSelectForDetail(mixed $iblockConfig): ?array
    {
        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'ACTIVE',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'DETAIL_TEXT',
            'DETAIL_PICTURE',
            'DETAIL_PAGE_URL',
            'LIST_PAGE_URL',
            'ACTIVE_TO',
            'ACTIVE_FROM',
            'TIMESTAMP_X',
            'CANONICAL_PAGE_URL'
        ];
        if (Loader::includeModule('workflow')) {
            $arSelect[] = 'WF_STATUS_ID';
            $arSelect[] = 'WF_PARENT_ELEMENT_ID';
            $arSelect[] = 'WF_NEW';
        }

        //Request select
        if ($select = $iblockConfig?->getSelect()) {
            $arSelect = array_merge($arSelect, $select);
        }
        return $arSelect;
    }
    //</editor-fold>
    //<editor-fold desc="Utils Elements">
    /**
     * Преобразует массив PROPERTIES из Битрикс данных в данные для FRONT.
     * @param array $properties
     * @return void
     */
    protected function convertPropertiesDataForFront(array &$properties, bool $stopRecursive = false): void
    {
        if (!empty($properties)) {
            $isHideDisplayValues = $this->config?->isHideDisplayValues() ?? false;

            foreach ($properties as $propertyCode => &$propertyData) {
                $data = $propertyData;

                $propertyData = [
                    'ID'             => $data['ID'],
                    'NAME'           => $data['NAME'],
                    'CODE'           => $propertyCode,
                    'SORT'           => $data['SORT'] ?? null,
                    'PROPERTY_TYPE'  => $data['PROPERTY_TYPE'],
                    'USER_TYPE'      => $data['USER_TYPE'],
                    'MULTIPLE'       => $data['MULTIPLE'],
                    'LINK_IBLOCK_ID' => $data['LINK_IBLOCK_ID'],
                    'VALUE'          => $data['VALUE'],
                    'DESCRIPTION'    => $data['DESCRIPTION'],
                    '~VALUE'         => $data['~VALUE'],
                    '~DESCRIPTION'   => $data['~DESCRIPTION'],
                ];

                if (
                    (!$isHideDisplayValues)
                    &&
                    (is_array($propertyData['VALUE']) ?
                        !empty($propertyData['VALUE']) :
                        strlen((string)$propertyData['VALUE']) > 0
                    )
                ) {
                    $arValues = (array)$propertyData['~VALUE'];

                    if ($arValues) {
                        $arDisplayValue = [];
                        switch ($propertyData['PROPERTY_TYPE']) {
                            case PropertyTable::TYPE_ELEMENT:
                                $arLinkElements = [];
                                foreach ($arValues as $val) {
                                    $val = (int)$val;
                                    if ($val > 0) {
                                        if (!isset(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val])) {
                                            //USED TO GET "LINKED" ELEMENTS
                                            $rsLink = CIBlockElement::GetList(
                                                [],
                                                [
                                                    'ID'                => $val,
                                                    'ACTIVE'            => 'Y',
                                                    'ACTIVE_DATE'       => 'Y',
                                                    'CHECK_PERMISSIONS' => 'Y',
                                                    'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
                                                ],
                                                false,
                                                false,
                                                [
                                                    'ID',
                                                    'IBLOCK_ID',
                                                    'NAME',
                                                    'DETAIL_PAGE_URL',
                                                    'PREVIEW_PICTURE',
                                                    'DETAIL_PICTURE',
                                                    'SORT',
                                                ]
                                            );
                                            self::$nameCache[PropertyTable::TYPE_ELEMENT][$val] = null;
                                            if ($obj = $rsLink->GetNextElement()) {
                                                self::$nameCache[PropertyTable::TYPE_ELEMENT][$val] = $obj->GetFields();
                                                $this->convertFieldsDataForFront(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val]);
                                                unset(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val]['IPROPERTY_VALUES']);
                                                if (!$stopRecursive && $propCodes = $this->config?->getPropertiesProperty()[$propertyCode] ?? []) {
                                                    $prop = $obj->GetProperties();
                                                    if ($prop) {
                                                        foreach ($propCodes as $propCode) {
                                                            if (isset($prop[$propCode])) {
                                                                self::$nameCache[PropertyTable::TYPE_ELEMENT][$val]['PROPERTIES'][$propCode] = $prop[$propCode];
                                                            }
                                                        }
                                                        if (!empty(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val]['PROPERTIES'])) {
                                                            $this->convertPropertiesDataForFront(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val]['PROPERTIES'], true);
                                                        }
                                                    }
                                                }
                                            }
                                            unset($rsLink);
                                        }
                                        if (is_array(self::$nameCache[PropertyTable::TYPE_ELEMENT][$val])) {
                                            $row = self::$nameCache[PropertyTable::TYPE_ELEMENT][$val];

                                            $arDisplayValue[] = $row['NAME'];
                                            $arLinkElements[$val] = $row;
                                            unset($row);
                                        }
                                    }
                                }
                                $propertyData['LINK_ELEMENT_VALUE'] = (!empty($arLinkElements) ? $arLinkElements : false);

                                break;
                            case PropertyTable::TYPE_SECTION:
                                $arLinkSections = [];
                                foreach ($arValues as $val) {
                                    $val = (int)$val;
                                    if ($val > 0) {
                                        if (!isset(self::$nameCache[PropertyTable::TYPE_SECTION][$val])) {
                                            //USED TO GET SECTIONS NAMES
                                            $rsSection = \CIBlockSection::GetList(
                                                [],
                                                [
                                                    'ID'                => $val,
                                                    'CHECK_PERMISSIONS' => 'Y',
                                                    'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
                                                ],
                                                false,
                                                [
                                                    'ID',
                                                    'IBLOCK_ID',
                                                    'NAME',
                                                    'SECTION_PAGE_URL',
                                                    'PICTURE',
                                                    'DETAIL_PICTURE',
                                                    'SORT',
                                                ]
                                            );
                                            self::$nameCache[PropertyTable::TYPE_SECTION][$val] = $rsSection->GetNext();
                                            if (!empty(self::$nameCache[PropertyTable::TYPE_SECTION][$val]['PICTURE'])) {
                                                self::$nameCache[PropertyTable::TYPE_SECTION][$val]['PICTURE'] = \CFile::GetFileArray(self::$nameCache[PropertyTable::TYPE_SECTION][$val]['PICTURE']);
                                            }
                                            unset($rsSection);
                                        }
                                        if (is_array(self::$nameCache[PropertyTable::TYPE_SECTION][$val])) {
                                            $row = self::$nameCache[PropertyTable::TYPE_SECTION][$val];
                                            $arDisplayValue[] = $row['NAME'];
                                            $arLinkSections[$val] = self::$nameCache[PropertyTable::TYPE_SECTION][$val];
                                        }
                                    }
                                }
                                $propertyData['LINK_SECTION_VALUE'] = (!empty($arLinkSections) ? $arLinkSections : false);
                                unset($arLinkSections);
                                break;
                            case PropertyTable::TYPE_LIST:
                                $isCheckBox = \Bitrix\Iblock\Component\Tools::isCheckboxProperty($data);
                                foreach ($arValues as $val) {
                                    $val = (string)$val;
                                    if ($isCheckBox) {
                                        if ($val === \Bitrix\Iblock\Component\Tools::CHECKBOX_VALUE_YES) {
                                            $arDisplayValue[] = Loc::getMessage('IBLOCK_FORMATPROPS_PROPERTY_YES');
                                        } else {
                                            $arDisplayValue[] = Loc::getMessage('IBLOCK_FORMATPROPS_PROPERTY_NO');
                                        }
                                    } else {
                                        if ($val !== '') {
                                            $arDisplayValue[] = $val;
                                        }
                                    }
                                }
                                unset($isCheckBox);
                                break;
                            case PropertyTable::TYPE_FILE:
                                $arFiles = [];
                                foreach ($arValues as $val) {
                                    if ($arFile = \CFile::GetFileArray($val)) {
                                        $arFiles[] = $arFile;
                                        $arDisplayValue[] = htmlspecialcharsbx($arFile['SRC']);
                                    }
                                }
                                $fileCount = count($arFiles);
                                if ($fileCount == 1) {
                                    $propertyData['FILE_VALUE'] = $arFiles[0];
                                } elseif ($fileCount > 1) {
                                    $propertyData['FILE_VALUE'] = $arFiles;
                                } else {
                                    $propertyData['FILE_VALUE'] = false;
                                }
                                unset($fileCount, $arFiles);
                                break;
                            case PropertyTable::TYPE_STRING:
                                switch ($propertyData['USER_TYPE']) {
                                    case 'directory':
                                        $arLinkElements = [];
                                        if ($hlBlock = HlblockService::getEntityByTableName(
                                            $data['USER_TYPE_SETTINGS']['TABLE_NAME'] ?? ''
                                        )?->getDataClass()) {
                                            foreach ($arValues as $val) {
                                                if (!isset(self::$nameCache[$data['USER_TYPE_SETTINGS']['TABLE_NAME']][$val])) {
                                                    //USED TO GET "LINKED" ELEMENTS
                                                    $rsLink = $hlBlock::getList([
                                                        'select' => ['*'],
                                                        'filter' => ['=UF_XML_ID' => $val]
                                                    ])
                                                        ->fetch();
                                                    self::$nameCache[$data['USER_TYPE_SETTINGS']['TABLE_NAME']][$val] = $rsLink;
                                                    unset($rsLink);
                                                }
                                                if (is_array(self::$nameCache[$data['USER_TYPE_SETTINGS']['TABLE_NAME']][$val])) {
                                                    $row = self::$nameCache[$data['USER_TYPE_SETTINGS']['TABLE_NAME']][$val];

                                                    $arDisplayValue[] = $row['UF_NAME'];
                                                    $arLinkElements[$val] = $row;
                                                    unset($row);
                                                }
                                            }
                                        }
                                        $propertyData['LINK_ELEMENT_VALUE'] = (!empty($arLinkElements) ? $arLinkElements : false);
                                        break;
                                    case 'HTML':
                                        if ($propertyData['MULTIPLE'] !== 'Y') {
                                            $arDisplayValue[] = $arValues['TEXT'];
                                        } else {
                                            foreach ($arValues as $val) {
                                                $arDisplayValue[] = $val['TEXT'];
                                            }
                                        }
                                        break;
                                    default:
                                        foreach ($arValues as $val) {
                                            $arDisplayValue[] = $val;
                                        }
                                        break;
                                }
                                break;
                            default:
                                foreach ($arValues as $val) {
                                    $arDisplayValue[] = $val;
                                }
                                break;
                        }

                        if ($events = GetModuleEvents('uplab.api', 'OnIblockConvertPropertyDisplayValue', true)) {
                            foreach ($events as $arEvent) {
                                ExecuteModuleEventEx($arEvent, [$data, &$arDisplayValue]);
                            }
                        }

                        if ($propertyData['PROPERTY_TYPE'] === PropertyTable::TYPE_STRING && $propertyData['USER_TYPE'] === 'sprint_editor') {
                            unset($propertyData['VALUE'], $propertyData['~VALUE']);
                        }
                    }

                    $propertyData['DISPLAY_VALUE'] = $arDisplayValue;
                }
            }
            unset($propertyData);
        }
    }

    /**
     * Преобразует массив FIELDS из Битрикс данных в данные для FRONT.
     * @param array $arItem
     * @param string|null $deprecated
     * @return void
     */
    protected function convertFieldsDataForFront(array &$arItem, ?string $deprecated = null): void
    {
        if (!($this->config?->isShowPrefixFieldData() ?? false)) {
            $this->hidePrefixData($arItem);
        }

        $ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($arItem["IBLOCK_ID"], $arItem["ID"]);
        $arItem["IPROPERTY_VALUES"] = $ipropValues->getValues();
        \Bitrix\Iblock\Component\Tools::getFieldImageData(
            $arItem,
            ['PREVIEW_PICTURE', 'DETAIL_PICTURE'],
            \Bitrix\Iblock\Component\Tools::IPROPERTY_ENTITY_ELEMENT,
            'IPROPERTY_VALUES'
        );

        //hide systems data
        foreach (
            [
                'EXTERNAL_ID',
                'IBLOCK_TYPE_ID',
                'IBLOCK_EXTERNAL_ID',
                'PREVIEW_TEXT_TYPE',
                'DETAIL_TEXT_TYPE'
            ] as $code
        ) {
            if (isset($arItem[$code])) {
                unset($arItem[$code]);
            }
        }
    }

    protected function hidePrefixData(&$item): void
    {
        foreach ($item as $k => &$v) {
            if ($k && strlen($k) >= 2 && str_starts_with($k, '~')) {
                $item[mb_substr($k, 1)] = $v;
                unset($item[$k]);
            }
        }
        unset($v);
    }

    /**
     * Форматирует вывод даты
     * @param string|null $sDate
     * @param string|null $dateFormat
     * @return string
     */
    protected function getFormatDate(?string $sDate, ?string $dateFormat = null)
    {
        if ($sDate <> '' && $dateFormat) {
            return \CIBlockFormatProperties::DateFormat(
                $dateFormat,
                MakeTimeStamp($sDate, \CSite::GetDateFormat())
            );
        }
        return '';
    }

    /**
     * Возвращает массив для сортировки
     * @param IblockListElementsConfig|null|mixed $iblockConfig
     * @return string[]|null
     */
    protected function getArOrderForList(mixed $iblockConfig = null): ?array
    {
        $arOrder = [
            'SORT' => 'ASC',
            'NAME' => 'ASC'
        ];
        //request order
        if ($order = $iblockConfig?->getOrder()) {
            $arOrder = $order;
        }
        return $arOrder;
    }

    /**
     * @param $iblockId
     * @param IblockListSectionsConfig|IblockListElementsConfig|mixed $iblockConfig
     * @return array|null
     */
    protected function getArFilterForList($iblockId, mixed $iblockConfig): ?array
    {
        //Default  filter
        $arFilter = [
            'ACTIVE'            => 'Y',
            'CHECK_PERMISSIONS' => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ
        ];

        if ($siteId = $iblockConfig?->getSiteId()) {
            $arFilter['IBLOCK_LID'] = $siteId;
        }

        //Request filter
        if ($filter = $iblockConfig?->getFilter()) {
            // Базовые (защитные) ключи имеют приоритет, а управляющие ключи фильтра
            // (LOGIC, PERMISSIONS_BY, SHOW_HISTORY и др.) из запроса отбрасываются.
            $arFilter = IblockHelper::mergeProtectedFilter($filter, $arFilter);
        }

        return $arFilter;
    }

    /**
     * @param IblockListElementsConfig|IblockDetailElementConfig|mixed $iblockConfig
     * @return array|null
     */
    protected function getPropertiesCode(mixed $iblockConfig): ?array
    {
        $propertyCode = $iblockConfig?->getPropertyCode();

        if (!empty($propertyCode)) {
            $updateKey = false;
            foreach ($propertyCode as $key => $value) {
                if ($value == '') {
                    unset($propertyCode[$key]);
                    $updateKey = true;
                }
            }
            if ($updateKey) {
                $propertyCode = array_values($propertyCode);
            }
        }

        return $propertyCode;
    }

    /**
     * Возвращает формат для поля начало активности.
     * @param IblockDetailElementConfig|IblockListElementsConfig|mixed $iblockConfig
     * @return string|null
     */
    protected function getActiveDateFormat(mixed $iblockConfig): ?string
    {
        $activeDateFormat = $iblockConfig?->getActiveDateFormat();
        global $DB;
        if (empty($activeDateFormat) && $DB) {
            $activeDateFormat = (string)$DB->DateFormatToPHP(\CSite::GetDateFormat("SHORT"));
        }
        return $activeDateFormat;
    }
    //</editor-fold>
}
