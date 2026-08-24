<?php

namespace Uplab\Api\Controller;

use Uplab\Api\Model\Iblock\MenuService;
use Uplab\Api\Utils\Config\Iblock\MenuConfig;

class MenuController extends BaseController
{
    /**
     * Метод end-point для получения информации о меню в формате для FRONT.
     * @param string $menuCode - id инфоблока меню
     * @return \Bitrix\Main\Engine\Response\Json
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\SystemException
     */
    public function getAction(string $menuCode): \Bitrix\Main\Engine\Response\Json
    {
        return $this->getModelClass()->getMenu($menuCode, $this->createConfig($this->getMenuConfigClass()));
    }

    /**
     * Класс конфига для {@see static::getAction()}.
     *
     * Точка расширения: наследник контроллера может вернуть свой конфиг — например с поднятыми
     * лимитами запроса (см. README, раздел «Переопределение лимитов запросов»).
     *
     * @return string Класс-наследник {@see MenuConfig}
     */
    protected function getMenuConfigClass(): string
    {
        return MenuConfig::class;
    }

    /**
     * Модель для получения данных
     * @return MenuService
     */
    protected function getModelClass()
    {
        return new MenuService();
    }
}