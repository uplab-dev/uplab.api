<?php

namespace Uplab\Api\Utils\Config;

use Uplab\Api\Exception\ApiException;

trait TraitPaginationConfig
{
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $showTotal = true;

    public function setLimit(?int $limit): static
    {
        if ($limit !== null && ($limit < 1 || $limit > BaseConfig::MAX_LIMIT)) {
            throw new ApiException('limit must be between 1 and ' . BaseConfig::MAX_LIMIT, 400);
        }
        $this->limit = $limit;
        return $this;
    }

    public function setOffset(?int $offset): static
    {
        if ($offset !== null && ($offset < 0 || $offset > BaseConfig::MAX_OFFSET)) {
            throw new ApiException('offset must be between 0 and ' . BaseConfig::MAX_OFFSET, 400);
        }
        $this->offset = $offset;
        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function setShowTotal(bool $showTotal = true): static
    {
        $this->showTotal = $showTotal;
        return $this;
    }

    public function isShowTotal(): bool
    {
        return $this->showTotal;
    }
}
