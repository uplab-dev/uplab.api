<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\TraitDetailConfig;

class MenuConfig extends BaseConfig
{
    use TraitDetailConfig;

    protected ?string $iblockType = null;
    protected ?array $order = null;

    /**
     * Получить конфигурацию для списка элемента
     * @param array|null $arBody
     * @return static
     */
    public static function create(?array $arBody): static
    {
        $config = parent::create($arBody);

        //iblockType
        $config->setIblockType($arBody['iblockType'] ?? null);

        //filter
        $config->setFilter($arBody['filter'] ?? []);
        //select
        $config->setSelect($arBody['select'] ?? []);
        //order
        $config->setOrder($arBody['order'] ?? []);

        return $config;
    }

    public function setIblockType(?string $iblockType): static
    {
        $this->iblockType = $iblockType;
        return $this;
    }

    public function getIblockType(): ?string
    {
        return $this->iblockType;
    }

    public function setOrder(?array $order): static
    {
        self::assertOrder($order);

        $this->order = $order;
        return $this;
    }

    public function getOrder(): ?array
    {
        return $this->order;
    }
}
