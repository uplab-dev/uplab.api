<?php

namespace Uplab\Api\Utils\Config;

use Bitrix\Main\Localization\Loc;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;

trait TraitPaginationConfig
{
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $showTotal = true;

    public function setLimit(?int $limit): static
    {
        if ($limit !== null && ($limit < 1 || $limit > static::MAX_LIMIT)) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_LIMIT_RANGE', ['#MAX#' => static::MAX_LIMIT]),
                HttpStatus::BAD_REQUEST
            );
        }
        $this->limit = $limit;
        return $this;
    }

    public function setOffset(?int $offset): static
    {
        if ($offset !== null && ($offset < 0 || $offset > static::MAX_OFFSET)) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_OFFSET_RANGE', ['#MAX#' => static::MAX_OFFSET]),
                HttpStatus::BAD_REQUEST
            );
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
