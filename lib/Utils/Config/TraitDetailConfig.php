<?php

namespace Uplab\Api\Utils\Config;

use Bitrix\Main\Localization\Loc;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;

trait TraitDetailConfig
{
    protected ?array $filter = null;
    protected ?array $select = null;

    public function setFilter(?array $filter): static
    {
        if ($filter !== null) {
            static::assertArrayComplexity($filter);
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
            if (count($select) > static::MAX_SELECT_FIELDS) {
                throw new ApiException(
                    (string)Loc::getMessage(
                        'UPLAB_API.ERROR_SELECT_TOO_MANY_FIELDS',
                        ['#MAX#' => static::MAX_SELECT_FIELDS]
                    ),
                    HttpStatus::BAD_REQUEST
                );
            }
            foreach ($select as $field) {
                if (!is_string($field) || $field === '' || strlen($field) > static::MAX_FIELD_NAME_LENGTH) {
                    throw new ApiException(
                        (string)Loc::getMessage('UPLAB_API.ERROR_SELECT_INVALID_FIELD'),
                        HttpStatus::BAD_REQUEST
                    );
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
