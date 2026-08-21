<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\LoaderException;
use Bitrix\Main\SystemException;
use Uplab\Api\Model\Iblock\IblockService;
use Uplab\Api\Utils\Config\Iblock\IblockDetailConfig;
use Uplab\Api\Utils\Config\Iblock\IblockDetailElementConfig;
use Uplab\Api\Utils\Config\Iblock\IblockDetailSectionConfig;
use Uplab\Api\Utils\Config\Iblock\IblockFilterConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListElementsConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListSectionsConfig;

class IblockController extends BaseController
{
    /**
     * End-point для получения информации о инфоблоке
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getIblockAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getIblockAction($iblockCode, IblockDetailConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения фильтра по элементам
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementFilterAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getFilterElementsAction($iblockCode, IblockFilterConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для списка элементов инфоблока
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementListAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getElementListAction($iblockCode, IblockListElementsConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения элемента инфоблока по его символьному коду
     * @param string $iblockCode - символьный код инфоблока
     * @param string $elementCode - символьный код элемента инфоблока
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getElementDetailByCodeAction(string $iblockCode, string $elementCode): Json
    {
        return $this->getModelClass()->getElementDetailByCodeAction($iblockCode, $elementCode, IblockDetailElementConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения элемента инфоблока по его id
     * @param string $iblockCode - символьный код инфоблока
     * @param int $elementId - id элемента
     * @return Json
     */
    public function getElementDetailByIdAction(string $iblockCode, int $elementId): Json
    {
        return $this->getModelClass()->getElementDetailByIdAction($iblockCode, $elementId, IblockDetailElementConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для списка разделов инфоблока
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getSectionListAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getSectionListAction($iblockCode, IblockListSectionsConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения раздела инфоблока по его символьному коду
     * @param string $iblockCode - символьный код инфоблока
     * @param string $sectionCode - символьный код раздела инфоблока
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getSectionDetailByCodeAction(string $iblockCode, string $sectionCode): Json
    {
        return $this->getModelClass()->getSectionDetailByCodeAction($iblockCode, $sectionCode, IblockDetailSectionConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения раздела инфоблока по его id
     * @param string $iblockCode - символьный код инфоблока
     * @param int $sectionId - id элемента
     * @return Json
     */
    public function getSectionDetailByIdAction(string $iblockCode, int $sectionId): Json
    {
        return $this->getModelClass()->getSectionDetailByIdAction($iblockCode, $sectionId, IblockDetailSectionConfig::create($this->getJsonRequestBody()));
    }

    /**
     * End-point для получения свойств инфоблока
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     * @throws ArgumentException
     * @throws ArgumentTypeException
     * @throws LoaderException
     * @throws SystemException
     */
    public function getIblockPropertiesAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getIblockPropertiesAction($iblockCode, IblockListConfig::create($this->getJsonRequestBody()));
    }

    /**
     * Модель для получения данных
     * @return IblockService
     */
    protected function getModelClass()
    {
        return new IblockService();
    }
}