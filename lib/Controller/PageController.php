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
        return $this->getModelClass()->getPageAction($code, $this->createConfig($this->getPageConfigClass()));
    }
    /**
     * Класс конфига для {@see static::getPageAction()}.
     *
     * Точка расширения: наследник контроллера может вернуть свой конфиг — например с поднятыми
     * лимитами запроса (см. README, раздел «Переопределение лимитов запросов»).
     *
     * @return string Класс-наследник {@see IblockDetailElementConfig}
     */
    protected function getPageConfigClass(): string
    {
        return IblockDetailElementConfig::class;
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