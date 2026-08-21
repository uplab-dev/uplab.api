<?php

namespace Uplab\Api\Security;

use Bitrix\Main\SystemException;
use Uplab\Api\Main;
use CMain;
use CUser;

/**
 * Единая точка проверки прав доступа к операциям модуля.
 *
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
class AccessManager
{
    /**
     * Минимальный уровень права модуля, достаточный для чтения данных.
     */
    private const READ_RIGHT = 'R';

    /**
     * Может ли текущий пользователь читать данные модуля.
     * Доступ есть у администратора и у авторизованных пользователей с правом
     * модуля не ниже "R" (модуль установлен с MODULE_GROUP_RIGHTS = 'Y').
     */
    public static function canRead(): bool
    {
        global $USER, $APPLICATION;

        if (!is_object($USER) || !$USER->IsAuthorized()) {
            return false;
        }

        if ($USER->IsAdmin()) {
            return true;
        }

        if (is_object($APPLICATION)) {
            return $APPLICATION->GetGroupRight(Main::MODULE_ID) >= self::READ_RIGHT;
        }

        return false;
    }

    /**
     * Бросает исключение, если у текущего пользователя нет прав на чтение.
     *
     * @throws SystemException
     */
    public static function checkRead(): void
    {
        if (!static::canRead()) {
            throw new SystemException('Недостаточно прав для выполнения операции.');
        }
    }
}
