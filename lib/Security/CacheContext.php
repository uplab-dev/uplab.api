<?php

namespace Uplab\Api\Security;

use Bitrix\Main\Engine\CurrentUser;

/**
 * Сегмент доступа для кешей, чьи выборки зависят от прав текущего пользователя.
 * Повторяет штатный подход компонентов Bitrix с CACHE_GROUPS=Y.
 */
final class CacheContext
{
    /**
     * @return int[]
     */
    public static function getUserGroups(): array
    {
        // Группа 2 — «Все пользователи (в том числе неавторизованные)»: если групп нет
        // (CLI, агент, неавторизованный пользователь), кеш считается анонимным.
        $groups = array_values(array_unique(array_filter(
            array_map('intval', CurrentUser::get()->getUserGroups()),
            static fn(int $groupId): bool => $groupId > 0
        )));
        sort($groups, SORT_NUMERIC);

        return $groups ?: [2];
    }

    public static function getUserGroupsCacheId(): string
    {
        return implode(',', self::getUserGroups());
    }

    public static function getUserId(): int
    {
        return (int)CurrentUser::get()->getId();
    }

    /**
     * Сегмент доступа с точностью до пользователя — для выборок, где видимость данных
     * зависит не только от групп: расширенный режим прав инфоблока даёт права
     * персонально (код `U<ID>`), по отделу и на «создателя» (код `CR`).
     * Для неавторизованного пользователя совпадает с групповым сегментом.
     */
    public static function getUserCacheId(): string
    {
        return self::getUserGroupsCacheId() . '|u' . self::getUserId();
    }
}
