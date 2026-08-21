<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Uplab\Api\Admin\Docs;
use Uplab\Api\Security\AccessManager;

const ADMIN_MODULE_NAME = 'uplab.api';

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @global CMain $APPLICATION */

if (!Loader::includeModule(ADMIN_MODULE_NAME) || !AccessManager::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$docKey = 'overview';
$pages = Docs::pages();
$APPLICATION->SetTitle('API Документация — ' . ($pages[$docKey]['title'] ?? ''));

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

Docs::render($docKey);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
