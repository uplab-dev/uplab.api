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
        return $this->getModelClass()->getElementListAction($hlblockCode, HlblockListConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения фильтра по элементам
     * @param string $hlblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementFilterAction(string $hlblockCode): Json
    {
        return $this->getModelClass()->getFilterElementsAction($hlblockCode, HlblockListConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения полей HLBlock
     * @param string $hlblockCode - символьный код инфоблока
     * @return Json
     */
    public function getFieldsAction(string $hlblockCode): Json
    {
        return $this->getModelClass()->getFiedlsAction($hlblockCode, BaseConfig::create($this->getJsonRequestBody()));
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