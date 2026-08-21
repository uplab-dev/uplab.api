<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;

/**
 * Фильтры выборки значений умного фильтра: базовый (защитный) фильтр и фильтр запроса.
 */
class IblockPropertyFilterTest extends TestCase
{
    public function testBaseFilterAlwaysChecksPermissions(): void
    {
        $property = new FakeIblockProperty(17);

        $filter = $property->callBuildElementsFilter();

        $this->assertSame('Y', $filter['ACTIVE']);
        $this->assertSame(17, $filter['IBLOCK_ID']);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
        $this->assertSame(\CIBlockRights::PUBLIC_READ, $filter['MIN_PERMISSION']);
    }

    public function testUserFilterAddsConditions(): void
    {
        $property = new FakeIblockProperty(17);

        $filter = $property->callBuildElementsFilter(['PROPERTY_BRAND' => 42]);

        $this->assertSame(42, $filter['PROPERTY_BRAND']);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
    }

    /**
     * Защита от обхода прав через тело запроса: базовые ключи не переопределяются.
     */
    public function testUserFilterCannotOverrideProtectedKeys(): void
    {
        $property = new FakeIblockProperty(17);

        $filter = $property->callBuildElementsFilter([
            'CHECK_PERMISSIONS' => 'N',
            'MIN_PERMISSION'    => 'X',
            'ACTIVE'            => 'N',
            'IBLOCK_ID'         => 999,
        ]);

        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
        $this->assertSame(\CIBlockRights::PUBLIC_READ, $filter['MIN_PERMISSION']);
        $this->assertSame('Y', $filter['ACTIVE']);
        $this->assertSame(17, $filter['IBLOCK_ID']);
    }

    public function testSectionScopeAddedWhenSectionGiven(): void
    {
        $withoutSection = (new FakeIblockProperty(17))->callBuildElementsFilter();
        $withSection = (new FakeIblockProperty(17, 5))->callBuildElementsFilter();

        $this->assertArrayNotHasKey('SECTION_ID', $withoutSection);
        $this->assertSame(5, $withSection['SECTION_ID']);
        $this->assertSame('Y', $withSection['INCLUDE_SUBSECTIONS']);
    }

    /**
     * Условие по самому свойству исключается: иначе свойство сузило бы собственный
     * список значений и в фильтре остался бы только выбранный вариант.
     */
    public function testOwnConditionExcludedFromPropertyFilter(): void
    {
        $property = new FakeIblockProperty(17);

        $filter = $property->callBuildPropertyFilter(
            ['CODE' => 'COLOR'],
            ['PROPERTY_COLOR' => 12, 'PROPERTY_BRAND' => 42, 'SECTION_ID' => 3]
        );

        $this->assertArrayNotHasKey('PROPERTY_COLOR', $filter);
        $this->assertSame(42, $filter['PROPERTY_BRAND']);
        $this->assertSame(3, $filter['SECTION_ID']);
    }

    public function testEmptyRequestFilterGivesEmptyPropertyFilter(): void
    {
        $property = new FakeIblockProperty(17);

        $this->assertSame([], $property->callBuildPropertyFilter(['CODE' => 'COLOR'], null));
        $this->assertSame([], $property->callBuildPropertyFilter(['CODE' => 'COLOR'], []));
    }

    /**
     * Зафиксировано текущее поведение: исключение работает по точному совпадению ключа,
     * поэтому ключ с префиксом-оператором сузит список значений своего же свойства.
     * Так задокументировано в README — тест страхует от незаметного изменения.
     */
    public function testOperatorPrefixedKeyIsNotExcluded(): void
    {
        $property = new FakeIblockProperty(17);

        $filter = $property->callBuildPropertyFilter(
            ['CODE' => 'PRICE'],
            ['>=PROPERTY_PRICE' => 100]
        );

        $this->assertSame(['>=PROPERTY_PRICE' => 100], $filter);
    }
}
