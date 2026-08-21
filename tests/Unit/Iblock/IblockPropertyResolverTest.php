<?php

declare(strict_types=1);

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Model\Iblock\IblockProperty;

/**
 * Диспетчеризация: карта «тип свойства => резолвер», расширение карты наследником,
 * поведение при неизвестном типе и при ошибке внутри резолвера.
 */
class IblockPropertyResolverTest extends TestCase
{
    public function testAllSupportedTypesHaveResolvers(): void
    {
        $resolvers = (new FakeIblockProperty(17))->getResolvers();

        $this->assertSame(['L', 'S', 'E', 'G'], array_keys($resolvers));
        foreach ($resolvers as $method) {
            $this->assertTrue(
                method_exists(IblockProperty::class, $method),
                'Резолвер ' . $method . ' объявлен в карте, но не реализован'
            );
        }
    }

    public function testUnknownTypeGivesNoValues(): void
    {
        $property = new FakeIblockProperty(17);

        $values = $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'FILE', 'PROPERTY_TYPE' => 'F']);

        $this->assertSame([], $values);
        $this->assertSame([], $property->calls, 'Для неподдерживаемого типа не должно быть запросов');
    }

    public function testPropertyCodeIsUppercasedForResolvers(): void
    {
        $property = new FakeIblockProperty(17);
        $property->propertyValueRows['COLOR'] = [
            ['PROPERTY_COLOR_ENUM_ID' => 12, 'PROPERTY_COLOR_VALUE' => 'Красный', 'CNT' => 3],
        ];
        $property->enumValues = [['ID' => 12, 'VALUE' => 'Красный']];

        $arProperty = ['ID' => 105, 'CODE' => 'color', 'PROPERTY_TYPE' => 'L'];
        $values = $property->callGetPropertyValuesByRef($arProperty);

        $this->assertNotSame([], $values);
        $this->assertSame('COLOR', $arProperty['CODE'], 'CODE должен вернуться в верхнем регистре');
    }

    /**
     * Ошибка в одном свойстве не должна ронять весь фильтр — она логируется,
     * а свойство отдаёт пустой набор значений.
     */
    public function testResolverExceptionIsLoggedAndSwallowed(): void
    {
        $property = new FakeIblockProperty(17);
        $property->failingResolvers = ['getEnumValues' => 'DB is down'];

        $values = $property->callGetPropertyValues(['ID' => 105, 'CODE' => 'COLOR', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame([], $values);
        $this->assertSame([['code' => 'COLOR', 'message' => 'DB is down']], $property->loggedErrors);
    }

    /**
     * Ошибка PHP (не исключение) в проектном резолвере тоже не должна ронять весь фильтр:
     * TypeError/Error логируется, свойство отдаёт пустой набор.
     */
    public function testResolverPhpErrorIsLoggedAndSwallowed(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getValueResolvers(): array
            {
                return parent::getValueResolvers() + ['F' => 'brokenResolver'];
            }

            protected function brokenResolver(array $arProperty, array $customFilter): array
            {
                /** @noinspection PhpUndefinedMethodInspection Умышленная ошибка: проверяем перехват Error */
                return $this->methodThatDoesNotExist();
            }
        };

        $values = $property->callGetPropertyValues(['ID' => 7, 'CODE' => 'DOC', 'PROPERTY_TYPE' => 'F']);

        $this->assertSame([], $values);
        $this->assertCount(1, $property->loggedErrors);
        $this->assertSame('DOC', $property->loggedErrors[0]['code']);
    }

    /**
     * Вложенный сбор значений другого свойства не должен затирать внешний контекст.
     */
    public function testNestedResolverRestoresOuterContext(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            public array $contextAfterNested = [];

            protected function getEnumValues(array $arProperty, array $customFilter): array
            {
                if ($arProperty['CODE'] === 'OUTER') {
                    // по пути собираем значения другого свойства
                    $this->callGetPropertyValues(['ID' => 2, 'CODE' => 'INNER', 'PROPERTY_TYPE' => 'L']);
                    $this->contextAfterNested[] = $this->getCurrentProperty()['CODE'] ?? null;
                }

                return parent::getEnumValues($arProperty, $customFilter);
            }
        };
        $property->propertyValueRows['OUTER'] = [['PROPERTY_OUTER_ENUM_ID' => 1]];
        $property->propertyValueRows['INNER'] = [['PROPERTY_INNER_ENUM_ID' => 2]];
        $property->enumValues = [['ID' => 1, 'VALUE' => 'Значение']];

        $property->callGetPropertyValues(['ID' => 1, 'CODE' => 'OUTER', 'PROPERTY_TYPE' => 'L']);

        $this->assertSame(['OUTER'], $property->contextAfterNested);
    }

    /**
     * Проектный USER_TYPE: наследник дописывает карту и реализует свой резолвер,
     * не копируя остальные ветки.
     */
    public function testChildCanRegisterOwnResolver(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getValueResolvers(): array
            {
                return parent::getValueResolvers() + ['F' => 'getFileValues'];
            }

            protected function getFileValues(array $arProperty, array $customFilter): array
            {
                return [['id' => 1, 'value' => 'custom', 'text' => 'Своё значение']];
            }
        };

        $values = $property->callGetPropertyValues(['ID' => 7, 'CODE' => 'DOC', 'PROPERTY_TYPE' => 'F']);

        $this->assertSame([['id' => 1, 'value' => 'custom', 'text' => 'Своё значение']], $values);
    }

    /**
     * Наследник может подменить штатный тип и опереться на базовое поведение через parent::.
     */
    public function testChildCanOverrideBuiltInResolver(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getStringValues(array $arProperty, array $customFilter): array
            {
                if (($arProperty['USER_TYPE'] ?? '') === 'my_type') {
                    return [['id' => 'my', 'value' => 'my', 'text' => 'Проектный тип']];
                }

                return parent::getStringValues($arProperty, $customFilter);
            }
        };
        $property->propertyValueRowsWithRaw['TITLE'] = [
            ['~PROPERTY_TITLE_VALUE' => 'Обычная строка'],
        ];

        $custom = $property->callGetPropertyValues([
            'ID'        => 9,
            'CODE'      => 'SIZE',
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'my_type',
        ]);
        $builtIn = $property->callGetPropertyValues([
            'ID'            => 10,
            'CODE'          => 'TITLE',
            'IBLOCK_ID'     => 17,
            'PROPERTY_TYPE' => 'S',
        ]);

        $this->assertSame([['id' => 'my', 'value' => 'my', 'text' => 'Проектный тип']], $custom);
        $this->assertSame([['id' => 10, 'value' => 'Обычная строка', 'text' => 'Обычная строка']], $builtIn);
    }

    /**
     * Резолвер, объявленный в наследнике как private, недоступен базовому классу.
     * Вызов дал бы Error (не Exception) и уронил бы весь эндпоинт — свойство должно
     * просто выпасть из фильтра.
     */
    public function testInaccessibleResolverDoesNotBreakFilter(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getValueResolvers(): array
            {
                return parent::getValueResolvers() + ['F' => 'privateResolver'];
            }

            private function privateResolver(array $arProperty, array $customFilter): array
            {
                return [['id' => 1, 'value' => 1, 'text' => 'Никогда не вызовется']];
            }
        };

        $values = $property->callGetPropertyValues(['ID' => 7, 'CODE' => 'DOC', 'PROPERTY_TYPE' => 'F']);

        $this->assertSame([], $values);
    }

    public function testMissingResolverMethodGivesNoValues(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getValueResolvers(): array
            {
                return ['F' => 'notImplementedResolver'];
            }
        };

        $this->assertSame([], $property->callGetPropertyValues(['ID' => 7, 'CODE' => 'DOC', 'PROPERTY_TYPE' => 'F']));
    }

    /**
     * Список типов для выборки свойств выводится из карты резолверов — рассинхрон,
     * из-за которого свойства типа «Список» выпадали из фильтра, стал невозможен.
     */
    public function testSupportedTypesComeFromResolverMap(): void
    {
        $property = new class(17) extends FakeIblockProperty {
            protected function getValueResolvers(): array
            {
                return ['L' => 'getEnumValues'];
            }
        };

        $this->assertSame(['L'], array_keys($property->getResolvers()));
    }
}
