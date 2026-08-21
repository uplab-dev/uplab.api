<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;

/**
 * Живой пример проектного расширения: типы свойств модуля asd.iblock
 * (SASDCheckboxNum и SASDSection), которые сам uplab.api не поддерживает.
 *
 * Класс ниже — то же, что копируется в проект (в README он приведён целиком);
 * тесты проверяют, что такой наследник действительно работает, поэтому пример
 * не может разойтись с кодом модуля.
 */
class ProjectCustomTypeExampleTest extends TestCase
{
    public function testCheckboxNumValuesFromUserTypeSettings(): void
    {
        $property = $this->makeProjectProperty();

        $values = $property->callGetPropertyValues([
            'ID'                 => 300,
            'CODE'               => 'FLAGS',
            'PROPERTY_TYPE'      => 'N',
            'USER_TYPE'          => 'SASDCheckboxNum',
            'USER_TYPE_SETTINGS' => ['VIEW' => [1 => 'Да', 2 => 'Нет']],
        ]);

        $this->assertSame([
            ['id' => 'FLAGS1', 'value' => 1, 'text' => 'Да'],
            ['id' => 'FLAGS2', 'value' => 2, 'text' => 'Нет'],
        ], $values);
    }

    public function testSectionValuesUseModuleHelpers(): void
    {
        $property = $this->makeProjectProperty();
        $property->propertyValueRows['SECT'] = [
            ['PROPERTY_SECT_VALUE' => 5, 'CNT' => 2],
            ['PROPERTY_SECT_VALUE' => 7, 'CNT' => 1],
        ];
        $property->projectSections = [
            ['ID' => 5, 'NAME' => 'Ноутбуки'],
            ['ID' => 7, 'NAME' => 'Планшеты'],
        ];

        $values = $property->callGetPropertyValues(
            ['ID' => 301, 'CODE' => 'SECT', 'PROPERTY_TYPE' => 'N', 'USER_TYPE' => 'SASDSection'],
            ['PROPERTY_BRAND' => 42]
        );

        $this->assertSame([
            ['id' => 5, 'value' => 5, 'text' => 'Ноутбуки'],
            ['id' => 7, 'value' => 7, 'text' => 'Планшеты'],
        ], $values);

        // Значения отобраны через штатный getGroupPropertyItems(): защитный фильтр и
        // переданный filter применяются, дублировать это в проекте не нужно.
        [, $code, $filter] = $property->lastCall('fetchPropertyValueRows');
        $this->assertSame('SECT', $code);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
        $this->assertSame(42, $filter['PROPERTY_BRAND']);
        $this->assertSame([5, 7], $property->projectSectionIdsAsked);
    }

    public function testUnknownUserTypeOfNumberGivesNoValues(): void
    {
        $property = $this->makeProjectProperty();

        $values = $property->callGetPropertyValues([
            'ID'            => 302,
            'CODE'          => 'WEIGHT',
            'PROPERTY_TYPE' => 'N',
        ]);

        $this->assertSame([], $values);
    }

    /**
     * Штатные типы продолжают работать — проект их не копировал.
     */
    public function testBuiltInTypesStillWork(): void
    {
        $property = $this->makeProjectProperty();
        $property->propertyValueRows['COLOR'] = [['PROPERTY_COLOR_ENUM_ID' => 12]];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $values = $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame([['id' => 12, 'value' => 12, 'text' => 'Красный']], $values);
    }

    /**
     * Строковое свойство со своим USER_TYPE: значения свойства остаются ключом фильтра,
     * а «text» проект подменяет своим справочником.
     */
    public function testStringPropertyWithOwnUserType(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getStringValues(array $arProperty, array $customFilter): array
            {
                if (($arProperty['USER_TYPE'] ?? '') !== 'ProjectColorPicker') {
                    return parent::getStringValues($arProperty, $customFilter);
                }

                $labels = ['#FF0000' => 'Красный', '#0000FF' => 'Синий'];

                $items = [];
                foreach (array_keys($this->getGroupPropertyItems($arProperty['CODE'], $customFilter)) as $value) {
                    $items[] = [
                        'id'    => $arProperty['ID'],
                        'value' => $value,
                        'text'  => $labels[$value] ?? $value,
                    ];
                }

                return $items;
            }
        };
        $property->propertyValueRows['COLOR_HEX'] = [
            ['PROPERTY_COLOR_HEX_VALUE' => '#FF0000', 'CNT' => 3],
            ['PROPERTY_COLOR_HEX_VALUE' => '#00FF00', 'CNT' => 1],
        ];
        $property->propertyValueRowsWithRaw['TITLE'] = [['~PROPERTY_TITLE_VALUE' => 'Обычная строка']];

        $custom = $property->callGetPropertyValues([
            'ID'            => 600,
            'CODE'          => 'COLOR_HEX',
            'IBLOCK_ID'     => 17,
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE'     => 'ProjectColorPicker',
        ]);

        $this->assertSame([
            ['id' => 600, 'value' => '#FF0000', 'text' => 'Красный'],
            ['id' => 600, 'value' => '#00FF00', 'text' => '#00FF00'],
        ], $custom, 'Неизвестный проекту цвет отдаётся как есть');

