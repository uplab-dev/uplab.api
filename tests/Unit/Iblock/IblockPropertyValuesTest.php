<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;

/**
 * Сборка значений фильтра по каждому типу свойства.
 */
class IblockPropertyValuesTest extends TestCase
{
    public function testEnumValuesBuiltFromUsedEnumIds(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['COLOR'] = [
            ['PROPERTY_COLOR_ENUM_ID' => 12, 'PROPERTY_COLOR_VALUE' => 'Красный', 'CNT' => 3],
            ['PROPERTY_COLOR_ENUM_ID' => 13, 'PROPERTY_COLOR_VALUE' => 'Синий', 'CNT' => 1],
        ];
        $property->enumValues = [
            ['ID' => 12, 'VALUE' => 'Красный'],
            ['ID' => 13, 'VALUE' => 'Синий'],
        ];

        $values = $property->callGetPropertyValues(['ID' => 105, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame([
            ['id' => 12, 'value' => 12, 'text' => 'Красный'],
            ['id' => 13, 'value' => 13, 'text' => 'Синий'],
        ], $values);

        [, $propertyId, $enumIds] = $property->lastCall('fetchEnumValues');
        $this->assertSame(105, $propertyId, 'Перечисления запрашиваются по ID свойства');
        $this->assertSame([12, 13], $enumIds);
    }

    public function testEnumIdsAreDeduplicatedAndZeroesSkipped(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['COLOR'] = [
            ['PROPERTY_COLOR_ENUM_ID' => 12],
            ['PROPERTY_COLOR_ENUM_ID' => 12],
            ['PROPERTY_COLOR_ENUM_ID' => null],
            ['PROPERTY_COLOR_ENUM_ID' => 0],
            ['PROPERTY_COLOR_ENUM_ID' => 13],
        ];

        $this->assertSame([12, 13], $property->callGetEnumPropertyItems('COLOR'));
    }

    public function testEnumWithoutUsedValuesGivesNoValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $values = $property->callGetPropertyValues(['ID' => 105, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame([], $values);
        $this->assertNull(
            $property->lastCall('fetchEnumValues'),
            'Без использованных значений запрос перечислений не нужен'
        );
    }

    public function testRequestFilterReachesValueQuery(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $property->callGetPropertyValues(
            ['ID' => 105, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L'],
            ['PROPERTY_BRAND' => 42, 'PROPERTY_COLOR' => 12]
        );

        [, $code, $filter] = $property->lastCall('fetchPropertyValueRows');
        $this->assertSame('COLOR', $code);
        $this->assertSame(42, $filter['PROPERTY_BRAND'], 'Фильтр запроса должен доходить до выборки значений');
        $this->assertArrayNotHasKey('PROPERTY_COLOR', $filter, 'Своё условие свойство не сужает');
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
    }

    public function testSectionLinkValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['CATALOG'] = [
            ['PROPERTY_CATALOG_VALUE' => 5, 'CNT' => 2],
            ['PROPERTY_CATALOG_VALUE' => 7, 'CNT' => 1],
        ];
        $property->sections = [
            ['ID' => 5, 'NAME' => 'Ноутбуки'],
            ['ID' => 7, 'NAME' => 'Планшеты'],
        ];

        $values = $property->callGetPropertyValues([
            'ID'             => 200,
            'CODE'           => 'CATALOG',
            'PROPERTY_TYPE'  => 'G',
            'LINK_IBLOCK_ID' => 21,
        ]);

        $this->assertSame([
            ['id' => 5, 'value' => 5, 'text' => 'Ноутбуки'],
            ['id' => 7, 'value' => 7, 'text' => 'Планшеты'],
        ], $values);
        // Тег текущего инфоблока регистрирует getFilterProperties(); резолвер добавляет связанный.
        $this->assertSame([21], $property->getRegisteredIblockTags(), 'Связанный инфоблок должен попасть в теги кеша');

        [, $filter] = $property->lastCall('fetchSections');
        $this->assertSame(21, $filter['IBLOCK_ID']);
        $this->assertSame([5, 7], $filter['ID']);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS'], 'Названия недоступных разделов не должны утекать');
        $this->assertSame(\CIBlockRights::PUBLIC_READ, $filter['MIN_PERMISSION']);
    }

    public function testElementLinkValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['VENDOR'] = [['PROPERTY_VENDOR_VALUE' => 42, 'CNT' => 4]];
        $property->elements = [['ID' => 42, 'NAME' => 'Lenovo']];

        $values = $property->callGetPropertyValues([
            'ID'             => 201,
            'CODE'           => 'VENDOR',
            'PROPERTY_TYPE'  => 'E',
            'LINK_IBLOCK_ID' => 30,
        ]);

        $this->assertSame([['id' => 42, 'value' => 42, 'text' => 'Lenovo']], $values);
        $this->assertSame([30], $property->getRegisteredIblockTags());

        [, $filter] = $property->lastCall('fetchElements');
        $this->assertSame(30, $filter['IBLOCK_ID']);
        $this->assertSame([42], $filter['ID']);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS'], 'Названия недоступных элементов не должны утекать');
        $this->assertSame(\CIBlockRights::PUBLIC_READ, $filter['MIN_PERMISSION']);
    }

    public function testLinkPropertyWithoutLinkedIblockGivesNoValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['VENDOR'] = [['PROPERTY_VENDOR_VALUE' => 42, 'CNT' => 4]];

        $values = $property->callGetPropertyValues([
            'ID'            => 201,
            'CODE'          => 'VENDOR',
            'PROPERTY_TYPE' => 'E',
        ]);

        $this->assertSame([], $values);
        $this->assertSame([], $property->calls, 'Без LINK_IBLOCK_ID запросов быть не должно');
    }

    /**
     * Числовые свойства модуль из коробки не обрабатывает: тип N в карте резолверов
     * отсутствует, значения добавляет проект (см. ProjectCustomTypeExampleTest).
     */
    public function testNumberTypeIsNotSupportedOutOfTheBox(): void
    {
        $property = new FakeIblockProperty(17);

        $values = $property->callGetPropertyValues([
            'ID'            => 302,
            'CODE'          => 'WEIGHT',
            'PROPERTY_TYPE' => 'N',
        ]);

        $this->assertSame([], $values);
        $this->assertSame([], $property->calls, 'Для неподдерживаемого типа не должно быть запросов');
    }

    public function testPlainStringValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRowsWithRaw['MATERIAL'] = [
            ['~PROPERTY_MATERIAL_VALUE' => 'Хлопок'],
            ['~PROPERTY_MATERIAL_VALUE' => ''],
            ['~PROPERTY_MATERIAL_VALUE' => ['TEXT' => 'Шёлк', 'TYPE' => 'HTML']],
        ];

        $values = $property->callGetPropertyValues([
            'ID'            => 400,
            'CODE'          => 'MATERIAL',
            'IBLOCK_ID'     => 17,
            'PROPERTY_TYPE' => 'S',
        ]);

        $this->assertSame([
            ['id' => 400, 'value' => 'Хлопок', 'text' => 'Хлопок'],
            ['id' => 400, 'value' => 'Шёлк', 'text' => 'Шёлк'],
        ], $values, 'Пустые значения отбрасываются, HTML/TEXT разворачивается в TEXT');
    }

