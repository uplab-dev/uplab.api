<?php

namespace Uplab\Api\Routing;

use Bitrix\Main\Routing\RoutingConfigurator;
use Uplab\Api\Controller\ContentController;
use Uplab\Api\Controller\ContentPageController;
use Uplab\Api\Controller\IblockController;
use Uplab\Api\Controller\PageController;
use Uplab\Api\Controller\WebFormController;
use Uplab\Api\Controller\HlblockController;
use Uplab\Api\Controller\MenuController;
use Uplab\Api\Controller\SearchController;
use Uplab\Api\Controller\SitemapController;

class Configurator
{
    /**
     * Установка роутинга для работы модуля
     * @param RoutingConfigurator $routes
     * @return void
     */
    public static function set(RoutingConfigurator $routes): void
    {
        //Запросы API
        $routes->prefix('api')->group(function (RoutingConfigurator $routes) {
            //End-point для модуля инфоблок (iblock)
            $routes->prefix('iblock')->group(function (RoutingConfigurator $routesIblock) {
                //Элемент инфоблока по символьному коду
                $routesIblock->any('{iblockCode}/element/code/{elementCode}', [IblockController::class, 'getElementDetailByCodeAction']);
                //Элемент инфоблока по id
                $routesIblock->any('{iblockCode}/element/id/{elementId}', [IblockController::class, 'getElementDetailByIdAction']);

                //Раздел инфоблока по символьному коду
                $routesIblock->any('{iblockCode}/section/code/{sectionCode}', [IblockController::class, 'getSectionDetailByCodeAction']);
                //Раздел инфоблока по id
                $routesIblock->any('{iblockCode}/section/id/{sectionId}', [IblockController::class, 'getSectionDetailByIdAction']);

                //Данные для фильтрации элементов
                $routesIblock->any('{iblockCode}/filter', [IblockController::class, 'getElementFilterAction']);

                //Список элементов инфоблока
                $routesIblock->any('{iblockCode}/elements', [IblockController::class, 'getElementListAction']);

                //Список разделов инфоблока
                $routesIblock->any('{iblockCode}/sections', [IblockController::class, 'getSectionListAction']);

                //Все свойства инфоблока
                $routesIblock->any('{iblockCode}/properties', [IblockController::class, 'getIblockPropertiesAction']);

                //Информация об инфоблоке
                $routesIblock->any('{iblockCode}', [IblockController::class, 'getIblockAction']);
            });

            //End-point для модуля Highload-блоки
            $routes->prefix('hlblock')->group(function (RoutingConfigurator $routesHlblock) {
                // Список элементов
                $routesHlblock->any('{hlblockCode}/elements', [HlblockController::class, 'getElementListAction']);

                // Данные для фильтрации элементов
                $routesHlblock->any('{hlblockCode}/filter', [HlblockController::class, 'getElementFilterAction']);

                // Данные для информации о полях HLBLock
                $routesHlblock->any('{hlblockCode}/fields', [HlblockController::class, 'getFieldsAction']);
            });

            // Страницы
            $routes->prefix('pages')->group(function (RoutingConfigurator $routesPages) {
                $routesPages->any('{code}', [PageController::class, 'getPageAction']);
            });

            //End-point для модуля web-формы (form)
            $routes->prefix('forms')->group(function (RoutingConfigurator $routesForms) {
                //Вывод всех ответов вопроса, удобно когда есть и radio и input[text]
                $routesForms->get('{code}/fields', [WebFormController::class, 'getWebFormFieldsAction']);
                //сохранение результата web-form
                $routesForms->post('{code}/add', [WebFormController::class, 'addNewResultAction']);
                //Вывод 1 вопрос - 1 ответ
                $routesForms->any('{code}', [WebFormController::class, 'getWebFormAction']);
            });

            // Статический контент
            $routes->any('content/page/{pageCode}', [ContentPageController::class, 'getAction']);
            $routes->post('content/admin/{pageCode}', [ContentController::class, 'getListAction']);
            $routes->delete('content/admin/{elementId}', [ContentController::class, 'deleteElementAction']);
            $routes->post('content/bulk', [ContentController::class, 'bulkAction']);

            // Меню
            $routes->any('menus/{menuCode}', [MenuController::class, 'getAction']);

            //Поиск
            $routes->any('search', [SearchController::class, 'getAction']);

            // Карта сайта
            /** @link SitemapController::getAction() */
            $routes->any('sitemap', [SitemapController::class, 'get']);

        });
    }
}