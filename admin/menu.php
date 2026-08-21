<?php

/**
 * Пункт левого меню админки. Bitrix автоматически сканирует
 * <module>/admin/menu.php у установленных модулей — регистрация событий не нужна.
 *
 * @global CMain $APPLICATION
 */

use Bitrix\Main\Loader;
use Uplab\Api\Admin\Docs;
use Uplab\Api\Main;
use Uplab\Api\Security\AccessManager;

if (!Loader::includeModule(Main::MODULE_ID) || !AccessManager::canRead()) {
    return null;
}

$items = [];
foreach (Docs::menuItems() as $item) {
    $items[] = [
        'text'  => $item['menu'],
        'title' => $item['title'],
        'url'   => $item['file'] . '?lang=' . LANGUAGE_ID,
    ];
}

return [
    'parent_menu' => 'global_menu_services',
    'section'     => 'uplab_api_docs',
    'sort'        => 100,
    'text'        => 'API Документация (uplab.api)',
    'title'       => 'Документация REST API модуля uplab.api',
    'icon'        => 'util_menu_icon',
    'page_icon'   => 'util_page_icon',
    'items_id'    => 'menu_uplab_api_docs',
    'items'       => $items,
];
