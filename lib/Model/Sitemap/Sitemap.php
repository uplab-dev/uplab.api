<?php

namespace Uplab\Api\Model\Sitemap;


use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CIBlockElement;
use CIBlockRights;
use CIBlockSection;
use Uplab\Api\Model\Iblock\IblockHelper;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Utils\Config\BaseConfig;

/**
 * Генератор данных для sitemap.xml.
 *
 * Собирает URL из трёх источников:
 * - статические страницы инфоблока `pages` (свойство LINK),
 * - детальные страницы элементов инфоблоков ({@see $pagesIblockElementsCodes}),
 * - страницы разделов инфоблоков ({@see $pagesIblockSectionsCodes}).
 *
 * Результат кешируется на {@see $cacheTime} секунд в разрезе текущего сайта (SITE_ID).
 * Для расширения поведения достаточно наследоваться и переопределить нужные protected-методы
 * или свойства — публичный контракт {@see getData()} и {@see getXml()} не меняется.
 *
 * @example
 * ```php
 * class MySitemap extends Sitemap
 * {
 *     protected string $domain = 'https://example.ru';
 *     protected int $cacheTime = 3600;
 *     protected array $pagesIblockElementsCodes = ['news', 'products'];
 *     protected array $pagesIblockSectionsCodes = ['catalog'];
 * }
 * ```
 */
class Sitemap implements SitemapInterface
{
    /**
     * Домен, который подставляется перед каждым URL в sitemap.
     * Если пустая строка — URL формируются относительными.
     *
     * @var string
     */
    protected string $domain = '';

    /**
     * Время жизни кеша в секундах. По умолчанию 24 часа.
     *
     * @var int
     */
    protected int $cacheTime = 86400;

    /**
     * Символьные коды инфоблоков, детальные страницы элементов которых
     * попадают в sitemap.
     *
     * @var string[]
     */
    protected array $pagesIblockElementsCodes = [];

    /**
     * Символьные коды инфоблоков, страницы разделов которых
     * попадают в sitemap.
     *
     * @var string[]
     */
    protected array $pagesIblockSectionsCodes = [];
    /**
     * @var mixed|BaseConfig|null
     */
    protected mixed $config = null;