    public function testPlainStringValuesUseProtectedFilter(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRowsWithRaw['MATERIAL'] = [['~PROPERTY_MATERIAL_VALUE' => 'Хлопок']];

        $property->callGetPropertyValues(
            ['ID' => 400, 'CODE' => 'MATERIAL', 'IBLOCK_ID' => 17, 'PROPERTY_TYPE' => 'S'],
            ['CHECK_PERMISSIONS' => 'N']
        );

        [, , $filter] = $property->lastCall('fetchPropertyValueRowsWithRaw');
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS'], 'Проверку прав нельзя отключить через filter');
        $this->assertSame(17, $filter['IBLOCK_ID']);
    }

    public function testDirectoryValues(): void
    {
        $property = new FakeIblockProperty(17, 0, 'en');
        $property->highloadblockId = 3;
        $property->propertyValueRows['BRAND'] = [
            ['PROPERTY_BRAND_VALUE' => 'lenovo', 'CNT' => 2],
            ['PROPERTY_BRAND_VALUE' => 'asus', 'CNT' => 1],
        ];
        $property->hlblockFields = [
            'UF_NAME'    => ['FIELD_NAME' => 'UF_NAME', 'USER_TYPE_ID' => 'string'],
            'UF_NAME_EN' => ['FIELD_NAME' => 'UF_NAME_EN', 'USER_TYPE_ID' => 'string'],
            'UF_XML_ID'  => ['FIELD_NAME' => 'UF_XML_ID', 'USER_TYPE_ID' => 'string'],
        ];
        $property->directoryItems = [
            ['ID' => 1, 'UF_XML_ID' => 'lenovo', 'UF_NAME_EN' => 'Lenovo', 'UF_NAME' => 'Леново'],
            ['ID' => 2, 'UF_XML_ID' => 'asus', 'UF_NAME_EN' => '', 'UF_NAME' => 'Асус'],
            ['ID' => 3, 'UF_XML_ID' => 'acer', 'UF_NAME_EN' => '', 'UF_NAME' => ''],
        ];

        $values = $property->callGetPropertyValues([
            'ID'                 => 500,
            'CODE'               => 'BRAND',
            'PROPERTY_TYPE'      => 'S',
            'USER_TYPE'          => 'directory',
            'USER_TYPE_SETTINGS' => ['TABLE_NAME' => 'b_brands'],
        ]);

        $this->assertSame([
            ['id' => 1, 'value' => 'lenovo', 'text' => 'Lenovo'],
            ['id' => 2, 'value' => 'asus', 'text' => 'Асус'],
            ['id' => 3, 'value' => 'acer', 'text' => 'acer'],
        ], $values, 'Приоритет: локализованное имя → UF_NAME → UF_XML_ID');

        [, $hlblockId, $select, $xmlIds] = $property->lastCall('fetchDirectoryItems');
        $this->assertSame(3, $hlblockId);
        $this->assertSame(['lenovo', 'asus'], $xmlIds);
        $this->assertContains('UF_NAME_EN', $select);
        $this->assertContains('UF_XML_ID', $select);

        // Правка записей справочника в админке сбрасывает этот тег (Uplab\Api\Event\EventManager),
        // иначе фильтр отдавал бы старые названия ещё сутки
        $this->assertSame(['hlblock_id_3'], $property->getRegisteredCacheTags());
    }

