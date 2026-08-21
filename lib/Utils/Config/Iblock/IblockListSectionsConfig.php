<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitListConfig;

class IblockListSectionsConfig extends BaseConfig
{
    use TraitListConfig;
    use TraitIblockSectionConfig;

    protected ?int $parentSectionId = null;
    protected ?string $parentSectionCode = null;

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

        $config->setBIncCnt($arBody['bIncCnt'] ?? null);

        //
        $config->setParentSectionId($arBody['parentSectionId'] ?? null);
        $config->setParentSectionCode($arBody['parentSectionCode'] ?? null);

        //Пагинация
        $config->setLimit($arBody['limit'] ?? 10);
        $config->setOffset($arBody['offset'] ?? 0);

        $config->setShowTotal(($arBody['SHOW_TOTAL'] ?? 'Y') === 'Y');

        return $config;
    }

    public function setParentSectionId(?int $parentSectionId): static
    {
        $this->parentSectionId = $parentSectionId;
        return $this;
    }

    public function setParentSectionCode(?string $parentSectionCode): static
    {
        $this->parentSectionCode = $parentSectionCode;
        return $this;
    }

    public function getParentSectionId(): ?int
    {
        return $this->parentSectionId;
    }

    public function getParentSectionCode(): ?string
    {
        return $this->parentSectionCode;
    }
}