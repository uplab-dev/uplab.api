<?php

namespace Uplab\Api\Event;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Uplab\Api\Enums\EventLogType;
use Uplab\Api\Main;
use Uplab\Api\Model\Hlblock\HlblockService;

class EventManager
{
    /**
     * Обработчики уже инициализированы?
     * @var bool
     */
    protected static bool $initEvents = false;

    public static function bindEvents(): void
    {
        if (static::$initEvents !== true) {
            $eventManager = \Bitrix\Main\EventManager::getInstance();

            // Инвалидация кеша контента должна работать и для мутаций через публичные
            // API-роуты (/api/content/admin/*, /api/content/bulk), а не только в админке.
            ContentEvent::bindEvents();

            if (\Bitrix\Main\Application::getInstance()->getContext()->getRequest()->isAdminSection()) {
                //Сброс тегированного кеша для web-form
                $eventManager->addEventHandler('main', 'OnBeforeProlog', [
                    static::class,
                    'handlerEventMainOnBeforeProlog'
                ]);

                if (Option::get(Main::MODULE_ID, 'SHOW_PAGE_CONTENT_TAB', 'N') === 'Y') {
                    $eventManager->addEventHandler('main', 'OnAdminIBlockElementEdit', [CCustomOETabs::class, 'OnInit']);
                }

                // Читаемые названия типов событий модуля в Журнале событий (и в фильтре журнала).
                $eventManager->addEventHandler('main', 'OnEventLogGetAuditTypes', [
                    static::class,
                    'onEventLogGetAuditTypes'
                ]);
            }

            static::$initEvents = true;
        }
    }

    /**
     * Регистрирует читаемые названия типов событий модуля для Журнала событий Bitrix.
     * @return array<string, string>
     */
    public static function onEventLogGetAuditTypes(): array
    {
        return EventLogType::auditTypes();
    }

    /**
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function handlerEventMainOnBeforeProlog(): void
    {
        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

        if ($request->isPost()) {
            $curPage = $GLOBALS['APPLICATION']->GetCurPage();

            if ((
                    $curPage === '/bitrix/admin/form_edit.php' && ($formId = (int)$request->get('ID'))
                )
                ||
                (
                    $curPage === '/bitrix/admin/form_field_edit.php' && ($formId = (int)$request->get('WEB_FORM_ID'))
                )
            ) {
                //сбросим тегированный кеш
                (Application::getInstance()->getTaggedCache())
                    ->clearByTag('form_' . $formId);
            } elseif (
                (
                    $curPage === '/bitrix/admin/highloadblock_row_edit.php' && ($hlBlockId = (int)$request->get('ENTITY_ID'))
                )
                ||
                (
                    $curPage === '/bitrix/admin/highloadblock_rows_list.php' && ($hlBlockId = (int)$request->get('ENTITY_ID'))
                )
            ) {
                //сбросим тегированный кеш
                (Application::getInstance()->getTaggedCache())
                    ->clearByTag(HlblockService::getHlBlockTag($hlBlockId));
            }
        }
    }
}