    public function testDirectoryWithoutHlblockGivesNoValues(): void
    {
        $property = new FakeIblockProperty(17);
        $property->highloadblockId = 0;

        $values = $property->callGetPropertyValues([
            'ID'                 => 500,
            'CODE'               => 'BRAND',
            'PROPERTY_TYPE'      => 'S',
            'USER_TYPE'          => 'directory',
            'USER_TYPE_SETTINGS' => ['TABLE_NAME' => 'b_unknown'],
        ]);

        $this->assertSame([], $values);
    }

    public function testDirectoryNameFieldsPriority(): void
    {
        $fields = [
            'UF_NAME'    => ['FIELD_NAME' => 'UF_NAME'],
            'UF_NAME_EN' => ['FIELD_NAME' => 'UF_NAME_EN'],
        ];

        $withLang = new FakeIblockProperty(17, 0, 'en');
        $this->assertSame(['UF_NAME_EN', 'UF_NAME'], $withLang->callResolveDirectoryNameFields($fields));

        $withoutLangField = new FakeIblockProperty(17, 0, 'de');
        $this->assertSame(['UF_NAME'], $withoutLangField->callResolveDirectoryNameFields($fields));

        $withoutAny = new FakeIblockProperty(17, 0, 'de');
        $this->assertSame([], $withoutAny->callResolveDirectoryNameFields(['UF_XML_ID' => ['FIELD_NAME' => 'UF_XML_ID']]));
    }

    public function testGroupPropertyItemsKeepCounts(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['SIZE'] = [
            ['PROPERTY_SIZE_VALUE' => 'M', 'CNT' => 4],
            ['PROPERTY_SIZE_VALUE' => '', 'CNT' => 9],
            ['PROPERTY_SIZE_VALUE' => 'L', 'CNT' => 2],
        ];

        $this->assertSame(['M' => 4, 'L' => 2], $property->callGetGroupPropertyItems('SIZE'));
    }
}
