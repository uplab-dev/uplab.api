<?php

namespace Uplab\Api\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Security\CacheContext;

final class CacheContextTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['USER']);
    }

    public function testAnonymousFallbackUsesEveryoneGroup(): void
    {
        unset($GLOBALS['USER']);

        self::assertSame('2', CacheContext::getUserGroupsCacheId());
    }

    public function testGroupsAreNormalizedForStableCacheId(): void
    {
        $GLOBALS['USER'] = new class extends \CUser {
            public function GetUserGroupArray(): array
            {
                return [7, '2', 7, 0, 3];
            }
        };

        self::assertSame('2,3,7', CacheContext::getUserGroupsCacheId());
    }
}
