<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitDetailConfig;
use Uplab\Api\Utils\Config\Iblock\TraitIblockElementConfig;

class IblockFilterConfig extends BaseConfig
{
    use TraitDetailConfig;
    use TraitIblockElementConfig;

    /**
     * Получить конфигурацию для списка элемента
     * @param array|null $arBody
     * @return static
     */
    public static function create(?array $arBody): static
    {
        $config = parent::create($arBody);

        //какие свойства нужны
        $config->setPropertyCode($arBody['propertyCode'] ?? null);

        //additional filter
        $config->setFilter($arBody['filter'] ?? null);

        //additional select
        $config->setSelect($arBody['select'] ?? null);

        $config->setParentSectionId($arBody['parentSectionId'] ?? null);
        $config->setParentSectionCode($arBody['parentSectionCode'] ?? null);

        return $config;
    }
}