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
        return $this->getModelClass()->getIblockAction($iblockCode, $this->createConfig($this->getIblockConfigClass()));
    }

    /**
     * End-point для получения фильтра по элементам
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementFilterAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getFilterElementsAction($iblockCode, $this->createConfig($this->getElementFilterConfigClass()));
    }

    /**
     * End-point для списка элементов инфоблока
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getElementListAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getElementListAction($iblockCode, $this->createConfig($this->getElementListConfigClass()));
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
        return $this->getModelClass()->getElementDetailByCodeAction($iblockCode, $elementCode, $this->createConfig($this->getElementDetailConfigClass()));
    }

    /**
     * End-point для получения элемента инфоблока по его id
     * @param string $iblockCode - символьный код инфоблока
     * @param int $elementId - id элемента
     * @return Json
     */
    public function getElementDetailByIdAction(string $iblockCode, int $elementId): Json
    {
        return $this->getModelClass()->getElementDetailByIdAction($iblockCode, $elementId, $this->createConfig($this->getElementDetailConfigClass()));
    }

    /**
     * End-point для списка разделов инфоблока
     * @param string $iblockCode - символьный код инфоблока
     * @return Json
     */
    public function getSectionListAction(string $iblockCode): Json
    {
        return $this->getModelClass()->getSectionListAction($iblockCode, $this->createConfig($this->getSectionListConfigClass()));
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
        return $this->getModelClass()->getSectionDetailByCodeAction($iblockCode, $sectionCode, $this->createConfig($this->getSectionDetailConfigClass()));
    }

    /**
     * End-point для получения раздела инфоблока по его id
     * @param string $iblockCode - символьный код инфоблока
     * @param int $sectionId - id элемента
     * @return Json
     */
    public function getSectionDetailByIdAction(string $iblockCode, int $sectionId): Json
    {
        return $this->getModelClass()->getSectionDetailByIdAction($iblockCode, $sectionId, $this->createConfig($this->getSectionDetailConfigClass()));
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
        return $this->getModelClass()->getIblockPropertiesAction($iblockCode, $this->createConfig($this->getIblockPropertiesConfigClass()));
    }

    /**
     * Класс конфига для {@see static::getIblockAction()}.
     *
     * Точка расширения: наследник контроллера может вернуть свой конфиг — например с поднятыми
     * лимитами запроса (см. README, раздел «Переопределение лимитов запросов»).
     *
     * @return string Класс-наследник {@see IblockDetailConfig}
     */
    protected function getIblockConfigClass(): string
    {
        return IblockDetailConfig::class;
    }

    /**
     * Класс конфига для {@see static::getElementFilterAction()}.
     * @return string Класс-наследник {@see IblockFilterConfig}
     */
    protected function getElementFilterConfigClass(): string
    {
        return IblockFilterConfig::class;
    }

    /**
     * Класс конфига для {@see static::getElementListAction()}.
     * @return string Класс-наследник {@see IblockListElementsConfig}
     */
    protected function getElementListConfigClass(): string
    {
        return IblockListElementsConfig::class;
    }

    /**
     * Класс конфига для детальных элемента: {@see static::getElementDetailByCodeAction()}
     * и {@see static::getElementDetailByIdAction()}.
     * @return string Класс-наследник {@see IblockDetailElementConfig}
     */
    protected function getElementDetailConfigClass(): string
    {
        return IblockDetailElementConfig::class;
    }

    /**
     * Класс конфига для {@see static::getSectionListAction()}.
     * @return string Класс-наследник {@see IblockListSectionsConfig}
     */
    protected function getSectionListConfigClass(): string
    {
        return IblockListSectionsConfig::class;
    }

    /**
     * Класс конфига для детальных раздела: {@see static::getSectionDetailByCodeAction()}
     * и {@see static::getSectionDetailByIdAction()}.
     * @return string Класс-наследник {@see IblockDetailSectionConfig}
     */
    protected function getSectionDetailConfigClass(): string
    {
        return IblockDetailSectionConfig::class;
    }

    /**
     * Класс конфига для {@see static::getIblockPropertiesAction()}.
     * @return string Класс-наследник {@see IblockListConfig}
     */
    protected function getIblockPropertiesConfigClass(): string
    {
        return IblockListConfig::class;
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