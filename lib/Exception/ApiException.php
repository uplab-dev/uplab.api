<?php

namespace Uplab\Api\Exception;

use Uplab\Api\Enums\HttpStatus;

/**
 * Намеренная клиентская ошибка API.
 *
 * Бросается из сервисов/контроллеров, когда нужно вернуть клиенту осмысленную ошибку
 * с конкретным HTTP-статусом и безопасным (не раскрывающим внутренности) сообщением.
 * Перехватывается в {@see \Uplab\Api\Controller\BaseController::getActionResponse()} и
 * отдаётся как RFC 9457 (application/problem+json) с указанным статусом.
 *
 * Статус передаётся кейсом {@see HttpStatus} — так его нельзя опечатать; `int` тоже принимается
 * для обратной совместимости с кодом, написанным до появления enum.
 *
 * Текст сообщения берётся из языковых сообщений модуля
 * (`Loc::getMessage('UPLAB_API.ERROR_…')`, файлы `lang/{ru,en}/include.php`), а не задаётся
 * строкой в коде: сообщение видит клиент API, и на проекте его можно переопределить, не правя
 * модуль.
 *
 * ВАЖНО: сообщение считается клиенто-безопасным — не передавайте сюда детали исключений,
 * трассировки и внутренние идентификаторы.
 *
 * @example throw new ApiException(Loc::getMessage('UPLAB_API.WEB_FORM_NOT_FOUND'), HttpStatus::NOT_FOUND);
 */
class ApiException extends \Exception
{
    /**
     * HTTP-статус ответа (обычно 4xx).
     * @var int
     */
    protected int $httpStatus;

    /**
     * @param string $message Клиенто-безопасное сообщение, обычно из языковых сообщений модуля
     * @param HttpStatus|int $httpStatus Статус ответа; `int` поддерживается для обратной совместимости
     * @param int $code Код исключения PHP (не HTTP-статус)
     * @param \Throwable|null $previous Исходное исключение, если ошибка обёрнута
     */
    public function __construct(
        string $message,
        HttpStatus|int $httpStatus = HttpStatus::BAD_REQUEST,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatus = $httpStatus instanceof HttpStatus ? $httpStatus->value : $httpStatus;
    }

    /**
     * HTTP-статус для ответа клиенту.
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
