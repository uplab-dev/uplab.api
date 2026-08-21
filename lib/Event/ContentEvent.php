<?php

namespace Uplab\Api\Event;

use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity;
use Bitrix\Main\Entity\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Uplab\Api\Model\Content\ContentTable;

class ContentEvent extends Entity\DataManager
{
    /**
     * Регистрирует необходимые обработчики событий
     *
     * @return void
     */
    public static function bindEvents(): void
    {
        $event = EventManager::getInstance();

        $hlBlockName = ContentTable::getHlBlockName();

        $event->addEventHandler('', $hlBlockName . 'OnAfterAdd', [static::class, 'eventHandlerOnAddUpdateDelete']);
        $event->addEventHandler('', $hlBlockName . 'OnAfterUpdate', [static::class, 'eventHandlerOnAddUpdateDelete']);
        $event->addEventHandler('', $hlBlockName . 'OnDelete', [static::class, 'eventHandlerOnDelete']);
    }

    /**
     * Обработчик добавления и изменения элемента HLBlock
     * @param \Bitrix\Main\Entity\Event $event
     *
     * @return void
     */
    public static function eventHandlerOnAddUpdateDelete(\Bitrix\Main\Entity\Event $event): void
    {
        $parameters = $event->getParameters();
        $ufPage = $parameters['fields']['UF_PAGE'] ?? null;

        static::clearTagCache($ufPage);
    }

    /**
     * Обработчик удаления элемента HLBlock
     * @param Event $event
     *
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function eventHandlerOnDelete(\Bitrix\Main\Entity\Event $event): void
    {
        $ufPage = null;
        $parameters = $event->getParameters();
        if (($elementId = $parameters['id']['ID'] ?? 0) && $item = ContentTable::query()
                ->where('ID', $elementId)
                ->addSelect('*')
                ->exec()
                ->fetch()) {
            $ufPage = $item['UF_PAGE'] ?? '';
        }
        static::clearTagCache($ufPage);
    }

    /**
     * Очистка тегированного кеша.
     * @param string|null $pageCode
     * @return void
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    protected static function clearTagCache(?string $pageCode = null): void
    {
        if ($pageCode) {
            //сбрасываем тегированный кеш страницы
            Application::getInstance()->getTaggedCache()->clearByTag(ContentTable::getPageTag($pageCode));
        }
        //сбрасываем весь тегированный кеш
        Application::getInstance()->getTaggedCache()->clearByTag(ContentTable::CACHE_TAG);
    }
}