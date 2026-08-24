<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Uplab\Api\Model\Hlblock\HlblockService;
use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\Hlblock\HlblockListConfig;

class HlblockController extends BaseController
{
    /**
     * Спислок элементов highload-блока
     * @param string $hlblockCode - название таблицы highload-блока
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws \Throwable
     */
    public function getElementListAction(string $hlblockCode): Json
    {
        return $this->getModelClass()->getElementListAction($hlblockCode, $this->createConfig($this->getElementListConfigClass()));
    }

    /**
     * End-point для получения фильтра по элементам
     * @param string $hlblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementFilterAction(string $hlblockCode): Json
    {
        return $this->getModelClass()->getFilterElementsAction($hlblockCode, $this->createConfig($this->getElementFilterConfigClass()));
    }

    /**
     * End-point для получения полей HLBlock
     * @param string $hlblockCode - символьный код инфоблока
     * @return Json
     */
    public function getFieldsAction(string $hlblockCode): Json
    {
        return $this->getModelClass()->getFiedlsAction($hlblockCode, $this->createConfig($this->getFieldsConfigClass()));
    }

    /**
     * Класс конфига для {@see static::getElementListAction()}.
     *
     * Точка расширения: наследник контроллера может вернуть свой конфиг — например с поднятыми
     * лимитами запроса (см. README, раздел «Переопределение лимитов запросов»).
     *
     * @return string Класс-наследник {@see HlblockListConfig}
     */
    protected function getElementListConfigClass(): string
    {
        return HlblockListConfig::class;
    }

    /**
     * Класс конфига для {@see static::getElementFilterAction()}.
     * @return string Класс-наследник {@see HlblockListConfig}
     */
    protected function getElementFilterConfigClass(): string
    {
        return HlblockListConfig::class;
    }

    /**
     * Класс конфига для {@see static::getFieldsAction()}: у эндпоинта только `siteId`.
     * @return string Класс-наследник {@see BaseConfig}
     */
    protected function getFieldsConfigClass(): string
    {
        return BaseConfig::class;
    }

    /**
     * Модель для получения данных
     * @return HlblockService
     */
    protected function getModelClass()
    {
        return new HlblockService();
    }
}