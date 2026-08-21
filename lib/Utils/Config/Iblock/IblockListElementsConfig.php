<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitListConfig;

class IblockListElementsConfig extends BaseConfig
{
    use TraitListConfig;
    use TraitIblockElementConfig;

    protected bool $showIpropertyValues = false;

    /**
     * Получить конфигурацию для списка элемента
     * @param {
     *      siteId?: string
     *      propertyCode?: string[],
     *      filter?: array,
     *      select?: string[],
     *      order?: string|null,
     *      SHOW_IPROPERTY_VALUES?: 'Y'|'N',
     *      HIDE_DISPLAY_VALUES?: 'Y'|'N',
     *      SHOW_PREFIX_FIELD_DATA?: 'Y'|'N',
     *      SHOW_TOTAL?: 'Y'|'N',
     *      GET_PROPERTIES_PROPERTY?: array|null,
     *      activeDateFormat?: string|null,
     *      parentSectionId?: int|null,
     *      parentSectionCode?: string|null,
     *      limit?: int,
     *      offset?: int
     *  }|null $arBody Массив параметров конфигурации
     * @return static
     */
    public static function create(?array $arBody): static
    {
        $config = parent::create($arBody);

        //какие свойства нужны
        $config->setPropertyCode($arBody['propertyCode'] ?? []);

        //filter
        $config->setFilter($arBody['filter'] ?? []);

        //select
        $config->setSelect($arBody['select'] ?? []);

        //sorting
        $config->setOrder($arBody['order'] ?? null);

        //Пагинация
        $config->setLimit($arBody['limit'] ?? 10);
        $config->setOffset($arBody['offset'] ?? 0);

        //Дополнительные параметры
        $config->setParentSectionId($arBody['parentSectionId'] ?? null);
        $config->setParentSectionCode($arBody['parentSectionCode'] ?? null);

        $config->setShowIpropertyValues(($arBody['SHOW_IPROPERTY_VALUES'] ?? 'N') === 'Y');

        //какие свойства получать у связных элементов
        $config->setPropertiesProperty($arBody['GET_PROPERTIES_PROPERTY'] ?? []);

        //Не показывать PROPERTY DISPLAY_VALUES
        $config->setHideDisplayValues(($arBody['HIDE_DISPLAY_VALUES'] ?? 'N') === 'Y');

        //Не показывать SHOW_PREFIX_FIELD_DATA DISPLAY_VALUES
        $config->setShowPrefixFieldData(($arBody['SHOW_PREFIX_FIELD_DATA'] ?? 'N') === 'Y');

        $config->setShowTotal(($arBody['SHOW_TOTAL'] ?? 'Y') === 'Y');

        //формат даты для вывода ACTIVE_FROM
        $config->setActiveDateFormat($arBody['activeDateFormat'] ?? null);
        //формат даты для вывода ACTIVE_TO
        $config->setActiveToDateFormat($arBody['activeToDateFormat'] ?? null);

        return $config;
    }

    /**
     * @param bool $showIpropertyValues
     * @return $this
     */
    public function setShowIpropertyValues(bool $showIpropertyValues = false): static
    {
        $this->showIpropertyValues = $showIpropertyValues;
        return $this;
    }

    public function isShowIpropertyValues(): bool
    {
        return $this->showIpropertyValues;
    }
}