<?php

namespace Uplab\Api;

class Helper
{
    public static function isValidDate(string $dateString): bool
    {
        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $dateString)) {
            return false;
        }

        list($day, $month, $year) = explode('.', $dateString);

        if (!is_numeric($day) || !is_numeric($month) || !is_numeric($year)) {
            return false;
        }

        return checkdate((int)$month, (int)$day, (int)$year);
    }
}