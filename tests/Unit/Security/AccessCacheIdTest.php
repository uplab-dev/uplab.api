<?php

namespace Uplab\Api\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Model\Iblock\IblockHelper;
use Uplab\Api\Security\CacheContext;

/**
 * В простом режиме прав видимость данных инфоблока — функция набора групп (ядро строит
 * условие по b_iblock_group). В расширенном режиме права даются персонально (`U<ID>`),
 * по отделу и на «создателя» (`CR`), поэтому кеш делится ещё и по пользователю.
 */
final class AccessCacheIdTest extends TestCase
{
    protected function setUp(): void
    {
        \CIBlock::$rightsMode = [];
    }

    protected function tearDown(): void
    {
        \CIBlock::$rightsMode = [];
        unset($GLOBALS['USER']);
    }

    public function testSimpleRightsModeUsesGroupsOnly(): void
    {
        \CIBlock::$rightsMode = [17 => 'S'];
        $GLOBALS['USER'] = new CacheUser(groups: [2, 6], id: 42);

        self::assertSame('2,6', IblockHelper::getAccessCacheId(17));
    }

    public function testExtendedRightsModeSegmentsByUser(): void
    {
        \CIBlock::$rightsMode = [17 => 'E'];

        $GLOBALS['USER'] = new CacheUser(groups: [2, 6], id: 42);
        $first = IblockHelper::getAccessCacheId(17);

        $GLOBALS['USER'] = new CacheUser(groups: [2, 6], id: 43);
        $second = IblockHelper::getAccessCacheId(17);

        self::assertNotSame($first, $second, 'Пользователи с одинаковыми группами не должны делить кеш.');
        self::assertSame('2,6|u42', $first);
    }

    /**
     * Режим прав неизвестен (например, инфоблок ещё не найден) — берётся строгий сегмент.
     */
    public function testUnknownIblockUsesUserSegment(): void
    {
        $GLOBALS['USER'] = new CacheUser(groups: [2], id: 42);

        self::assertSame('2|u42', IblockHelper::getAccessCacheId());
        self::assertSame('2|u42', IblockHelper::getAccessCacheId(0));
    }

    /**
     * Для анонимного пользователя строгий сегмент совпадает с групповым по смыслу:
     * общий кеш анонимных запросов не дробится.
     */
    public function testAnonymousSegmentIsSharedAcrossRequests(): void
    {
        unset($GLOBALS['USER']);

        self::assertSame('2', CacheContext::getUserGroupsCacheId());
        self::assertSame('2|u0', CacheContext::getUserCacheId());
        self::assertSame('2|u0', IblockHelper::getAccessCacheId());
    }
}

final class CacheUser extends \CUser
{
    public function __construct(private array $groups = [2], private int $id = 0)
    {
    }

    public function GetUserGroupArray(): array
    {
        return $this->groups;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
