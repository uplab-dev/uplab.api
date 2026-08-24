<?php

namespace Uplab\Api\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;
use Uplab\Api\Utils\Config\BaseConfig;
use Uplab\Api\Utils\Config\Iblock\IblockListElementsConfig;

final class RequestLimitsTest extends TestCase
{
    public function testMaximumPaginationIsAccepted(): void
    {
        $config = IblockListElementsConfig::create([
            'limit' => BaseConfig::MAX_LIMIT,
            'offset' => BaseConfig::MAX_OFFSET,
        ]);

        self::assertSame(BaseConfig::MAX_LIMIT, $config->getLimit());
        self::assertSame(BaseConfig::MAX_OFFSET, $config->getOffset());
    }

    public function testLimitAboveMaximumIsRejected(): void
    {
        $this->expectException(ApiException::class);
        IblockListElementsConfig::create(['limit' => BaseConfig::MAX_LIMIT + 1]);
    }

    public function testDeepFilterIsRejected(): void
    {
        $this->expectException(ApiException::class);
        IblockListElementsConfig::create([
            'filter' => ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]],
        ]);
    }

    public function testTypicalFilterByIdListIsAccepted(): void
    {
        $config = IblockListElementsConfig::create(['filter' => ['ID' => range(1, 200)]]);

        self::assertCount(200, $config->getFilter()['ID']);
    }

    public function testInvalidOrderDirectionIsRejected(): void
    {
        $this->expectException(ApiException::class);
        IblockListElementsConfig::create(['order' => ['ID' => 'SIDEWAYS']]);
    }

    /**
     * Направления с NULLS принимает ядро (CIBlock::_Order()), значит принимает и конфиг.
     */
    public function testNullsOrderDirectionsAreAccepted(): void
    {
        $order = ['PROPERTY_PRICE' => 'ASC,NULLS', 'SORT' => 'nulls,desc'];

        self::assertSame($order, IblockListElementsConfig::create(['order' => $order])->getOrder());
    }

    public function testInvalidSiteIdIsRejected(): void
    {
        $this->expectException(ApiException::class);
        BaseConfig::create(['siteId' => '../cache']);
    }

    /**
     * Пустая строка — «фильтр по сайту не применять»: поведение до 1.12.0 сохранено,
     * иначе обновление ломало бы клиентов с незаданным siteId.
     */
    public function testEmptySiteIdIsAccepted(): void
    {
        self::assertSame('', BaseConfig::create(['siteId' => ''])->getSiteId());
    }

    public function testUnsafeCacheSegmentIsRejected(): void
    {
        $this->expectException(ApiException::class);
        BaseConfig::assertCacheSegment('../managed_cache', 'page code');
    }

    /**
     * Проект поднимает лимит переопределением константы в наследнике конфига: проверки
     * обращаются к лимитам через static::, поэтому тело проверок копировать не нужно.
     */
    public function testInheritedConfigRaisesPaginationLimits(): void
    {
        $config = ProjectLimitsConfig::create([
            'limit' => BaseConfig::MAX_LIMIT + 1,
            'offset' => BaseConfig::MAX_OFFSET + 1,
        ]);

        self::assertSame(BaseConfig::MAX_LIMIT + 1, $config->getLimit());
        self::assertSame(BaseConfig::MAX_OFFSET + 1, $config->getOffset());
    }

    public function testInheritedConfigRaisesFilterAndSelectLimits(): void
    {
        $config = ProjectLimitsConfig::create([
            'filter' => ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 1]]]]]],
            'select' => array_map(static fn(int $i): string => 'FIELD_' . $i, range(1, BaseConfig::MAX_SELECT_FIELDS + 1)),
        ]);

        self::assertCount(BaseConfig::MAX_SELECT_FIELDS + 1, $config->getSelect());
    }

    public function testInheritedConfigCanLowerLimits(): void
    {
        $this->expectException(ApiException::class);
        StrictLimitsConfig::create(['limit' => 11]);
    }

    /**
     * Базовый конфиг остаётся строгим: переопределение в наследнике на него не влияет.
     */
    public function testBaseConfigLimitsAreNotAffectedByHeir(): void
    {
        $this->expectException(ApiException::class);
        IblockListElementsConfig::create(['limit' => BaseConfig::MAX_LIMIT + 1]);
    }

    public function testInheritedConfigExtendsOrderDirections(): void
    {
        $order = ['SORT' => 'RAND'];

        self::assertSame($order, ProjectLimitsConfig::create(['order' => $order])->getOrder());
    }

    /**
     * Сообщения об ошибках берутся из языковых сообщений модуля, а не из строк в коде:
     * тест проверяет ключ и подстановку лимита.
     */
    public function testLimitErrorUsesLocalizedMessageWithLimitValue(): void
    {
        try {
            IblockListElementsConfig::create(['limit' => BaseConfig::MAX_LIMIT + 1]);
            self::fail('Ожидалось ApiException');
        } catch (ApiException $e) {
            self::assertStringContainsString('UPLAB_API.ERROR_LIMIT_RANGE', $e->getMessage());
            self::assertStringContainsString((string)BaseConfig::MAX_LIMIT, $e->getMessage());
        }
    }

    /**
     * В сообщение подставляется лимит наследника, а не базовый.
     */
    public function testInheritedLimitAppearsInErrorMessage(): void
    {
        try {
            StrictLimitsConfig::create(['limit' => StrictLimitsConfig::MAX_LIMIT + 1]);
            self::fail('Ожидалось ApiException');
        } catch (ApiException $e) {
            self::assertStringContainsString('#MAX#=' . StrictLimitsConfig::MAX_LIMIT, $e->getMessage());
        }
    }

    /**
     * Статус ответа задаётся кейсом HttpStatus, наружу отдаётся числом.
     */
    public function testApiExceptionCarriesHttpStatusFromEnum(): void
    {
        try {
            IblockListElementsConfig::create(['limit' => BaseConfig::MAX_LIMIT + 1]);
            self::fail('Ожидалось ApiException');
        } catch (ApiException $e) {
            self::assertSame(HttpStatus::BAD_REQUEST->value, $e->getHttpStatus());
        }
    }

    /**
     * Числовой статус тоже принимается — обратная совместимость с кодом до появления enum.
     */
    public function testApiExceptionAcceptsIntStatus(): void
    {
        self::assertSame(404, (new ApiException('test', 404))->getHttpStatus());
        self::assertSame(
            HttpStatus::NOT_FOUND->value,
            (new ApiException('test', HttpStatus::NOT_FOUND))->getHttpStatus()
        );
    }

    /**
     * Длины имени поля и строкового значения — тоже переопределяемые лимиты.
     */
    public function testInheritedConfigRaisesLengthLimits(): void
    {
        $longKey = str_repeat('A', BaseConfig::MAX_FIELD_NAME_LENGTH + 1);

        $this->expectException(ApiException::class);
        IblockListElementsConfig::create(['filter' => [$longKey => 1]]);
    }

    public function testLongFieldNameIsAcceptedByHeirWithRaisedLimit(): void
    {
        $longKey = str_repeat('A', BaseConfig::MAX_FIELD_NAME_LENGTH + 1);
        $config = LongNamesConfig::create(['filter' => [$longKey => 1]]);

        self::assertArrayHasKey($longKey, $config->getFilter());
    }

    /**
     * Фабрика класса конфига в контроллере: проект подставляет своего наследника,
     * тело экшена не копируется.
     */
    public function testControllerFactorySubstitutesConfigClass(): void
    {
        $controller = new FakeLimitsController();
        $controller->body = ['limit' => BaseConfig::MAX_LIMIT + 1];

        $config = $controller->makeElementListConfig();

        self::assertInstanceOf(ProjectLimitsConfig::class, $config);
        self::assertSame(BaseConfig::MAX_LIMIT + 1, $config->getLimit());
    }

    /**
     * Базовый контроллер продолжает отдавать штатный конфиг со штатными лимитами.
     */
    public function testDefaultControllerKeepsBaseConfigClass(): void
    {
        $controller = new DefaultLimitsController();
        $controller->body = ['limit' => BaseConfig::MAX_LIMIT + 1];

        $this->expectException(ApiException::class);
        $controller->makeElementListConfig();
    }
}