    /**
     * Возвращает JSON-ответ с данными для sitemap.xml.
     *
     * Данные берутся из кеша или собираются заново через {@see getPages()} и помещаются в кеш.
     * Кеш изолируется по сайту: директория `/{siteId}/sitemap`, ключ `sitemap`.
     * `siteId` берётся из конфига запроса ({@see BaseConfig::getSiteId()}).
     *
     * Коды статусов ответа:
     * - `200` — данные собраны (массив может быть пустым),
     * - `424` — не удалось подключить модуль `iblock`.
     *
     * @param mixed|BaseConfig|null $config
     * @return Json
     * @throws ArgumentTypeException
     * @throws LoaderException
     */
    public function getData(mixed $config = null): \Bitrix\Main\Engine\Response\Json
    {
        $this->config = $config;

        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('iblock')) {
            $result->setStatus(HttpStatus::FAILED_DEPENDENCY->value);
            $result->setData([
                'message' => Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'iblock'])
            ]);
        } else {
            $pages = [];

            $siteId = $config?->getSiteId() ?? (defined('SITE_ID') ? SITE_ID : 's1');
            // Карта собирается по нескольким инфоблокам с разными режимами прав,
            // поэтому сегмент строгий (для анонимного пользователя он тот же).
            $cacheId = 'sitemap_' . md5(IblockHelper::getAccessCacheId());
            $cacheDir = '/' . $siteId . '/sitemap';

            $cacheTime = $this->cacheTime;

            $cache = Cache::createInstance();
            if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
                $pages = $cache->getVars();
            } elseif ($cache->startDataCache()) {
                $pages = $this->getPages();
                $cache->endDataCache($pages);
            }

            $result->setData($pages);
        }

        return $result;
    }

    /**
     * Собирает полный список страниц для sitemap из всех источников.
     *
     * Метод вызывается внутри {@see getData()} при промахе кеша.
     * Переопределите его в наследнике, чтобы добавить собственные источники
     * или изменить состав страниц.
     *
     * @return array<int, array{loc: string, lastmod: string}>
     */
    protected function getPages(): array
    {
        $pages = [];

        // Страницы
        $this->getStaticPages($pages);

        // Элементы ИБ
        $this->getIblockElementsPages($pages);

        // Разделы ИБ
        $this->getIblockSectionsPages($pages);

        return $pages;
    }

    // -------------------------------------------------------------------------
    // Статические страницы (инфоблок `pages`)
    // -------------------------------------------------------------------------

    /**
     * Добавляет в список статические страницы из инфоблока {@see getStaticPagesIblockCode()}.
     *
     * Оркестратор: делегирует запрос и трансформацию в отдельные хуки,
     * каждый из которых можно точечно переопределить в наследнике.
     *
     * @param array<int, array{loc: string, lastmod: string}> $pages
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected function getStaticPages(array &$pages): void
    {
        $iblockId = IblockHelper::getIblockId($this->getStaticPagesIblockCode());
        if (!$iblockId) {
            return;
        }

        $res = CIBlockElement::GetList(
            $this->getStaticPagesOrder(),
            $this->getStaticPagesFilter($iblockId),
            false,
            false,
            $this->getStaticPagesSelect()
        );

        while ($el = $res->GetNext()) {
            if ($item = $this->buildStaticPageItem($el)) {
                $pages[] = $item;
            }
        }
    }

    /**
     * Символьный код инфоблока статических страниц.
     *
     * @return string
     */
    protected function getStaticPagesIblockCode(): string
    {
        return 'pages';
    }

    /**
     * Сортировка для выборки статических страниц.
     *
     * @return array
     */
    protected function getStaticPagesOrder(): array
    {
        return ['ID' => 'DESC'];
    }

    /**
     * Фильтр для выборки статических страниц.
     * Автоматически добавляет `IBLOCK_LID`, если передан `siteId`.
     *
     * @param int $iblockId
     * @return array
     */
    protected function getStaticPagesFilter(int $iblockId): array
    {
        $filter = [
            'CHECK_PERMISSIONS' => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
            'ACTIVE'            => 'Y',
            '!PROPERTY_LINK'    => false
        ];

        if ($siteId = $this->config?->getSiteId()) {
            $filter['IBLOCK_LID'] = $siteId;
        }

        return $filter;
    }

    /**
     * Список полей для выборки статических страниц.
     *
     * @return string[]
     */
    protected function getStaticPagesSelect(): array
    {
        return ['ID', 'NAME', 'CODE', 'LANG_DIR', 'TIMESTAMP_X_UNIX', 'PROPERTY_LINK'];
    }

    /**
     * Преобразует элемент инфоблока в запись sitemap.
     * Возвращает `null`, если элемент нужно пропустить.
     *
     * @param array $el Элемент из `CIBlockElement::GetList()->GetNext()`.
     * @return array{loc: string, lastmod: string}|null
     */
    protected function buildStaticPageItem(array $el): ?array
    {
        if (empty($el['LANG_DIR']) || empty($el['PROPERTY_LINK_VALUE']) || empty($el['TIMESTAMP_X_UNIX'])) {
            return null;
        }

        return [
            'loc'     => $this->domain . str_replace('//', '/', $el['LANG_DIR'] . $el['PROPERTY_LINK_VALUE']),
            'lastmod' => $this->getFormatDate($el['TIMESTAMP_X_UNIX']),
        ];
    }

    // -------------------------------------------------------------------------
    // Элементы инфоблоков
    // -------------------------------------------------------------------------

    /**
     * Добавляет в список детальные страницы элементов инфоблоков из {@see $pagesIblockElementsCodes}.
     *
     * @param array<int, array{loc: string, lastmod: string}> $pages
     */
    protected function getIblockElementsPages(array &$pages): void
    {
        if (empty($this->pagesIblockElementsCodes)) {
            return;
        }

        $res = CIBlockElement::GetList(
            $this->getElementPagesOrder(),
            $this->getElementPagesFilter(),
            false,
            false,
            []
        );

        while ($el = $res->GetNext()) {
            if ($item = $this->buildElementPageItem($el)) {
                $pages[] = $item;
            }
        }
    }

    /**
     * Сортировка для выборки элементов инфоблоков.
     *
     * @return array
     */
    protected function getElementPagesOrder(): array
    {
        return ['ID' => 'DESC'];
    }

    /**
     * Фильтр для выборки элементов инфоблоков.
     * Автоматически добавляет `IBLOCK_LID`, если передан `siteId`.
     *
     * @return array
     */
    protected function getElementPagesFilter(): array
    {
        $filter = [
            'CHECK_PERMISSIONS' => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
            'IBLOCK_CODE'       => $this->pagesIblockElementsCodes,
            'ACTIVE'            => 'Y',
        ];

        if ($siteId = $this->config?->getSiteId()) {
            $filter['IBLOCK_LID'] = $siteId;
        }

        return $filter;
    }

    /**
     * Преобразует элемент инфоблока в запись sitemap.
     * Возвращает `null`, если элемент нужно пропустить.
     *
     * @param array $el Элемент из `CIBlockElement::GetList()->GetNext()`.
     * @return array{loc: string, lastmod: string}|null
     */
    protected function buildElementPageItem(array $el): ?array
    {
        if (empty($el['DETAIL_PAGE_URL']) || empty($el['TIMESTAMP_X_UNIX'])) {
            return null;
        }

        return [
            'loc'     => $this->domain . $el['DETAIL_PAGE_URL'],
            'lastmod' => $this->getFormatDate($el['TIMESTAMP_X_UNIX']),
        ];
    }

    // -------------------------------------------------------------------------
    // Разделы инфоблоков
    // -------------------------------------------------------------------------

    /**
     * Добавляет в список страницы разделов инфоблоков из {@see $pagesIblockSectionsCodes}.
     *
     * @param array<int, array{loc: string, lastmod: string}> $pages
     */
    protected function getIblockSectionsPages(array &$pages): void
    {
        if (empty($this->pagesIblockSectionsCodes)) {
            return;
        }

        $res = CIBlockSection::GetList(
            $this->getSectionPagesOrder(),
            $this->getSectionPagesFilter(),
            true,
            [],
            false
        );

        while ($sect = $res->GetNext()) {
            if ($item = $this->buildSectionPageItem($sect)) {
                $pages[] = $item;
            }
        }
    }

    /**
     * Сортировка для выборки разделов инфоблоков.
     *
     * @return array
     */
    protected function getSectionPagesOrder(): array
    {
        return ['ID' => 'DESC'];
    }

    /**
     * Фильтр для выборки разделов инфоблоков.
     * Автоматически добавляет `IBLOCK_LID`, если передан `siteId`.
     *
     * @return array
     */
    protected function getSectionPagesFilter(): array
    {
        $filter = [
            'CHECK_PERMISSIONS' => 'Y',
            'IBLOCK_ACTIVE'     => 'Y',
            'MIN_PERMISSION'    => CIBlockRights::PUBLIC_READ,
            'IBLOCK_CODE'       => $this->pagesIblockSectionsCodes,
            'ACTIVE'            => 'Y',
        ];

        if ($siteId = $this->config?->getSiteId()) {
            $filter['IBLOCK_LID'] = $siteId;
        }

        return $filter;
    }

    /**
     * Преобразует раздел инфоблока в запись sitemap.
     * Возвращает `null`, если раздел нужно пропустить.
     *
     * @param array $sect Раздел из `CIBlockSection::GetList()->GetNext()`.
     * @return array{loc: string, lastmod: string}|null
     */
    protected function buildSectionPageItem(array $sect): ?array
    {
        if (empty($sect['SECTION_PAGE_URL']) || empty($sect['TIMESTAMP_X'])) {
            return null;
        }

        $siteFormat = \CSite::GetDateFormat('FULL') ?: 'DD.MM.YYYY HH:MI:SS';
        $dateInt = MakeTimeStamp($sect['TIMESTAMP_X'], $siteFormat);

        return [
            'loc'     => $this->domain . $sect['SECTION_PAGE_URL'],
            'lastmod' => $dateInt ? $this->getFormatDate($dateInt) : '',
        ];
    }

    // -------------------------------------------------------------------------
    // Утилиты
    // -------------------------------------------------------------------------

    /**
     * Форматирует Unix-timestamp в строку даты формата ISO 8601 (W3C Datetime).
     *
     * Переопределите в наследнике, если требуется другой формат даты.
     *
     * @param int $date Unix-timestamp.
     * @return string Дата в формате `c` (например, `2024-06-01T12:00:00+03:00`).
     */
    protected function getFormatDate($date): string
    {
        return date('c', $date);
    }

    /**
     * Преобразует массив страниц в строку XML формата sitemap.
     *
     * Страницы без `loc` или `lastmod` пропускаются.
     * Значения `loc` и `lastmod` экранируются через `htmlspecialchars` (ENT_XML1),
     * поэтому URL с `&` будут корректно представлены как `&amp;`.
     *
     * @param array<int, array{loc: string, lastmod: string}> $pages Список страниц.
     * @return string Готовая XML-строка sitemap.
     */
    public static function getXml(array $pages): string
    {
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($pages as $page) {
            if (empty($page['loc']) ||
                empty($page['lastmod'])) {
                continue;
            }

            $output .= '<url>';
            $output .= '<loc>' . htmlspecialchars($page['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>';
            $output .= '<lastmod>' . htmlspecialchars($page['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>';
            $output .= '</url>';
        }

        $output .= '</urlset>';

        return $output;
    }
}
