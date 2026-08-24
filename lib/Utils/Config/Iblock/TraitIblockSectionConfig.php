<?php

namespace Uplab\Api\Utils\Config\Iblock;

trait TraitIblockSectionConfig
{
    protected null|array|bool $bIncCnt = null;

    /**
     * Данные bIncCnt для CIBlockSections::GetList()
     * @param array|bool|null $bIncCnt
     * @return $this
     */
    public function setBIncCnt(null|array|bool $bIncCnt): static
    {
        if (is_array($bIncCnt)) {
            static::assertArrayComplexity($bIncCnt);
        }
        $this->bIncCnt = $bIncCnt;
        return $this;
    }

    /**
     * Данные bIncCnt для CIBlockSections::GetList()
     * @return array|bool|null
     */
    public function getBIncCnt(): null|array|bool
    {
        return $this->bIncCnt;
    }
}
