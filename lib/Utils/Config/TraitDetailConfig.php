<?php

namespace Uplab\Api\Utils\Config;

use Uplab\Api\Exception\ApiException;

trait TraitDetailConfig
{
    protected ?array $filter = null;
    protected ?array $select = null;

    public function setFilter(?array $filter): static
    {
        if ($filter !== null) {
            BaseConfig::assertArrayComplexity($filter);
        }
        $this->filter = $filter;
        return $this;
    }

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function setSelect(?array $select): static
    {
        if ($select !== null) {
            if (count($select) > BaseConfig::MAX_SELECT_FIELDS) {
                throw new ApiException('select contains too many fields', 400);
            }
            foreach ($select as $field) {
                if (!is_string($field) || $field === '' || strlen($field) > 128) {
                    throw new ApiException('select contains an invalid field', 400);
                }
            }
        }
        $this->select = $select;
        return $this;
    }

    public function getSelect(): ?array
    {
        return $this->select;
    }
}
