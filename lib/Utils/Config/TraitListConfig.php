<?php

namespace Uplab\Api\Utils\Config;

trait TraitListConfig
{
    use TraitPaginationConfig;
    use TraitDetailConfig;

    protected ?array $order = null;

    public function setOrder(?array $order): static
    {
        BaseConfig::assertOrder($order);

        $this->order = $order;
        return $this;
    }

    public function getOrder(): ?array
    {
        return $this->order;
    }
}
