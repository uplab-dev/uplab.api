<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\Engine\Response\Json;
use Uplab\Api\Model\Iblock\PageService;
use Uplab\Api\Utils\Config\Iblock\IblockDetailElementConfig;

class PageController extends BaseController
{
    /**
     * Список элементов инфоблока
     * @param string $code - символьный код страницы
     * @return Json
     */
    public function getPageAction(string $code): Json
    {
        return $this->getModelClass()->getPageAction($code, IblockDetailElementConfig::create($this->getJsonRequestBody()));
    }
    /**
     * Модель для получения данных
     * @return PageService
     */
    protected function getModelClass()
    {
        return new PageService();
    }
}