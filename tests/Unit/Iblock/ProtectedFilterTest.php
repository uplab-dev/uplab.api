<?php

namespace Uplab\Api\Tests\Unit\Iblock;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Model\Iblock\IblockHelper;

/**
 * Фильтр из запроса добавляет условия, но не управляет способом сборки запроса:
 * LOGIC, PERMISSIONS_BY и ключи документооборота снимали защитные условия ядра.
 */
final class ProtectedFilterTest extends TestCase
{
    public function testBaseKeysWinOverRequestFilter(): void
    {
        $filter = IblockHelper::mergeProtectedFilter(
            ['ACTIVE' => 'N', 'IBLOCK_ID' => 999, 'PROPERTY_BRAND' => 42],
            ['ACTIVE' => 'Y', 'IBLOCK_ID' => 17, 'CHECK_PERMISSIONS' => 'Y']
        );

        self::assertSame('Y', $filter['ACTIVE']);
        self::assertSame(17, $filter['IBLOCK_ID']);
        self::assertSame(42, $filter['PROPERTY_BRAND'], 'Обычные условия запроса сохраняются.');
    }

    /**
     * LOGIC=OR превращал ACTIVE/IBLOCK_ID/IBLOCK_LID в альтернативы — снималась
     * привязка к инфоблоку, сайту и активности.
     */
    public function testTopLevelLogicIsRemoved(): void
    {
        $filter = IblockHelper::mergeProtectedFilter(
            ['LOGIC' => 'OR', 'ID' => 123],
            ['ACTIVE' => 'Y', 'IBLOCK_ID' => 17]
        );

        self::assertArrayNotHasKey('LOGIC', $filter);
        self::assertSame(123, $filter['ID']);
    }

    /**
     * PERMISSIONS_BY заставлял ядро проверять права от имени другого пользователя.
     */
    public function testPermissionKeysAreRemoved(): void
    {
        $filter = IblockHelper::mergeProtectedFilter(
            ['PERMISSIONS_BY' => 1, 'CHECK_PERMISSIONS' => 'N', 'MIN_PERMISSION' => 'X'],
            ['CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'R']
        );

        self::assertArrayNotHasKey('PERMISSIONS_BY', $filter);
        self::assertSame('Y', $filter['CHECK_PERMISSIONS']);
        self::assertSame('R', $filter['MIN_PERMISSION']);
    }

    /**
     * SHOW_HISTORY снимал условие WF_STATUS_ID=1, отдавая черновики и историю.
     */
    public function testWorkflowKeysAreRemoved(): void
    {
        $filter = IblockHelper::mergeProtectedFilter(
            ['SHOW_HISTORY' => 'Y', 'SHOW_NEW' => 'Y', 'SHOW_BP_NEW' => ['PERMISSION' => 'read']],
            ['ACTIVE' => 'Y']
        );

        self::assertSame(['ACTIVE' => 'Y'], $filter);
    }

    /**
     * Ключ с префиксом-оператором Битрикс — то же самое условие.
     */
    public function testOperatorPrefixDoesNotHideControlKey(): void
    {
        $filter = IblockHelper::mergeProtectedFilter(
            ['!CHECK_PERMISSIONS' => 'Y', '=PERMISSIONS_BY' => 1, '>=MIN_PERMISSION' => 'X'],
            ['CHECK_PERMISSIONS' => 'Y']
        );

        self::assertSame(['CHECK_PERMISSIONS' => 'Y'], $filter);
    }

    /**
     * Вложенная группа присоединяется к запросу через AND и может только сузить
     * выборку, поэтому LOGIC внутри неё остаётся рабочим.
     */
    public function testNestedLogicIsPreserved(): void
    {
        $requestFilter = [
            ['LOGIC' => 'OR', 'NAME' => 'a', 'CODE' => 'b'],
        ];

        $filter = IblockHelper::mergeProtectedFilter($requestFilter, ['ACTIVE' => 'Y']);

        self::assertSame('OR', $filter[0]['LOGIC']);
        self::assertSame('Y', $filter['ACTIVE']);
    }
}