        // Штатное строковое свойство идёт прежним путём — parent::
        $builtIn = $property->callGetPropertyValues([
            'ID'            => 601,
            'CODE'          => 'TITLE',
            'IBLOCK_ID'     => 17,
            'PROPERTY_TYPE' => 'S',
        ]);
        $this->assertSame([['id' => 601, 'value' => 'Обычная строка', 'text' => 'Обычная строка']], $builtIn);
    }

    /**
     * Свойство-привязка к элементу (E): «text» из свойства связанного элемента,
     * своя сортировка. Выборка и отбор значений — штатные.
     */
    public function testElementLinkPropertyWithOwnTextAndOrder(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getElementsSelect(array $arProperty): array
            {
                if ($arProperty['CODE'] === 'VENDOR') {
                    return ['ID', 'NAME', 'PROPERTY_SHORT_TITLE'];
                }

                return parent::getElementsSelect($arProperty);
            }

            protected function getElementsOrder(array $arProperty): array
            {
                if ($arProperty['CODE'] === 'VENDOR') {
                    return ['PROPERTY_SHORT_TITLE' => 'ASC'];
                }

                return parent::getElementsOrder($arProperty);
            }

            protected function buildElementItems(array $rows, array $arProperty): array
            {
                if ($arProperty['CODE'] !== 'VENDOR') {
                    return parent::buildElementItems($rows, $arProperty);
                }

                $items = [];
                foreach ($rows as $row) {
                    $items[] = [
                        'id'    => $row['ID'],
                        'value' => $row['ID'],
                        // короткое название, если заполнено, иначе имя элемента
                        'text'  => (string)($row['PROPERTY_SHORT_TITLE_VALUE'] ?? '') !== ''
                            ? $row['PROPERTY_SHORT_TITLE_VALUE']
                            : $row['NAME'],
                    ];
                }

                return $items;
            }
        };
        $property->propertyValueRows['VENDOR'] = [
            ['PROPERTY_VENDOR_VALUE' => 42, 'CNT' => 5],
            ['PROPERTY_VENDOR_VALUE' => 43, 'CNT' => 2],
        ];
        $property->elements = [
            ['ID' => 42, 'NAME' => 'Lenovo Group Limited', 'PROPERTY_SHORT_TITLE_VALUE' => 'Lenovo'],
            ['ID' => 43, 'NAME' => 'ASUSTeK Computer Inc.', 'PROPERTY_SHORT_TITLE_VALUE' => ''],
        ];

        $values = $property->callGetPropertyValues([
            'ID'             => 700,
            'CODE'           => 'VENDOR',
            'PROPERTY_TYPE'  => 'E',
            'LINK_IBLOCK_ID' => 30,
        ]);

        $this->assertSame([
            ['id' => 42, 'value' => 42, 'text' => 'Lenovo'],
            ['id' => 43, 'value' => 43, 'text' => 'ASUSTeK Computer Inc.'],
        ], $values);

        [, $filter, $order, $select] = $property->lastCall('fetchElements');
        $this->assertSame(30, $filter['IBLOCK_ID'], 'Элементы берутся из связанного инфоблока');
        $this->assertSame([42, 43], $filter['ID']);
        $this->assertSame('Y', $filter['CHECK_PERMISSIONS']);
        $this->assertSame(['PROPERTY_SHORT_TITLE' => 'ASC'], $order);
        $this->assertContains('PROPERTY_SHORT_TITLE', $select);
        $this->assertSame([30], $property->getRegisteredIblockTags(), 'Тег связанного инфоблока зарегистрирован');
    }

    /**
     * Наследник из README (выборка разделов подменена, чтобы тест не требовал ядра Bitrix).
     */
    private function makeProjectProperty(): FakeIblockProperty
    {
        return new class(17) extends FakeIblockProperty {
            /** @var array<int, array> подменяет ответ SectionTable::getList() */
            public array $projectSections = [];

            /** @var array<int, int> ID, запрошенные у выборки разделов */
            public array $projectSectionIdsAsked = [];

            protected function getValueResolvers(): array
            {
                return parent::getValueResolvers() + ['N' => 'getAsdNumberValues'];
            }

            protected function getAsdNumberValues(array $arProperty, array $customFilter): array
            {
                return match ($arProperty['USER_TYPE'] ?? '') {
                    'SASDCheckboxNum' => $this->buildAsdCheckboxItems($arProperty),
                    'SASDSection'     => $this->getAsdSectionValues($arProperty, $customFilter),
                    default           => [],
                };
            }

            protected function buildAsdCheckboxItems(array $arProperty): array
            {
                $items = [];
                foreach ((array)($arProperty['USER_TYPE_SETTINGS']['VIEW'] ?? []) as $key => $label) {
                    $items[] = [
                        'id'    => $arProperty['CODE'] . $key,
                        'value' => $key,
                        'text'  => $label,
                    ];
                }

                return $items;
            }

            protected function getAsdSectionValues(array $arProperty, array $customFilter): array
            {
                $groupItems = $this->getGroupPropertyItems($arProperty['CODE'], $customFilter);
                if (!$groupItems) {
                    return [];
                }

                return $this->buildSectionItems(
                    $this->fetchAsdSections(array_keys($groupItems)),
                    $arProperty
                );
            }

            protected function fetchAsdSections(array $ids): array
            {
                $this->projectSectionIdsAsked = $ids;

                return $this->projectSections;
            }
        };
    }
}