/**
 * Контроллер проекта: подменён только класс конфига.
 */
final class FakeLimitsController extends \Uplab\Api\Controller\IblockController
{
    public ?array $body = null;

    public function makeElementListConfig(): BaseConfig
    {
        return $this->createConfig($this->getElementListConfigClass());
    }

    protected function getElementListConfigClass(): string
    {
        return ProjectLimitsConfig::class;
    }

    protected function getJsonRequestBody()
    {
        return $this->body;
    }
}

final class DefaultLimitsController extends \Uplab\Api\Controller\IblockController
{
    public ?array $body = null;

    public function makeElementListConfig(): BaseConfig
    {
        return $this->createConfig($this->getElementListConfigClass());
    }

    protected function getJsonRequestBody()
    {
        return $this->body;
    }
}

/**
 * Проектный конфиг с поднятыми лимитами — как в README, раздел
 * «Переопределение лимитов запросов».
 */
final class ProjectLimitsConfig extends IblockListElementsConfig
{
    public const MAX_LIMIT = BaseConfig::MAX_LIMIT + 1;
    public const MAX_OFFSET = BaseConfig::MAX_OFFSET + 1;
    public const MAX_FILTER_DEPTH = BaseConfig::MAX_FILTER_DEPTH + 2;
    public const MAX_SELECT_FIELDS = BaseConfig::MAX_SELECT_FIELDS + 1;
    public const ORDER_DIRECTIONS = ['ASC', 'DESC', 'RAND'];
}

/**
 * Поднята максимальная длина имени поля и строкового значения.
 */
final class LongNamesConfig extends IblockListElementsConfig
{
    public const MAX_FIELD_NAME_LENGTH = BaseConfig::MAX_FIELD_NAME_LENGTH + 10;
    public const MAX_STRING_VALUE_LENGTH = BaseConfig::MAX_STRING_VALUE_LENGTH + 10;
}

/**
 * Лимиты можно не только поднимать, но и ужесточать.
 */
final class StrictLimitsConfig extends IblockListElementsConfig
{
    public const MAX_LIMIT = 10;
}
