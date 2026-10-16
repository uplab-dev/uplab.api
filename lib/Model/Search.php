<?php

namespace Uplab\Api\Model;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use CPageOption;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Model\ControllerAction\BaseAction;
use Uplab\Api\Model\Iblock\IblockHelper;
use Uplab\Api\Model\Content\ContentTable;
use Uplab\Api\Model\Iblock\PageService;
use Uplab\Api\Exception\ApiException;
use Uplab\Api\Utils\Config\BaseConfig;

class Search extends BaseAction
{
    /**
     * Тип отображение элемента поиска - полный. Используется на странице поиска.
     */
    public const SEARCH_TYPE_FULL = 'full';
    /**
     * Тип отображение элемента поиска - короткий. Используется в шапке сайта.
     */
    public const SEARCH_TYPE_SHORT = 'short';
    /**
     * Тип сортировки поиск по-умолчанию
     */
    public const SORT_TYPE_RANK = 'rank';
    /**
     * Тип сортировки поиск дате
     */
    public const SORT_TYPE_DATE = 'date';

    /**
     * Контентные данные на странице.
     * @var array
     */
    protected array $content = [];
    protected array $config = [];
    protected array $staticPages = [];

    /**
     * Id инфоблока "Страницы".
     * @var int
     */
    private int $pagesIblockId;

    public function __construct()
    {
        Loader::includeModule('iblock');
        $this->content = ContentTable::getPageData('search');
    }

    /**
     * Возвращает результат поиска для контроллера
     * @param array $config
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getAction(array $config = []): Json
    {
        $result = new \Bitrix\Main\Engine\Response\Json();

        if (!Loader::includeModule('search')) {
            return $this->setErrorResponse(
                $result,
                HttpStatus::FAILED_DEPENDENCY->value,
                Loc::getMessage('UPLAB_API.ERROR_LOAD_MODULE', ['#NAME#' => 'search'])
            );
        }

        $result->setData(
            $this->getData($config)
        );

        return $result;
    }

    /**
     * Максимальное значение `limit` для поиска.
     *
     * Точка расширения: у поиска нет объекта конфига, поэтому лимит меняется переопределением
     * этого метода в наследнике модели (наследник подключается через
     * {@see \Uplab\Api\Controller\SearchController::getModelClass()}).
     *
     * @return int
     */
    protected function getMaxLimit(): int
    {
        return BaseConfig::MAX_LIMIT;
    }

    /**
     * Максимальное значение `offset` для поиска.
     * @return int
     */
    protected function getMaxOffset(): int
    {
        return BaseConfig::MAX_OFFSET;
    }

    /**
     * Возвращает данные для поиска
     * @throws LoaderException
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getData(array $config = []): array
    {
        $this->config = $config;

        $limit = (int)($config['limit'] ?? 10);
        $offset = (int)($config['offset'] ?? 0);
        if ($limit < 1 || $limit > $this->getMaxLimit()) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_LIMIT_RANGE', ['#MAX#' => $this->getMaxLimit()]),
                HttpStatus::BAD_REQUEST
            );
        }
        if ($offset < 0 || $offset > $this->getMaxOffset()) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_OFFSET_RANGE', ['#MAX#' => $this->getMaxOffset()]),
                HttpStatus::BAD_REQUEST
            );
        }
        BaseConfig::assertArrayComplexity($config);
        $page = intdiv($offset, $limit) + 1;
        $data = [];

        // Результаты поиска
        CPageOption::SetOptionString("main", "nav_page_in_session", "N");

        $aParamsEx = $this->getaParamsEx();
        $aSort = $this->getASort();
        $arParams = $this->getArParams();

        $cSearch = new \CSearch;
        $cSearch->Search(
            $arParams,
            $aSort,
            $aParamsEx
        );

        if ($cSearch->errorno > 0) {
            $data['message']['text'] = $cSearch->error ?? '';
        } else {
            $cSearch->NavStart($limit, false, $page);
            $countItems = $cSearch->NavRecordCount;
            $result = $cSearch->GetNext();
            if (!$result) {
                $aParamsEx['STEMMING'] = false;
                $cSearch = new \CSearch();
                $cSearch->Search(
                    $arParams,
                    $aSort,
                    $aParamsEx
                );
                if ($cSearch->errorno == 0) {
                    $cSearch->NavStart($limit, false, $page);
                    $countItems = $cSearch->NavRecordCount;
                    $result = $cSearch->GetNext();
                }
            }
            if ($countItems > 0) {
                $data['meta'] = ['limit' => $limit, 'offset' => $offset, 'total' => $countItems];
                //пагинация не ушла за пределы
                if (($page - 1) * $limit < $countItems) {
                    // Статические страницы из соответствующего ИБ
                    $this->staticPages = $this->getStaticPages();

                    while ($result) {
                        $data['items'][] = $this->prepareForOutput($result);
                        $result = $cSearch->GetNext();
                    }
                }
            } else {
                // Текст «ничего не найдено» берётся из статического контента
                // (HLBlock Content: UF_PAGE='search', UF_BLOCK='empty', UF_CODE='title'|'text').
                // Если там пусто — фолбэк на языковые сообщения модуля.
                $data['message'] = [
                    'title' => ($this->content['empty']['title'] ?? '') ?: (string)Loc::getMessage('UPLAB_API.SEARCH_EMPTY_TITLE'),
                    'text'  => ($this->content['empty']['text'] ?? '') ?: (string)Loc::getMessage('UPLAB_API.SEARCH_EMPTY_TEXT'),
                ];
            }
        }

        return $data;
    }

    /**
     * Возвращает список статических страниц
     */
    protected function getStaticPages(): array
    {
        $page = new PageService();
        $pages = $page->getStaticPages();
        $this->pagesIblockId = IblockHelper::getIblockId(PageService::IBLOCK_CODE);

        return $pages;
    }

