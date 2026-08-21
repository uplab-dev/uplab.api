<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitDetailConfig;

class IblockDetailElementConfig extends BaseConfig
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
        $config->setPropertyCode($arBody['propertyCode'] ?? []);

        //additional filter
        $config->setFilter($arBody['filter'] ?? []);

        //additional select
        $config->setSelect($arBody['select'] ?? []);

        //формат даты для вывода ACTIVE_FROM
        $config->setActiveDateFormat($arBody['activeDateFormat'] ?? null);
        //формат даты для вывода ACTIVE_TO
        $config->setActiveToDateFormat($arBody['activeToDateFormat'] ?? null);

        //какие свойства получать у связных элементов
        $config->setPropertiesProperty($arBody['GET_PROPERTIES_PROPERTY'] ?? []);

        //Не показывать PROPERTY DISPLAY_VALUES
        $config->setHideDisplayValues(($arBody['HIDE_DISPLAY_VALUES'] ?? 'N') === 'Y');

        //Не показывать SHOW_PREFIX_FIELD_DATA DISPLAY_VALUES
        $config->setShowPrefixFieldData(($arBody['SHOW_PREFIX_FIELD_DATA'] ?? 'N') === 'Y');

        //
        $config->setParentSectionId($arBody['parentSectionId'] ?? null);
        $config->setParentSectionCode($arBody['parentSectionCode'] ?? null);

        return $config;
    }
}