<?php

namespace Uplab\Api\Model\Iblock;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SystemException;
use CIBlockRights;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Utils\Config\Iblock\MenuConfig;

class MenuService extends IblockService
{
    protected int $maxDepth = 5;
    protected array $tagCacheIblockId = [];
    protected array $arResult;

    /**
     * Метод end-point для получения информации о меню в формате для FRONT.
     * @param string $iblockCode
     * @param MenuConfig|mixed $iblockConfig
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getMenu(string $iblockCode, mixed $iblockConfig): \Bitrix\Main\Engine\Response\Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock'])
            ]);
        } else {
            $siteId = $iblockConfig->getSiteId();
            $languageId = $iblockConfig->getLanguageId();
            $iblockType = $iblockConfig->getIblockType() ?: 'menu_' . ($languageId ?: $siteId);

            $iblock = $this->getIblockByCode($iblockCode, $siteId, $iblockType);

            if (!$iblock) {
                $result->setStatus(HttpStatus::NOT_FOUND->value);
                $result->setData(['message' => Loc::getMessage('UPLAB_API.IBLOCK_NOT_FOUND')]);
            } else {
                $result->setData(
                    $this->getMenuData($iblock['ID'], $iblockConfig)
                );
            }
        }

        return $result;
    }

    /**
     * Данные для меню
     * @param int $iblockId
     * @param MenuConfig|mixed $iblockConfig
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getMenuData(int $iblockId, mixed $iblockConfig): array
    {
        $data = [];

        $cache = Cache::createInstance();
        $cacheDir = $this->getCacheDir($iblockId);

        $siteId = $iblockConfig->getSiteId();

        $arFilter = $this->getArFilter($iblockId, $iblockConfig);
        $arOrderList = $this->getArOrder($iblockConfig);
        $arSectionList = $this->getArSection($iblockConfig);
        $bIncCnt = false;

        if ($cache->initCache(
            static::$cacheTime, __FUNCTION__ . md5(
                serialize([
                    $this->getAccessCacheId($iblockId),
                    $siteId,
                    $bIncCnt,
                    $arFilter,
                    $arOrderList,
                    $arSectionList
                ])
            ), $cacheDir
        )) {
            $data = (array)($cache->getVars() ?? []);
        } elseif ($cache->startDataCache() && Loader::includeModule('iblock')) {
            $this->arResult = [];
            $this->tagCacheIblockId = [$iblockId];

            $this->putArResultData($arOrderList, $arFilter, $bIncCnt, $arSectionList);

            $data = $this->getFrontData($siteId);

            if ($this->tagCacheIblockId) {
                $cacheManager = Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);

                foreach ($this->tagCacheIblockId as $id) {
                    $cacheManager->registerTag('iblock_id_' . $id);
                }

                $cacheManager->endTagCache();
            }

            $cache->endDataCache($data);
        }

        $this->arResult = [];

        return $data;
    }

    /**
     * @param $section
     * @return array
     */
    protected function convertForFrontData(&$section): array
    {
        $e = [
            'text' => $section['TEXT'] ?? '',
            'href' => $section['HREF'] ?? ''
        ];
        if (!empty($section['EXTERNAL'] ?? false)) {
            $e['external'] = true;
        }
        if (!empty($section['SUBMENU'])) {
            $e['submenu'] = [];
            foreach ($section['SUBMENU'] as $sectionSubItem) {
                $e['submenu'][] = $this->convertForFrontData($sectionSubItem);
            }
        }
        return $e;
    }

