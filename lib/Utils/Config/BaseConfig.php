<?php

namespace Uplab\Api\Utils\Config;

use Bitrix\Main\Localization\Loc;
use Uplab\Api\Enums\HttpStatus;
use Uplab\Api\Exception\ApiException;

/**
 * Базовый конфиг запроса и общие проверки входных данных.
 *
 * Выполняет две задачи:
 *
 * 1. Объект параметров запроса. Наследники ({@see \Uplab\Api\Utils\Config\Iblock\IblockListElementsConfig}
 *    и др.) создаются из JSON body через `static::create($body)` и передаются в сервисы
 *    ({@see \Uplab\Api\Model\Iblock\IblockService}) вместо «сырого» массива. Здесь живёт то,
 *    что есть у всех эндпоинтов, — привязка к сайту и язык.
 * 2. Общие ограничения на входные данные. Константы `MAX_*` и методы `assert*()` — единственное
 *    место, где заданы лимиты запроса; наследники и сервисы пользуются ими, а не своими числами.
 *    Проверки выполняются до обращения к БД и кешу, поэтому чрезмерно большие и некорректные
 *    параметры не доходят до ядра Битрикс.
 *
 * Все проверки при нарушении бросают {@see ApiException} со статусом 400: это намеренная
 * клиентская ошибка, она перехватывается в {@see \Uplab\Api\Controller\BaseController::getActionResponse()}
 * и отдаётся как `400 Bad Request` в формате RFC 9457. Из этого следует, что вызывать `assert*()`
 * можно только в контексте обработки запроса — во внутреннем коде (агенты, консольные скрипты)
 * такое исключение никто не перехватит.
 *
 * Проверки обращаются к лимитам через `static::`, поэтому проект может поднять или понизить любой
 * из них, унаследовав нужный конфиг и переопределив константу, — тело проверки копировать не нужно:
 *
 * ```php
 * class ProjectListConfig extends IblockListElementsConfig
 * {
 *     public const MAX_LIMIT = 2000;
 * }
 * ```
 *
 * Наследник подключается фабрикой класса конфига в контроллере
 * ({@see \Uplab\Api\Controller\IblockController::getElementListConfigClass()}), см. README,
 * раздел «Переопределение лимитов запросов».
 *
 * Лимиты описаны в README, раздел «Ограничения запросов», — при изменении констант правьте и его.
 */
class BaseConfig
{
    /** Максимальное значение `limit` (кол-во элементов в выборке). */
    public const MAX_LIMIT = 500;

    /** Максимальное значение `offset` (смещение выборки). */
    public const MAX_OFFSET = 100000;

    /** Максимальное суммарное число элементов в массиве условий (`filter` и подобных), с учётом вложенных. */
    public const MAX_FILTER_ITEMS = 1000;

    /** Максимальная глубина вложенности массива условий. */
    public const MAX_FILTER_DEPTH = 5;

    /** Максимальное число полей в `select` и `propertyCode`. */
    public const MAX_SELECT_FIELDS = 100;

    /** Максимальное число полей в `order`. */
    public const MAX_ORDER_FIELDS = 10;

    /** Максимальная длина имени поля (ключа условия, поля `select`, `order`, `propertyCode`). */
    public const MAX_FIELD_NAME_LENGTH = 128;

    /** Максимальная длина строкового значения в массиве условий. */
    public const MAX_STRING_VALUE_LENGTH = 4096;

    /**
     * Направления сортировки, которые принимает ядро (см. CIBlock::_Order())
     */
    public const ORDER_DIRECTIONS = ['ASC', 'DESC', 'ASC,NULLS', 'DESC,NULLS', 'NULLS,ASC', 'NULLS,DESC'];

    /**
     * ID сайта Битрикс, к которому привязана выборка.
     * Пустая строка означает «фильтр по сайту не применять», `null` до вызова `create()`.
     * @var string|null
     */
    protected ?string $siteId = null;

    /**
     * Язык (используется там, где данные хранятся в разрезе языка, например статический контент).
     * @var string|null
     */
    protected ?string $languageId = null;

