<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use Uplab\Api\Model\Iblock\IblockProperty;

/**
 * Тестовый дублёр: подменяет обращения к API Bitrix заранее заданными строками выборки
 * и запоминает, с какими аргументами их вызвали. Логика самого класса не переопределяется.
 */
class FakeIblockProperty extends IblockProperty
{
    /** Строки выборки значений свойства: код свойства => строки. */
    public array $propertyValueRows = [];

    /** Строки выборки значений свойства с «~»-полями: код свойства => строки. */
    public array $propertyValueRowsWithRaw = [];

    public array $sections = [];

    public array $elements = [];

    public array $enumValues = [];

    public array $hlblockFields = [];

    public array $directoryItems = [];

    public int $highloadblockId = 0;

    /** Журнал вызовов обёрток: [метод, аргументы]. */
    public array $calls = [];

    /** Пойманные логгером ошибки резолверов. */
    public array $loggedErrors = [];

    /** Резолверы, которые должны бросить исключение: имя метода => сообщение. */
    public array $failingResolvers = [];

    public function callGetPropertyValues(array $arProperty, ?array $requestFilter = null): array
    {
        return $this->getPropertyValues($arProperty, $requestFilter);
    }

    /**
     * Вариант с передачей по ссылке — как в самом getPropertyValues(): нужен тесту,
     * который проверяет, что CODE возвращается наружу в верхнем регистре.
     */
    public function callGetPropertyValuesByRef(array &$arProperty, ?array $requestFilter = null): array
    {
        return $this->getPropertyValues($arProperty, $requestFilter);
    }

    public function callBuildElementsFilter(array $additionalFilter = []): array
    {
        return $this->buildElementsFilter($additionalFilter);
    }

    public function callBuildPropertyFilter(array $arProperty, ?array $requestFilter = null): array
    {
        return $this->buildPropertyFilter($arProperty, $requestFilter);
    }

    public function callResolveDirectoryNameFields(array $fields): array
    {
        return $this->resolveDirectoryNameFields($fields);
    }

    public function callGetEnumPropertyItems(string $propertyCode, array $additionalFilter = []): array
    {
        return $this->getEnumPropertyItems($propertyCode, $additionalFilter);
    }

    public function callGetGroupPropertyItems(string $propertyCode, array $additionalFilter = []): array
    {
        return $this->getGroupPropertyItems($propertyCode, $additionalFilter);
    }

    public function getResolvers(): array
    {
        return $this->getValueResolvers();
    }

    public function getRegisteredIblockTags(): array
    {
        return $this->iblockTags;
    }

    public function getRegisteredCacheTags(): array
    {
        return $this->cacheTags;
    }

    protected function getValueResolvers(): array
    {
        $resolvers = parent::getValueResolvers();

        foreach (array_keys($this->failingResolvers) as $method) {
            $type = array_search($method, $resolvers, true);
            if ($type !== false) {
                $resolvers[$type] = 'failingResolver';
            }
        }

        return $resolvers;
    }

    protected function failingResolver(array $arProperty, array $customFilter): array
    {
        throw new \RuntimeException(reset($this->failingResolvers) ?: 'resolver failed');
    }

    protected function logPropertyError(array $arProperty, \Throwable $exception): void
    {
        $this->loggedErrors[] = [
            'code'    => $arProperty['CODE'] ?? null,
            'message' => $exception->getMessage(),
        ];
    }

    protected function fetchPropertyValueRows(string $propertyCode, array $filter): array
    {
        $this->calls[] = ['fetchPropertyValueRows', $propertyCode, $filter];

        return $this->propertyValueRows[$propertyCode] ?? [];
    }

    protected function fetchPropertyValueRowsWithRaw(string $propertyCode, array $filter): array
    {
        $this->calls[] = ['fetchPropertyValueRowsWithRaw', $propertyCode, $filter];

        return $this->propertyValueRowsWithRaw[$propertyCode] ?? [];
    }

    protected function fetchSections(array $filter, array $order = [], array $select = []): array
    {
        $this->calls[] = ['fetchSections', $filter, $order, $select];

        return $this->sections;
    }

    protected function fetchElements(array $filter, array $order = [], array $select = []): array
    {
        $this->calls[] = ['fetchElements', $filter, $order, $select];

        return $this->elements;
    }

    protected function fetchEnumValues(int $propertyId, array $enumIds, array $order = []): array
    {
        $this->calls[] = ['fetchEnumValues', $propertyId, $enumIds, $order];

        return $this->enumValues;
    }

    protected function fetchHlblockFields(int $hlblockID): array
    {
        $this->calls[] = ['fetchHlblockFields', $hlblockID];

        return $this->hlblockFields;
    }

    protected function fetchDirectoryItems(int $hlblockID, array $select, array $xmlIds, array $order = []): array
    {
        $this->calls[] = ['fetchDirectoryItems', $hlblockID, $select, $xmlIds, $order];

        return $this->directoryItems;
    }

    protected function getHighloadblockID($tableName)
    {
        $this->calls[] = ['getHighloadblockID', $tableName];

        return $this->highloadblockId;
    }

    /**
     * Аргументы последнего вызова обёртки.
     */
    public function lastCall(string $method): ?array
    {
        foreach (array_reverse($this->calls) as $call) {
            if ($call[0] === $method) {
                return $call;
            }
        }

        return null;
    }
}