    /**
     * Возвращает элементы инфоблоков.
     * @param array $iblockIds
     * @return array
     */
    protected function getElementsIblocks(array $iblockIds): array
    {
        $items = [];
        $res = \CIBlockElement::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                'IBLOCK_ID'         => $iblockIds,
                'ACTIVE'            => 'Y',
                'CHECK_PERMISSIONS' => 'Y',
                'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
            ],
            false,
            false,
            ['IBLOCK_ID', 'ID', 'NAME', 'DETAIL_PAGE_URL']
        );
        while ($element = $res->GetNext()) {
            $items[$element['IBLOCK_ID']][] = [
                'TEXT' => $element['NAME'],
                'HREF' => $element['DETAIL_PAGE_URL']
            ];
        }
        return $items;
    }

    /**
     * Возвращает id инфоблока "Страницы".
     * @param string|null $siteId
     * @return int
     * @throws ArgumentException
     * @throws LoaderException
     * @throws SystemException
     */
    protected function getPagesIblockId(?string $siteId): int
    {
        return (int)($this->getIblockByCode(PageService::IBLOCK_CODE, $siteId)['ID'] ?? 0);
    }

    /**
     * Список URL страниц инфоблока "Страницы".
     * @param array $ids
     * @param string|null $siteId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getPagesElements(array $ids, ?string $siteId): array
    {
        $items = [];
        if ($ids && $pageIblockId = $this->getPagesIblockId($siteId)) {
            $this->tagCacheIblockId[] = $pageIblockId;

            $res = \CIBlockElement::GetList(
                ['SORT' => 'ASC', 'NAME' => 'ASC'],
                [
                    'IBLOCK_ID' => $pageIblockId,
                    'ACTIVE'    => 'Y',
                    'ID'        => $ids,
                    'CHECK_PERMISSIONS' => 'Y',
                    'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
                ],
                false,
                false,
                ['ID', 'NAME', 'IBLOCK_ID', 'PROPERTY_LINK']
            );

            while ($page = $res->GetNext()) {
                if ($val = $page['PROPERTY_LINK_VALUE'] ?? '') {
                    $items[$page['ID']] = $val;
                }
            }
        }
        return $items;
    }

    protected function putPagesElements(array &$sectionList, ?array $pagesId = [], ?string $siteId = null): void
    {
        if ($pagesId && $elements = $this->getPagesElements(array_keys($pagesId), $siteId)) {
            foreach ($elements as $pageId => $url) {
                foreach ($pagesId[$pageId] as $secId) {
                    $sectionList[$secId]['HREF'] = $url;
                }
            }
        }
    }

    protected function putIblockElements(array &$sectionList, array $elementIblockIds): void
    {
        if ($elementIblockIds && $elements = $this->getElementsIblocks(array_keys($elementIblockIds))) {
            foreach ($elements as $elementIblockId => $item) {
                $this->tagCacheIblockId[] = $elementIblockId;
                foreach ($elementIblockIds[$elementIblockId] as $sectionId) {
                    $sectionList[$sectionId]['SUBMENU'] = array_merge($sectionList[$sectionId]['SUBMENU'] ?? [], $item);
                }
            }
        }
    }

    /**
     * Восстанавливаем иерархию
     * @param array $sectionList
     * @return void
     */
    protected function rebuildHierarchy(array &$sectionList): void
    {
        //восстанавливаем иерархию
        for ($depthLevel = $this->maxDepth; $depthLevel > 0; $depthLevel--) {
            foreach ($sectionList as $sectionId => $sectionValue) {
                if ($sectionValue['DEPTH_LEVEL'] == $depthLevel && $sectionValue['IBLOCK_SECTION_ID']) {
                    $parentId = $sectionValue['IBLOCK_SECTION_ID'];
                    // Родитель мог быть отфильтрован — не создаём «фантомный» пустой пункт.
                    if (isset($sectionList[$parentId])) {
                        $sectionList[$parentId]['SUBMENU'][] = $sectionValue;
                        unset($sectionList[$sectionId]);
                    }
                }
            }
        }
    }

    protected function getSectionItemData(array $section): array
    {
        return [
            'EXTERNAL'          => ($section['UF_EXTERNAL'] ?? '0') == 1,
            'HREF'              => $section['DESCRIPTION'] ?? '',
            'TEXT'              => $section['NAME'],
            'IBLOCK_SECTION_ID' => $section['IBLOCK_SECTION_ID'],
            'DEPTH_LEVEL'       => $section['DEPTH_LEVEL']
        ];
    }

    /**
     * Логика получения массива filter для получения данных из БД.
     * @param int $iblockId
     * @param mixed $iblockConfig
     * @return array
     */
    protected function getArFilter(int $iblockId, mixed $iblockConfig): array
    {
        $arFilter = [
            'ACTIVE'        => 'Y',
            'GLOBAL_ACTIVE' => 'Y',
            'IBLOCK_ID'     => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
        ];

        // Базовые ключи (ACTIVE/GLOBAL_ACTIVE/IBLOCK_ID) имеют приоритет, а управляющие
        // ключи фильтра (PERMISSIONS_BY и др.) из запроса отбрасываются.
        if ($val = $iblockConfig->getFilter()) {
            $arFilter = IblockHelper::mergeProtectedFilter($val, $arFilter);
        }
        return $arFilter;
    }

    /**
     * Логика получения массива order для получения данных из БД.
     * @param mixed $iblockConfig
     * @return mixed|string[]
     */
    protected function getArOrder(mixed $iblockConfig)
    {
        return $iblockConfig->getOrder() ?: ['SORT' => 'ASC', 'NAME' => 'ASC'];
    }

    /**
     * Логика получения массива select для получения данных из БД.
     * @param MenuConfig|mixed $iblockConfig
     * @return mixed|string[]
     */
    protected function getArSection(mixed $iblockConfig)
    {
        return array_merge([
            'ID',
            'IBLOCK_ID',
            'NAME',
            'DEPTH_LEVEL',
            'IBLOCK_SECTION_ID',
            'DESCRIPTION',
            'UF_LINK',
            'UF_SUB_IBLOCK_ELEMENTS',
            'UF_EXTERNAL'
        ], $iblockConfig->getSelect() ?? []);
    }

    /**
     * Логика получения разделов из БД
     * @param array $arOrderList
     * @param array $arFilter
     * @param bool|array $bIncCnt
     * @param array $arSectionList
     * @return void
     */
    protected function putArResultData(array $arOrderList, array $arFilter, bool|array $bIncCnt, array $arSectionList): void
    {
        $res = \CIBlockSection::GetList($arOrderList, $arFilter, $bIncCnt, $arSectionList);
        while ($section = $res->GetNext()) {
            $this->arResult[] = $section;
        }
    }

    /**
     * Логика обработки полученных разделов ранее.
     * @param string|null $siteId
     * @return array
     */
    protected function getFrontData(?string $siteId): array
    {
        $data = [];

        if ($this->arResult) {
            $sectionList = [];
            $pagesId = [];
            $elementIblockIds = [];

            foreach ($this->arResult as $section) {
                $secId = $section['ID'];

                $sectionList[$secId] = $this->getSectionItemData($section);

                if ($val = $section['UF_LINK'] ?? 0) {
                    $pagesId[$val][] = $secId;
                }
                if ($val = $section['UF_SUB_IBLOCK_ELEMENTS'] ?? 0) {
                    $elementIblockIds[$val][] = $secId;
                }
            }

            if ($sectionList) {
                $this->putPagesElements($sectionList, $pagesId, $siteId);

                $this->putIblockElements($sectionList, $elementIblockIds);

                $this->rebuildHierarchy($sectionList);

                foreach ($sectionList as $section) {
                    $data['items'][] = $this->convertForFrontData($section);
                }
            }
        }

        return $data;
    }
}