    /**
     * @return array
     */
    protected function getaParamsEx(): array
    {
        return [];
    }

    /**
     * Сортировка
     * @return string[]
     */
    protected function getASort(): array
    {
        $sort = $this->config['sort'] ?? static::SORT_TYPE_RANK;

        if ($sort === static::SORT_TYPE_RANK) {
            $sorting = [
                'CUSTOM_RANK' => 'DESC',
                'RANK'        => 'DESC',
                'DATE_CHANGE' => 'DESC'
            ];
        } else {
            $sorting = [
                'DATE_CHANGE' => 'DESC',
                'CUSTOM_RANK' => 'DESC',
                'RANK'        => 'DESC'
            ];
        }

        if ($this->getTypeSearch() === static::SEARCH_TYPE_SHORT) {
            $sorting = ['TITLE_RANK' => 'DESC'] + $sorting;
        }

        return $sorting;
    }

    /**
     * Тип поиска
     * @return mixed|string
     */
    protected function getTypeSearch(): mixed
    {
        return $this->config['type'] ?? static::SEARCH_TYPE_FULL;
    }

    /**
     * Параметры поиска
     * @return array
     */
    protected function getArParams(): array
    {
        $arParams = [];
        $arParams['QUERY'] = $this->config['search'] ?? '';

        if ($siteId = $this->config['siteId'] ?? '') {
            $arParams['SITE_ID'] = $siteId;
        }

        return $arParams;
    }

    /**
     * Как данные уйдут FRONT.
     * @param array $result
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function prepareForOutput(array $result): array
    {
        // Заголовок
        $r = [
            'href'  => ($result['URL_WO_PARAMS'] ?? '') ?: $result['URL'],
            'title' => $result['TITLE_FORMATED'] ?? ''
        ];
        $r['text'] = $result['BODY_FORMATED'];

        //Удалим лишние HTML теги
        $r['title'] = str_replace('&lt;br&gt;', ' ', $r['title']);

        if (!empty($r['text'])) {
            $r['text'] = str_replace('&lt;br&gt;', ' ', $r['text']);
        }

        // Статическая страница
        if ($result['MODULE_ID'] === 'iblock') {
            $iblockId = (int)($result['PARAM2'] ?? 0);
            if ($iblockId == $this->pagesIblockId && $page = $this->staticPages[$result['ITEM_ID']] ?? []) {
                unset($r['text']);//Там текст для поиска, поэтому скроем его
                if ($val = $page['LINK'] ?? '') {
                    $r['href'] = $val;
                }
            } elseif (
                $this->getTypeSearch() !== static::SEARCH_TYPE_SHORT &&
                $label = IblockHelper::getSearchName($iblockId)) {
                $r['tags'] = [$label];
            }
        }

        return $r;
    }
}
