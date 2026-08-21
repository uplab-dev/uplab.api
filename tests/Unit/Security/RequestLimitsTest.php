<?php

namespace Uplab\Api\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
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
}
