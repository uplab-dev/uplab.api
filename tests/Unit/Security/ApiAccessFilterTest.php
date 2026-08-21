<?php

namespace Uplab\Api\Tests\Unit\Security;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\EventResult;
use PHPUnit\Framework\TestCase;
use Uplab\Api\ActionFilter\ApiAccessFilter;

/**
 * Списки Origin и IP проверяются по «ИЛИ»: браузерный запрос проходит по Origin,
 * серверный (без заголовка) — по IP. Запрос, не прошедший ни по одному ЗАДАННОМУ
 * списку, отклоняется — снять заголовок и попасть в «пустой список» больше нельзя.
 * Если не задан ни один список, ограничений нет и API открыт.
 */
final class ApiAccessFilterTest extends TestCase
{
    protected function setUp(): void
    {
        Option::$values = [];
        HttpRequest::$headers = [];
        HttpRequest::$remoteAddress = '10.0.0.1';
    }

    protected function tearDown(): void
    {
        Option::$values = [];
        HttpRequest::$headers = [];
        HttpRequest::$remoteAddress = '';
    }

    public function testDisabledProtectionPassesEverything(): void
    {
        $this->configure('N', '', '');

        self::assertTrue($this->isAllowed());
    }

    /**
     * Включённый чекбокс без заполненных списков не должен выключать API:
     * ограничивать нечем, запросы проходят.
     */
    public function testEnabledProtectionWithoutRulesPasses(): void
    {
        $this->configure('Y', '', '');

        self::assertTrue($this->isAllowed());
    }

    public function testServerRequestPassesByIpWhenOriginListIsAlsoConfigured(): void
    {
        // Главный сценарий Nuxt SSR: запрос без Origin с разрешённого IP
        $this->configure('Y', "https://site.ru", "10.0.0.1");

        self::assertTrue($this->isAllowed());
    }

    public function testBrowserRequestPassesByOriginFromForeignIp(): void
    {
        $this->configure('Y', "https://site.ru", "10.0.0.1");
        HttpRequest::$remoteAddress = '203.0.113.7';
        HttpRequest::$headers['origin'] = 'https://site.ru';

        self::assertTrue($this->isAllowed());
    }

    public function testRequestMatchingNeitherListIsDenied(): void
    {
        $this->configure('Y', "https://site.ru", "10.0.0.1");
        HttpRequest::$remoteAddress = '203.0.113.7';
        HttpRequest::$headers['origin'] = 'https://evil.ru';

        self::assertFalse($this->isAllowed());
    }

    public function testMissingOriginIsDeniedWhenOnlyOriginListIsConfigured(): void
    {
        // Обход «удалить заголовок Origin» закрыт: IP-списка нет — проходить нечем
        $this->configure('Y', "https://site.ru", '');

        self::assertFalse($this->isAllowed());
    }

    public function testOnlyIpListIgnoresOriginValue(): void
    {
        $this->configure('Y', '', "10.0.0.1");
        HttpRequest::$headers['origin'] = 'https://evil.ru';

        self::assertTrue($this->isAllowed());
    }

    public function testSchemeInAllowlistIsOptional(): void
    {
        $this->configure('Y', "site.ru", '');
        HttpRequest::$headers['origin'] = 'https://site.ru';

        self::assertTrue($this->isAllowed());
    }

    public function testPortIsPartOfOrigin(): void
    {
        $this->configure('Y', "https://site.ru:3000", '');

        HttpRequest::$headers['origin'] = 'https://site.ru:3000';
        self::assertTrue($this->isAllowed());

        HttpRequest::$headers['origin'] = 'https://site.ru';
        self::assertFalse($this->isAllowed());
    }

    public function testOpaqueOriginIsDenied(): void
    {
        $this->configure('Y', "site.ru", '');
        HttpRequest::$headers['origin'] = 'null';

        self::assertFalse($this->isAllowed());
    }

    public function testNonHttpSchemeIsDenied(): void
    {
        $this->configure('Y', "site.ru", '');
        HttpRequest::$headers['origin'] = 'file://site.ru';

        self::assertFalse($this->isAllowed());
    }

    private function configure(string $enabled, string $origins, string $ips): void
    {
        Option::$values = [
            'API_ACCESS_ENABLED'   => $enabled,
            'API_ALLOWED_ORIGINS'  => $origins,
            'API_ALLOWED_IPS'      => $ips,
        ];
    }

    private function isAllowed(): bool
    {
        $result = (new ApiAccessFilter())->onBeforeAction(new Event());

        if ($result === null) {
            return true;
        }

        self::assertSame(EventResult::ERROR, $result->getType());

        return false;
    }
}
