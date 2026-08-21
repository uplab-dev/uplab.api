<?php

namespace Uplab\Api\Utils\Config\Hlblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitListConfig;

class HlblockListConfig extends BaseConfig
{
    use TraitListConfig;

    /**
     * Получить конфигурацию для списка элемента
     * @param array|null $arBody
     * @return static
     */
    public static function create(?array $arBody): static
    {
        $config = parent::create($arBody);

        //additional filter
        $config->setFilter($arBody['filter'] ?? []);

        //additional select
        $config->setSelect($arBody['select'] ?? []);

        //sorting
        $config->setOrder($arBody['order'] ?? null);

        //Пагинация
        $config->setLimit($arBody['limit'] ?? 10);
        $config->setOffset($arBody['offset'] ?? 0);

        $config->setShowTotal(($arBody['SHOW_TOTAL'] ?? 'Y') === 'Y');

        return $config;
    }
}