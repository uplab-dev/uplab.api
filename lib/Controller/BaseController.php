<?php

namespace Uplab\Api\Controller;

use Bitrix\Main\Application;
use Bitrix\Main\Engine\Action;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Uplab\Api\ActionFilter\ApiAccessFilter;
use Uplab\Api\Enums\EventLogType;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;
use Uplab\Api\Main;
use Uplab\Api\Utils\Config\BaseConfig;

abstract class BaseController extends Controller
{
	protected const MAX_JSON_BODY_BYTES = 65536;
	protected const MAX_JSON_BODY_DEPTH = 32;

	/**
	 * Типы тела, которые не являются JSON-параметрами конфига
	 */
	protected const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';
	protected const CONTENT_TYPE_MULTIPART = 'multipart/form-data';

	public function getDefaultPreFilters(): array
	{
		return [
			new ApiAccessFilter(),
		];
	}

	/**
	 * Перехватываем исключения/ошибки экшена и отдаём корректный REST-ответ (RFC 9457,
	 * application/problem+json):
	 *  - {@see ApiException} — намеренная клиентская ошибка → её HTTP-статус + сообщение;
	 *  - любой другой \Throwable — баг сервера → HTTP 500 + обобщённое сообщение (+ лог),
	 *    без раскрытия внутренностей (по умолчанию Bitrix отдал бы текст исключения с HTTP 200).
	 *
	 * @param Action $action
	 * @return HttpResponse|mixed
	 */
	protected function getActionResponse(Action $action)
	{
		try {
			return parent::getActionResponse($action);
		} catch (ApiException $e) {
			return $this->renderApiException($e);
		} catch (\Throwable $t) {
			return $this->renderInternalError($t);
		}
	}

	/**
	 * Страховка для исключений, не дошедших до {@see getActionResponse()}.
	 *
	 * На ядре без этого хука (старый main вызывает runWithSourceParametersList() прямо из run())
	 * сюда попадают ВСЕ исключения экшена, на новом — только исключения фильтров и привязки
	 * параметров. Ответ в обоих случаях тот же, что из getActionResponse(): ApiException —
	 * свой 4xx без записи в журнал, остальное — HTTP 500 + запись в Журнал событий.
	 *
	 * run() ядра после исключения возвращает null и собирает стандартный конверт (HTTP 200),
	 * поэтому ответ отправляется напрямую и запрос завершается.
	 *
	 * @param \Exception $e
	 * @return void
	 */
	protected function runProcessingException(\Exception $e)
	{
		$this->endWithResponse(
			$e instanceof ApiException ? $this->renderApiException($e) : $this->renderInternalError($e)
		);
	}

	/**
	 * То же для PHP-ошибок (\Error, в т.ч. TypeError).
	 *
	 * @param \Error $error
	 * @return void
	 */
	protected function runProcessingError(\Error $error)
	{
		$this->endWithResponse($this->renderInternalError($error));
	}

	/**
	 * Отправляет ответ и завершает запрос — так же ядро отдаёт ответ контроллера
	 * в HttpApplication::runController(). finally-блоки run() при этом не выполняются.
	 *
	 * @param HttpResponse $response
	 * @return void
	 */
	private function endWithResponse(HttpResponse $response): void
	{
		Application::getInstance()->end(0, $response);
	}

	/**
	 * Ответ на намеренную клиентскую ошибку (RFC 9457): статус и сообщение — из исключения.
	 * Не логируется как внутренняя ошибка (это ожидаемая ситуация, а не баг) — иначе любой клиент
	 * кривым телом запроса забивал бы Журнал событий и уведомления администратора.
	 *
	 * @param ApiException $e
	 * @return HttpResponse
	 */
	private function renderApiException(ApiException $e): HttpResponse
	{
		return $this->problemResponse($e->getHttpStatus(), $e->getMessage());
	}

	/**
	 * Ответ на внутреннюю ошибку (RFC 9457): HTTP 500 + обобщённое сообщение, детали — в лог.
	 *
	 * @param \Throwable $t
	 * @return HttpResponse
	 */
	private function renderInternalError(\Throwable $t): HttpResponse
	{
		$this->logInternalThrowable($t);

		return $this->problemResponse(
			500,
			(string)Loc::getMessage('UPLAB_API.INTERNAL_ERROR'),
			EventLogType::INTERNAL_ERROR->value
		);
	}

