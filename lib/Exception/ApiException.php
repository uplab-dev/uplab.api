<?php

namespace Uplab\Api\Exception;

/**
 * Намеренная клиентская ошибка API.
 *
 * Бросается из сервисов/контроллеров, когда нужно вернуть клиенту осмысленную ошибку
 * с конкретным HTTP-статусом и безопасным (не раскрывающим внутренности) сообщением.
 * Перехватывается в {@see \Uplab\Api\Controller\BaseController::getActionResponse()} и
 * отдаётся как RFC 9457 (application/problem+json) с указанным статусом.
 *
 * ВАЖНО: сообщение считается клиенто-безопасным — не передавайте сюда детали исключений,
 * трассировки и внутренние идентификаторы.
 *
 * @example throw new ApiException('Форма не найдена', 404);
 */
class ApiException extends \Exception
{
    /**
     * HTTP-статус ответа (обычно 4xx).
     * @var int
     */
    protected int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
