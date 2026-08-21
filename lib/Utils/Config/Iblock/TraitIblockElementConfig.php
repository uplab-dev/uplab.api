<?php

namespace Uplab\Api\Utils\Config\Iblock;

use Uplab\Api\Exception\ApiException;
use Uplab\Api\Utils\Config\BaseConfig;

trait TraitIblockElementConfig
{
    protected ?array $propertyCode = null;
    protected bool $hideDisplayValues = false;
    protected bool $showPrefixFieldData = false;
    protected ?string $activeDateFormat = null;
    protected ?string $activeToDateFormat = null;
    protected ?int $parentSectionId = null;
    protected ?string $parentSectionCode = null;

    /**
     * Какие свойства свойств выводить. ['PROPERTY_CODE_1'=>['PROPERTY_CODE_1_1','PROPERTY_CODE_1_2']]
     * @var array|null
     */
    protected ?array $getPropertiesProperty = null;

    public function getPropertyCode(): ?array
    {
        return $this->propertyCode;
    }

    public function setPropertyCode(?array $propertyCode): static
    {
        if ($propertyCode !== null) {
            if (count($propertyCode) > BaseConfig::MAX_SELECT_FIELDS) {
                throw new ApiException('propertyCode contains too many fields', 400);
            }
            foreach ($propertyCode as $code) {
                if (!is_string($code) || $code === '' || strlen($code) > 128) {
                    throw new ApiException('propertyCode contains an invalid field', 400);
                }
            }
        }
        $this->propertyCode = $propertyCode;
        return $this;
    }

    public function setActiveDateFormat(?string $activeDateFormat): static
    {
        $this->activeDateFormat = $activeDateFormat;
        return $this;
    }

    /**
     * Формат даты для вывода
     * @return string|null
     */
    public function getActiveDateFormat(): ?string
    {
        return $this->activeDateFormat;
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

    public function setActiveToDateFormat(?string $activeToDateFormat): static
    {
        $this->activeToDateFormat = $activeToDateFormat;
        return $this;
    }

    public function getActiveToDateFormat(): ?string
    {
        return $this->activeToDateFormat;
    }

    public function setHideDisplayValues(bool $hideDisplayValues = false): static
    {
        $this->hideDisplayValues = $hideDisplayValues;
        return $this;
    }

    public function isHideDisplayValues(): bool
    {
        return $this->hideDisplayValues;
    }

    public function setShowPrefixFieldData(bool $showPrefixFieldData = false): static
    {
        $this->showPrefixFieldData = $showPrefixFieldData;
        return $this;
    }

    public function isShowPrefixFieldData(): bool
    {
        return $this->showPrefixFieldData;
    }

    public function setPropertiesProperty(?array $getPropertiesProperty): static
    {
        if ($getPropertiesProperty !== null) {
            BaseConfig::assertArrayComplexity($getPropertiesProperty);
        }
        $this->getPropertiesProperty = $getPropertiesProperty;
        return $this;
    }

    public function getPropertiesProperty(): ?array
    {
        return $this->getPropertiesProperty;
    }
}
