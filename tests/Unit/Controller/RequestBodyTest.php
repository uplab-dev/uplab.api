<?php

namespace Uplab\Api\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Uplab\Api\Controller\BaseController;
use Uplab\Api\Exception\ApiException;

/**
 * Тело формы (`x-www-form-urlencoded`, `multipart/form-data`) не является JSON-параметрами:
 * поля веб-формы читает сам экшен из запроса, поэтому JSON-декодер к такому телу не применяется.
 */
final class RequestBodyTest extends TestCase
{
    public function testFormBodyIsNotDecodedAsJson(): void
    {
        $body = 'siteId=ru&form_text_NAME=Test';

        self::assertNull(FakeController::decode($body, 'application/x-www-form-urlencoded'));
        self::assertNull(FakeController::decode($body, 'multipart/form-data'));
    }

    public function testJsonBodyIsDecoded(): void
    {
        $decoded = FakeController::decode('{"limit":5,"filter":{"ID":1}}', 'application/json');

        self::assertSame(5, $decoded['limit']);
        self::assertSame(['ID' => 1], $decoded['filter']);
    }

    public function testJsonBodyWithoutContentTypeIsDecoded(): void
    {
        self::assertSame(['limit' => 5], FakeController::decode('{"limit":5}', ''));
    }

    /**
     * Клиент объявил JSON — некорректное тело считается ошибкой запроса.
     */
    public function testInvalidJsonWithJsonContentTypeIsRejected(): void
    {
        $this->expectException(ApiException::class);
        FakeController::decode('{"limit":', 'application/json');
    }

    public function testScalarJsonWithJsonContentTypeIsRejected(): void
    {
        $this->expectException(ApiException::class);
        FakeController::decode('"строка"', 'application/vnd.api+json');
    }

    /**
     * Тип не объявлен (или не JSON) — тело считается «не нашим», а не ошибкой:
     * поведение как до появления строгого разбора.
     */
    public function testInvalidJsonWithoutContentTypeIsIgnored(): void
    {
        self::assertNull(FakeController::decode('not a json at all', ''));
        self::assertNull(FakeController::decode('not a json at all', 'text/plain'));
    }
}

final class FakeController extends BaseController
{
    public static function decode(string $rawBody, string $contentType): ?array
    {
        return static::decodeRequestBody($rawBody, $contentType);
    }
}
