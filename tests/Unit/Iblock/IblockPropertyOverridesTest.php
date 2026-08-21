<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;

/**
 * Точечная настройка под конкретное свойство конкретного инфоблока:
 * сортировка, формат значений и фильтр выборки.
 */
class IblockPropertyOverridesTest extends TestCase
{
    public function testDefaultOrdersPassedToQueries(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];
        $property->propertyValueRows['CATALOG'] = [['PROPERTY_CATALOG_VALUE' => 5, 'CNT' => 1]];
        $property->sections = [['ID' => 5, 'NAME' => 'Ноутбуки']];

        $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);
        $property->callGetPropertyValues([
            'ID'             => 2,
            'CODE'           => 'CATALOG',
            'PROPERTY_TYPE'  => 'G',
            'LINK_IBLOCK_ID' => 21,
        ]);

        $this->assertSame(['SORT' => 'ASC', 'VALUE' => 'ASC'], $property->lastCall('fetchEnumValues')[3]);
        $this->assertSame(['SORT' => 'ASC', 'NAME' => 'ASC'], $property->lastCall('fetchSections')[2]);
    }

    /**
     * Сортировка меняется только для одного свойства одного инфоблока.
     */
    public function testOrderOverriddenForSinglePropertyOfSingleIblock(): void
    {
        $make = function (int $iblockId): FakeIblockProperty {
            $property = new class($iblockId) extends FakeIblockProperty {
                protected function getEnumOrder(array $arProperty): array
                {
                    if ($this->getIblockId() === 17 && $arProperty['CODE'] === 'COLOR') {
                        return ['VALUE' => 'DESC'];
                    }

                    return parent::getEnumOrder($arProperty);
                }
            };
            $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
            $property->propertyValueRows['SIZE'] = [['PROPERTY_SIZE_ENUM_ID' => 13]];
            $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

            return $property;
        };

        $target = $make(17);
        $target->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);
        $this->assertSame(['VALUE' => 'DESC'], $target->lastCall('fetchEnumValues')[3]);

        $otherProperty = $make(17);
        $otherProperty->callGetPropertyValues(['ID' => 2, 'CODE' => 'SIZE', 'PROPERTY_TYPE' => 'L']);
        $this->assertSame(['SORT' => 'ASC', 'VALUE' => 'ASC'], $otherProperty->lastCall('fetchEnumValues')[3]);

        $otherIblock = $make(21);
        $otherIblock->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);
        $this->assertSame(['SORT' => 'ASC', 'VALUE' => 'ASC'], $otherIblock->lastCall('fetchEnumValues')[3]);
    }

    /**
     * Формат значений дополняется без копирования выборки.
     */
    public function testValueFormatExtendedForSingleProperty(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function buildEnumItems(array $rows, array $arProperty): array
            {
                $items = parent::buildEnumItems($rows, $arProperty);
                if ($arProperty['CODE'] !== 'COLOR') {
                    return $items;
                }

                foreach ($items as &$item) {
                    $item['hex'] = '#' . strtoupper(substr(md5((string)$item['value']), 0, 6));
                }

                return $items;
            }
        };
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $values = $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame(12, $values[0]['value']);
        $this->assertSame('Красный', $values[0]['text']);
        $this->assertArrayHasKey('hex', $values[0]);
    }

    /**
     * Фильтр выборки правится для конкретного свойства через контекст currentProperty.
     */
    public function testElementsFilterOverriddenForSingleProperty(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function buildElementsFilter(array $additionalFilter = []): array
            {
                $filter = parent::buildElementsFilter($additionalFilter);
                if (($this->getCurrentProperty()['CODE'] ?? '') === 'COLOR') {
                    $filter['!PROPERTY_HIDE_VALUE'] = false;
                }

                return $filter;
            }
        };
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->propertyValueRows['SIZE'] = [['PROPERTY_SIZE_ENUM_ID' => 13]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);
        $colorFilter = $property->lastCall('fetchPropertyValueRows')[2];

        $property->callGetPropertyValues(['ID' => 2, 'CODE' => 'SIZE', 'PROPERTY_TYPE' => 'L']);
        $sizeFilter = $property->lastCall('fetchPropertyValueRows')[2];

        $this->assertArrayHasKey('!PROPERTY_HIDE_VALUE', $colorFilter);
        $this->assertArrayNotHasKey('!PROPERTY_HIDE_VALUE', $sizeFilter);
        $this->assertSame('Y', $colorFilter['CHECK_PERMISSIONS'], 'Защитные ключи остаются на месте');
    }

    /**
     * Контекст живёт только на время работы резолвера: вне него — null,
     * иначе фильтры других выборок начали бы «залипать» на прошлом свойстве.
     */
    public function testCurrentPropertyIsResetAfterResolver(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            public array $seenInsideResolver = [];

            protected function getEnumValues(array $arProperty, array $customFilter): array
            {
                $this->seenInsideResolver[] = $this->getCurrentProperty()['CODE'] ?? null;

                return parent::getEnumValues($arProperty, $customFilter);
            }

            public function currentPropertyOutside(): ?array
            {
                return $this->getCurrentProperty();
            }
        };
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame(['COLOR'], $property->seenInsideResolver);
        $this->assertNull($property->currentPropertyOutside());
    }

    /**
     * Контекст сбрасывается и когда резолвер упал с исключением.
     */
    public function testCurrentPropertyIsResetAfterResolverException(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            public function currentPropertyOutside(): ?array
            {
                return $this->getCurrentProperty();
            }
        };
        $property->failingResolvers = ['getEnumValues' => 'boom'];

        $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertNull($property->currentPropertyOutside());
        $this->assertSame([['code' => 'COLOR', 'message' => 'boom']], $property->loggedErrors);
    }

    public function testDirectoryOrderAndFormatOverridable(): void
    {
        $property = new class(17, 0, 'ru') extends FakeIblockProperty {
            protected function getDirectoryOrder(array $arProperty): array
            {
                return ['UF_NAME' => 'DESC'];
            }

            protected function buildDirectoryItems(array $rows, array $nameFields, array $arProperty = []): array
            {
                $items = parent::buildDirectoryItems($rows, $nameFields, $arProperty);
                foreach ($items as &$item) {
                    $item['code'] = $arProperty['CODE'] ?? null;
                }

                return $items;
            }
        };
        $property->highloadblockId = 3;
        $property->propertyValueRows['BRAND'] = [['PROPERTY_BRAND_VALUE' => 'lenovo', 'CNT' => 1]];
        $property->hlblockFields = ['UF_NAME' => ['FIELD_NAME' => 'UF_NAME']];
        $property->directoryItems = [['ID' => 1, 'UF_XML_ID' => 'lenovo', 'UF_NAME' => 'Леново']];

        $values = $property->callGetPropertyValues([
            'ID'                 => 500,
            'CODE'               => 'BRAND',
            'PROPERTY_TYPE'      => 'S',
            'USER_TYPE'          => 'directory',
            'USER_TYPE_SETTINGS' => ['TABLE_NAME' => 'b_brands'],
        ]);

        $this->assertSame(['UF_NAME' => 'DESC'], $property->lastCall('fetchDirectoryItems')[4]);
        $this->assertSame('BRAND', $values[0]['code']);
        $this->assertSame('Леново', $values[0]['text']);
    }
}