	/**
	 * Строит ответ RFC 9457 (Problem Details, application/problem+json).
	 *
	 * @param int         $status HTTP-статус
	 * @param string      $detail Человекочитаемое описание (клиенто-безопасное)
	 * @param string|null $code   Необязательный машинный код ошибки (extension member)
	 * @return HttpResponse
	 */
	private function problemResponse(int $status, string $detail, ?string $code = null): HttpResponse
	{
		$phrase = $this->reasonPhrase($status);

		$problem = [
			'type'   => 'about:blank',
			'title'  => $phrase,
			'status' => $status,
			'detail' => $detail,
		];
		if ($code !== null) {
			$problem['code'] = $code;
		}

		$response = new HttpResponse();
		$response->setStatus($status . ' ' . $phrase);
		$response->addHeader('Content-Type', 'application/problem+json; charset=UTF-8');
		$response->setContent(json_encode($problem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		return $response;
	}

	/**
	 * Reason-phrase для строки статуса и поля title.
	 *
	 * @param int $status
	 * @return string
	 */
	private function reasonPhrase(int $status): string
	{
		return match ($status) {
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			409 => 'Conflict',
			413 => 'Content Too Large',
			422 => 'Unprocessable Entity',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			503 => 'Service Unavailable',
			default => $status >= 500 ? 'Server Error' : 'Error',
		};
	}

	/**
	 * Пишет полную информацию об ошибке в Журнал событий и уведомляет администратора.
	 * Наружу детали не отдаются.
	 *
	 * @param \Throwable $t
	 * @return void
	 */
	private function logInternalThrowable(\Throwable $t): void
	{
		$request = Application::getInstance()->getContext()->getRequest();
		$uri     = (string)$request->getRequestUri();
		$method  = (string)$request->getRequestMethod();

		\CEventLog::Log(
			\CEventLog::SEVERITY_ERROR,
			EventLogType::INTERNAL_ERROR->value,
			Main::MODULE_ID,
			$uri,
			sprintf(
				"%s: %s\n%s:%d\n%s %s\n%s",
				get_class($t),
				$t->getMessage(),
				$t->getFile(),
				$t->getLine(),
				$method,
				$uri,
				$t->getTraceAsString()
			)
		);

		$logUrl = '/bitrix/admin/event_log.php?set_filter=Y&find_type=audit_type_id'
			. '&find_audit_type%5B0%5D=' . EventLogType::INTERNAL_ERROR->value . '&lang=' . LANGUAGE_ID;

		\CAdminNotify::Add([
			'NOTIFY_TYPE'  => \CAdminNotify::TYPE_ERROR,
			'MESSAGE'      => (string)Loc::getMessage('UPLAB_API.NOTIFY_INTERNAL_ERROR', ['#URL#' => $logUrl]),
			'TAG'          => 'uplab_api_internal_error',
			'MODULE_ID'    => Main::MODULE_ID,
			'ENABLE_CLOSE' => 'Y',
		]);
	}

	/**
	 * Обёртка, чтобы избавиться от повторения кода
	 *
	 * @param Result $mainResult
	 *
	 * @return array
	 */
	protected function runResult(Result $mainResult): array
	{
		$result = $mainResult;

		if (!$result->isSuccess()) {
			foreach ($result->getErrors() as $error) {
				$this->addError($error);
			}
		}

		return $result->getData();
	}

	/**
	 * Создать объект конфига запроса из JSON body.
	 *
	 * Класс конфига берётся не напрямую, а из фабрики контроллера (`get*ConfigClass()`), чтобы
	 * проект мог подставить своего наследника — например с другими лимитами запроса
	 * ({@see BaseConfig}) — не копируя тело экшена.
	 *
	 * @param string $configClass Класс-наследник {@see BaseConfig}
	 * @return BaseConfig
	 * @throws ApiException При некорректном теле запроса или недопустимых параметрах
	 */
	protected function createConfig(string $configClass): BaseConfig
	{
		return $configClass::create($this->getJsonRequestBody());
	}

	protected function getJsonRequestBody()
	{
		$rawBody = HttpRequest::getInput();
		if ($rawBody === false || $rawBody === '') {
			return null;
		}
		if (strlen($rawBody) > self::MAX_JSON_BODY_BYTES) {
			throw new ApiException(
				(string)Loc::getMessage(
					'UPLAB_API.ERROR_BODY_TOO_LARGE',
					['#MAX#' => static::MAX_JSON_BODY_BYTES]
				),
				HttpStatus::PAYLOAD_TOO_LARGE
			);
		}

		return static::decodeRequestBody($rawBody, $this->getRequestContentType());
	}

	/**
	 * Разбор тела запроса в массив параметров.
	 *
	 * Тело формы (`x-www-form-urlencoded`, `multipart/form-data`) параметрами конфига не
	 * является: такие поля читает сам экшен из запроса (см. {@see WebFormController::addNewResultAction()}),
	 * поэтому JSON-декодер к ним не применяется. Если клиент объявил JSON, некорректное или
	 * скалярное тело — ошибка запроса (`400`). Для прочих типов (и когда заголовка нет) разбор
	 * остаётся терпимым: тело просто считается «не нашим», параметры берутся по умолчанию.
	 *
	 * @param string $rawBody
	 * @param string $contentType Тип из заголовка Content-Type, без параметров, в нижнем регистре
	 *
	 * @return array|null
	 */
	protected static function decodeRequestBody(string $rawBody, string $contentType): ?array
	{
		if (in_array($contentType, [self::CONTENT_TYPE_FORM, self::CONTENT_TYPE_MULTIPART], true)) {
			return null;
		}

		$declaresJson = $contentType !== '' && str_contains($contentType, 'json');

		try {
			$body = json_decode($rawBody, true, self::MAX_JSON_BODY_DEPTH, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			if ($declaresJson) {
				throw new ApiException(
					(string)Loc::getMessage('UPLAB_API.ERROR_INVALID_JSON'),
					HttpStatus::BAD_REQUEST
				);
			}

			return null;
		}

		if (!is_array($body)) {
			if ($declaresJson) {
				throw new ApiException(
					(string)Loc::getMessage('UPLAB_API.ERROR_JSON_NOT_OBJECT'),
					HttpStatus::BAD_REQUEST
				);
			}

			return null;
		}

		return $body;
	}

	protected function getRequestContentType(): string
	{
		return (string)$this->getRequest()->getHeaders()->getContentType();
	}
}
