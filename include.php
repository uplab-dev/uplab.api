<?php

use Bitrix\Main\Localization\Loc;


if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

Loc::loadMessages(__FILE__);

//Регистрация необходимых обработчиков проекта
\Uplab\Api\Event\EventManager::bindEvents();