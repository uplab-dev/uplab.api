<?php

namespace Uplab\Api\Utils\Config;

use Uplab\Api\Exception\ApiException;

class BaseConfig
{
    public const MAX_LIMIT = 500;
    public const MAX_OFFSET = 100000;
    public const MAX_FILTER_ITEMS = 1000;
    public const MAX_FILTER_DEPTH = 5;
    public const MAX_SELECT_FIELDS = 100;
    public const MAX_ORDER_FIELDS = 10;

    /**
     * Направления сортировки, которые принимает ядро (см. CIBlock::_Order())
     */
    public const ORDER_DIRECTIONS = ['ASC', 'DESC', 'ASC,NULLS', 'DESC,NULLS', 'NULLS,ASC', 'NULLS,DESC'];

    protected ?string $siteId = null;
    protected ?string $languageId = null;

    /**
     * Получить конфигурацию по умолчанию, только siteId
     * @param array|null {
     *      siteId?: string
     *  }|null $arBody Массив параметров конфигурации
     * @return static
     */
    public static function create(?array $arBody): static
    {
        $config = new static();
        //Привязка к сайту
        $config->setSiteId($arBody['siteId'] ?? null);

        return $config;
    }

    public function setSiteId(?string $siteId): static
    {
        // Пустая строка означает «фильтр по сайту не применять» — так вёл себя модуль до
        // появления проверки, и обновление не должно менять выборки существующих клиентов.
        if ($siteId !== null && $siteId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,32}$/', $siteId)) {
            throw new ApiException('Invalid siteId', 400);
        }
        $this->siteId = $siteId ?? SITE_ID;
        return $this;
    }

    public function getSiteId(): ?string
    {
        return $this->siteId;
    }

    public function setLanguageId(?string $languageId): static
    {
        $this->languageId = $languageId;
        return $this;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public static function assertArrayComplexity(
        array $value,
        int $maxItems = self::MAX_FILTER_ITEMS,
        int $maxDepth = self::MAX_FILTER_DEPTH
    ): void {
        $items = 0;
        $walk = static function (array $part, int $depth) use (&$walk, &$items, $maxItems, $maxDepth): void {
            if ($depth > $maxDepth) {
                throw new ApiException('Request array is too deeply nested', 400);
            }
            foreach ($part as $key => $item) {
                $items++;
                if ($items > $maxItems) {
                    throw new ApiException('Request array contains too many items', 400);
                }
                if (is_string($key) && strlen($key) > 128) {
                    throw new ApiException('Request field name is too long', 400);
                }
                if (is_string($item) && strlen($item) > 4096) {
                    throw new ApiException('Request value is too long', 400);
                }
                if (is_array($item)) {
                    $walk($item, $depth + 1);
                }
            }
        };

        $walk($value, 1);
    }

    public static function assertOrder(?array $order): void
    {
        if ($order === null) {
            return;
        }

        if (count($order) > self::MAX_ORDER_FIELDS) {
            throw new ApiException('order contains too many fields', 400);
        }

        foreach ($order as $field => $direction) {
            if (!is_string($field) || $field === '' || strlen($field) > 128) {
                throw new ApiException('order contains an invalid field', 400);
            }
            if (!is_string($direction)
                || !in_array(strtoupper(str_replace(' ', '', $direction)), self::ORDER_DIRECTIONS, true)) {
                throw new ApiException('order direction must be one of: '
                    . implode(', ', self::ORDER_DIRECTIONS), 400);
            }
        }
    }

    public static function assertCacheSegment(string $value, string $name = 'resource code'): void
    {
        if ($value === '' || strlen($value) > 128 || str_contains($value, '..')
            || preg_match('~[\\\\/\x00-\x1F\x7F]~', $value)) {
            throw new ApiException("Invalid {$name}", 400);
        }
    }
}
