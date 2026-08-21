<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Uplab\Api\Model\Content\ContentTable;
use Uplab\Api\Utils\Config\BaseConfig;

class ContentPageController extends BaseController
{
    /**
     * End-point для списка переменных страницы из Content
     * @param string $pageCode
     * @return Json
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function getAction(string $pageCode): Json
    {
        BaseConfig::assertCacheSegment($pageCode, 'page code');
        $result = new \Bitrix\Main\Engine\Response\Json();
        $result->setData($this->getData($pageCode, BaseConfig::create($this->getJsonRequestBody())));
        return $result;
    }

    /**
     * Возвращает данные у которых UF_PAGE = $pageCode.
     * @param string $pageCode
     * @param BaseConfig|mixed $config
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getData(string $pageCode, mixed $config = null): array
    {
        if ($data = ContentTable::getPageData($pageCode, $config?->getSiteId())) {
            return $data;
        }
        return [];
    }
}