    /**
     * Получить конфигурацию по умолчанию, только siteId
     * @param array{siteId?: string}|null $arBody Массив параметров конфигурации
     * @return static
     * @throws ApiException Если `siteId` не проходит проверку формата
     */
    public static function create(?array $arBody): static
    {
        $config = new static();
        //Привязка к сайту
        $config->setSiteId($arBody['siteId'] ?? null);

        return $config;
    }

    /**
     * Задать привязку к сайту.
     *
     * @param string|null $siteId ID сайта: 1–32 символа из латиницы, цифр, `_` и `-`.
     *                            `null` — берётся `SITE_ID` текущего сайта;
     *                            пустая строка — фильтр по сайту не применяется.
     * @return static
     * @throws ApiException 400, если формат значения не подходит
     */
    public function setSiteId(?string $siteId): static
    {
        // Пустая строка означает «фильтр по сайту не применять» — так вёл себя модуль до
        // появления проверки, и обновление не должно менять выборки существующих клиентов.
        if ($siteId !== null && $siteId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,32}$/', $siteId)) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_INVALID_SITE_ID'),
                HttpStatus::BAD_REQUEST
            );
        }
        $this->siteId = $siteId ?? SITE_ID;
        return $this;
    }

    /**
     * ID сайта из конфига: `SITE_ID` по умолчанию, пустая строка — «без привязки к сайту».
     * @return string|null
     */
    public function getSiteId(): ?string
    {
        return $this->siteId;
    }

    /**
     * Задать язык выборки. Формат не проверяется: значение задаёт код модуля, а не клиент.
     * @param string|null $languageId
     * @return static
     */
    public function setLanguageId(?string $languageId): static
    {
        $this->languageId = $languageId;
        return $this;
    }

    /**
     * @return string|null Язык выборки или `null`, если не задан
     */
    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    /**
     * Проверить «стоимость» массива условий: число элементов, глубину и длину строк.
     *
     * Применяется к `filter` и другим массивам, которые уходят в выборку: без ограничения
     * клиент может прислать тысячи условий или глубоко вложенную структуру и получить
     * неоправданно дорогой запрос к БД. Считаются все элементы, включая вложенные.
     *
     * @param array $value Проверяемый массив условий
     * @param int|null $maxItems Максимум элементов суммарно; `null` — {@see static::MAX_FILTER_ITEMS}
     * @param int|null $maxDepth Максимальная глубина вложенности; `null` — {@see static::MAX_FILTER_DEPTH}
     * @return void
     * @throws ApiException 400 при превышении лимита элементов, глубины,
     *                      длины имени поля (128) или длины строкового значения (4096)
     */
    public static function assertArrayComplexity(
        array $value,
        ?int $maxItems = null,
        ?int $maxDepth = null
    ): void {
        // static:: в значении по умолчанию параметра запрещён языком, поэтому разрешаем здесь:
        // так наследник конфига может поднять лимит переопределением константы.
        $maxItems ??= static::MAX_FILTER_ITEMS;
        $maxDepth ??= static::MAX_FILTER_DEPTH;
        $maxFieldName = static::MAX_FIELD_NAME_LENGTH;
        $maxStringValue = static::MAX_STRING_VALUE_LENGTH;
        $items = 0;
        $walk = static function (array $part, int $depth) use (
            &$walk,
            &$items,
            $maxItems,
            $maxDepth,
            $maxFieldName,
            $maxStringValue
        ): void {
            if ($depth > $maxDepth) {
                throw new ApiException(
                    (string)Loc::getMessage('UPLAB_API.ERROR_FILTER_TOO_DEEP', ['#MAX#' => $maxDepth]),
                    HttpStatus::BAD_REQUEST
                );
            }
            foreach ($part as $key => $item) {
                $items++;
                if ($items > $maxItems) {
                    throw new ApiException(
                        (string)Loc::getMessage('UPLAB_API.ERROR_FILTER_TOO_MANY_ITEMS', ['#MAX#' => $maxItems]),
                        HttpStatus::BAD_REQUEST
                    );
                }
                if (is_string($key) && strlen($key) > $maxFieldName) {
                    throw new ApiException(
                        (string)Loc::getMessage('UPLAB_API.ERROR_FIELD_NAME_TOO_LONG', ['#MAX#' => $maxFieldName]),
                        HttpStatus::BAD_REQUEST
                    );
                }
                if (is_string($item) && strlen($item) > $maxStringValue) {
                    throw new ApiException(
                        (string)Loc::getMessage('UPLAB_API.ERROR_VALUE_TOO_LONG', ['#MAX#' => $maxStringValue]),
                        HttpStatus::BAD_REQUEST
                    );
                }
                if (is_array($item)) {
                    $walk($item, $depth + 1);
                }
            }
        };

        $walk($value, 1);
    }

    /**
     * Проверить сортировку: число полей, имена полей и допустимость направлений.
     *
     * Направление сверяется со {@see self::ORDER_DIRECTIONS} без учёта регистра и пробелов,
     * поэтому в запрос не попадает произвольная строка, которую ядро подставит в `ORDER BY`.
     *
     * @param array|null $order Сортировка вида `['SORT' => 'ASC']`; `null` — проверять нечего
     * @return void
     * @throws ApiException 400 при превышении {@see self::MAX_ORDER_FIELDS},
     *                      пустом/слишком длинном имени поля или неизвестном направлении
     */
    public static function assertOrder(?array $order): void
    {
        if ($order === null) {
            return;
        }

        if (count($order) > static::MAX_ORDER_FIELDS) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_ORDER_TOO_MANY_FIELDS', ['#MAX#' => static::MAX_ORDER_FIELDS]),
                HttpStatus::BAD_REQUEST
            );
        }

        foreach ($order as $field => $direction) {
            if (!is_string($field) || $field === '' || strlen($field) > static::MAX_FIELD_NAME_LENGTH) {
                throw new ApiException(
                    (string)Loc::getMessage('UPLAB_API.ERROR_ORDER_INVALID_FIELD'),
                    HttpStatus::BAD_REQUEST
                );
            }
            if (!is_string($direction)
                || !in_array(strtoupper(str_replace(' ', '', $direction)), static::ORDER_DIRECTIONS, true)) {
                throw new ApiException(
                    (string)Loc::getMessage(
                        'UPLAB_API.ERROR_ORDER_INVALID_DIRECTION',
                        ['#DIRECTIONS#' => implode(', ', static::ORDER_DIRECTIONS)]
                    ),
                    HttpStatus::BAD_REQUEST
                );
            }
        }
    }

    /**
     * Проверить значение, которое подставляется в путь каталога кеша.
     *
     * Коды инфоблока, HL-блока и страницы приходят из URL и участвуют в имени каталога кеша
     * ({@see \Uplab\Api\Model\Iblock\IblockService::getCacheDir()},
     * {@see \Uplab\Api\Model\Content\ContentTable::getPageData()}), поэтому в них нельзя пускать
     * разделители путей, `..` и управляющие символы: иначе запрос управляет тем, куда пишется
     * и откуда читается кеш.
     *
     * @param string $value Проверяемое значение (код инфоблока, HL-блока, страницы)
     * @param string $name Название параметра для текста ошибки
     * @return void
     * @throws ApiException 400, если значение пустое, длиннее {@see static::MAX_FIELD_NAME_LENGTH}
     *                      символов, содержит `..`, слэш, обратный слэш или управляющий символ
     */
    public static function assertCacheSegment(string $value, string $name = 'resource code'): void
    {
        if ($value === '' || strlen($value) > static::MAX_FIELD_NAME_LENGTH || str_contains($value, '..')
            || preg_match('~[\\\\/\x00-\x1F\x7F]~', $value)) {
            throw new ApiException(
                (string)Loc::getMessage('UPLAB_API.ERROR_INVALID_RESOURCE_CODE', ['#NAME#' => $name]),
                HttpStatus::BAD_REQUEST
            );
        }
    }
}
