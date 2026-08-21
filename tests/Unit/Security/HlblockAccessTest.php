<?php

namespace Uplab\Api\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Model\Hlblock\HlblockService;

/**
 * Правило доступа к HL-блокам: блок публичен, пока никому не выдана операция
 * hl_element_read; как только право выдано — читают только его обладатели и админ.
 */
final class HlblockAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['USER']);
    }

    public function testBlockWithoutReadGrantsIsPublic(): void
    {
        unset($GLOBALS['USER']);
        $service = new FakeHlblockService();

        self::assertTrue($service->canRead(12));
        self::assertFalse($service->userOperationsFetched, 'Штатный резолвер для анонима не нужен.');
    }

    public function testReadGrantClosesBlockForAnonymous(): void
    {
        unset($GLOBALS['USER']);
        $service = new FakeHlblockService(readAccessCodes: ['G5']);

        self::assertFalse($service->canRead(12));
        self::assertFalse($service->userOperationsFetched, 'Для анонима b_user_access пуст — фолбэк бессмыслен.');
    }

    public function testReadGrantForEveryoneGroupKeepsBlockPublic(): void
    {
        unset($GLOBALS['USER']);

        self::assertTrue((new FakeHlblockService(readAccessCodes: ['G2']))->canRead(12));
    }

    public function testReadGrantMatchesUserGroupCode(): void
    {
        $GLOBALS['USER'] = new FakeUser(groups: [2, 5]);

        self::assertTrue((new FakeHlblockService(readAccessCodes: ['G5']))->canRead(12));
    }

    public function testUserWithoutMatchingGroupIsDenied(): void
    {
        $GLOBALS['USER'] = new FakeUser(groups: [2, 6]);

        self::assertFalse((new FakeHlblockService(readAccessCodes: ['G5']))->canRead(12));
    }

    public function testAuthorizedUserFallsBackToNativeResolver(): void
    {
        // Права выданы кодом, который без b_user_access не раскрыть (отдел, соцгруппа)
        $GLOBALS['USER'] = new FakeUser(groups: [2, 6]);
        $service = new FakeHlblockService(readAccessCodes: ['DR31'], userOperations: ['hl_element_read']);

        self::assertTrue($service->canRead(12));
        self::assertTrue($service->userOperationsFetched);
    }

    public function testAdministratorIsAllowedRegardlessOfGrants(): void
    {
        $GLOBALS['USER'] = new FakeUser(groups: [1], admin: true);

        self::assertTrue((new FakeHlblockService(readAccessCodes: ['G5']))->canRead(12));
    }

    public function testAuthorizedUserCodesIncludeSelfAndAllAuthorized(): void
    {
        $GLOBALS['USER'] = new FakeUser(groups: [2, 6], id: 42);

        self::assertSame(['G2', 'G6', 'AU', 'U42'], (new FakeHlblockService())->accessCodes());
    }

    public function testAnonymousCodesAreLimitedToEveryoneGroup(): void
    {
        unset($GLOBALS['USER']);

        self::assertSame(['G2'], (new FakeHlblockService())->accessCodes());
    }
}

final class FakeUser extends \CUser
{
    public function __construct(
        private array $groups = [2],
        private bool $admin = false,
        private int $id = 7
    ) {
    }

    public function GetUserGroupArray(): array
    {
        return $this->groups;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function getId(): int
    {
        return $this->id;
    }
}

/**
 * Обращения к правам HL-блока подменены: тест проверяет логику решения, а не выборку.
 */
final class FakeHlblockService extends HlblockService
{
    public bool $userOperationsFetched = false;

    public function __construct(
        private array $readAccessCodes = [],
        private array $userOperations = []
    ) {
    }

    public function canRead(int $hlblockId): bool
    {
        return $this->canReadHlblock($hlblockId);
    }

    /**
     * @return string[]
     */
    public function accessCodes(): array
    {
        return $this->getCurrentUserAccessCodes();
    }

    protected function fetchReadAccessCodes(int $hlblockId): array
    {
        return $this->readAccessCodes;
    }

    protected function fetchUserOperations(int $hlblockId): array
    {
        $this->userOperationsFetched = true;

        return $this->userOperations;
    }
}
