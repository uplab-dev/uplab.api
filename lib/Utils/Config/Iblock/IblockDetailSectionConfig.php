<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitDetailConfig;
use Uplab\Api\Utils\Config\Iblock\TraitIblockSectionConfig;

class IblockDetailSectionConfig extends BaseConfig
{
    use TraitDetailConfig;
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

        //
        $config->setParentSectionId($arBody['parentSectionId'] ?? null);
        $config->setParentSectionCode($arBody['parentSectionCode'] ?? null);

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