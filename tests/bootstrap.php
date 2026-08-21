<?php

/**
 * Bootstrap юнит-тестов: тесты запускаются БЕЗ ядра Bitrix.
 *
 * Классы модуля обращаются к API Bitrix, поэтому здесь объявлены минимальные заглушки —
 * ровно то, что нужно для загрузки тестируемых классов (константы, статические методы,
 * вызываемые в конструкторе). Всё, что делает запросы к БД, вынесено в модуле в отдельные
 * методы-обёртки и подменяется в тестовых дублёрах — заглушки ниже к БД не обращаются.
 *
 * Файл предназначен ТОЛЬКО для CLI-запуска PHPUnit на машине разработчика:
 *   - при запуске не из CLI выполнение прерывается;
 *   - каждая заглушка объявляется только при отсутствии настоящего класса, поэтому
 *     подключение файла в окружении с загруженным ядром не приводит к конфликту объявлений.
 */

namespace {
    if (PHP_SAPI !== 'cli') {
        die('tests/bootstrap.php предназначен только для запуска юнит-тестов из CLI.');
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, 'Не найден vendor/autoload.php — выполните composer install.' . PHP_EOL);
        exit(1);
    }
    require_once $autoload;

    if (!defined('LANGUAGE_ID')) {
        define('LANGUAGE_ID', 'ru');
    }
    if (!defined('SITE_ID')) {
        define('SITE_ID', 's1');
    }

    /**
     * CurrentUser::get() забирает пользователя только если это экземпляр CUser,
     * поэтому тестовые дублёры пользователя наследуют эту заглушку.
     */
    if (!class_exists(\CUser::class, false)) {
        class CUser
        {
            public function GetUserGroupArray(): array
            {
                return [2];
            }

            public function isAdmin(): bool
            {
                return false;
            }

            public function getId()
            {
                return null;
            }
        }
    }

    /**
     * Режим прав инфоблока читается через IblockHelper::fetchIblockRightsMode();
     * в тестах значение задаётся статикой.
     */
    if (!class_exists(\CIBlock::class, false)) {
        class CIBlock
        {
            /** @var array<int, string> */
            public static array $rightsMode = [];

            public static function GetArrayByID($id, $field = '')
            {
                if ($field === 'RIGHTS_MODE') {
                    return self::$rightsMode[(int)$id] ?? 'S';
                }

                throw new \LogicException('Обращение к CIBlock в юнит-тестах не ожидается.');
            }
        }
    }

    if (!class_exists(\CIBlockRights::class, false)) {
        class CIBlockRights
        {
            public const PUBLIC_READ = 'element_read';
        }
    }
}

namespace Bitrix\Main {
    if (!class_exists(Loader::class, false)) {
        class Loader
        {
            public static function includeModule(string $moduleName): bool
            {
                return true;
            }
        }
    }

    if (!class_exists(Error::class, false)) {
        class Error
        {
            public function __construct(private string $message, private $code = 0)
            {
            }

            public function getMessage(): string
            {
                return $this->message;
            }

            public function getCode()
            {
                return $this->code;
            }
        }
    }

    if (!class_exists(EventResult::class, false)) {
        class EventResult
        {
            public const UNDEFINED = -1;
            public const SUCCESS = 0;
            public const ERROR = 1;

            public function __construct(
                private int $type = self::UNDEFINED,
                private $parameters = null,
                private ?string $moduleId = null,
                private $filter = null
            ) {
            }

            public function getType(): int
            {
                return $this->type;
            }
        }
    }

    if (!class_exists(Event::class, false)) {
        class Event
        {
            public function __construct(private string $moduleId = '', private string $type = '', private array $parameters = [])
            {
            }
        }
    }
}

namespace Bitrix\Main\Localization {
    if (!class_exists(Loc::class, false)) {
        class Loc
        {
            public static function getMessage(string $code, ?array $replace = null, ?string $language = null): ?string
            {
                return $code;
            }
        }
    }
}

namespace Bitrix\Iblock {
    /**
     * Только для резолвинга use-импорта: нужны константы режима прав инфоблока.
     */
    if (!class_exists(IblockTable::class, false)) {
        class IblockTable
        {
            public const RIGHTS_SIMPLE = 'S';
            public const RIGHTS_EXTENDED = 'E';
        }
    }
}

namespace Bitrix\Highloadblock {
    /**
     * Только для резолвинга use-импорта. Любое обращение — ошибка теста: выборки
     * справочника подменяются через IblockProperty::fetchDirectoryItems().
     */
    if (!class_exists(HighloadBlockTable::class, false)) {
        class HighloadBlockTable
        {
            public static function getById($id)
            {
                throw new \LogicException('Обращение к HighloadBlockTable в юнит-тестах не ожидается.');
            }

            public static function compileEntity($hlblock)
            {
                throw new \LogicException('Обращение к HighloadBlockTable в юнит-тестах не ожидается.');
            }

            public static function getList(array $parameters = [])
            {
                throw new \LogicException('Обращение к HighloadBlockTable в юнит-тестах не ожидается.');
            }
        }
    }

    /**
     * Только для резолвинга use-импорта: права HL-блока читаются через обёртки
     * HlblockService::fetchReadAccessCodes()/fetchUserOperations(), они и подменяются.
     */
    if (!class_exists(HighloadBlockRightsTable::class, false)) {
        class HighloadBlockRightsTable
        {
            public static function getList(array $parameters = [])
            {
                throw new \LogicException('Обращение к HighloadBlockRightsTable в юнит-тестах не ожидается.');
            }

            public static function getOperationsName($hlId): array
            {
                throw new \LogicException('Обращение к HighloadBlockRightsTable в юнит-тестах не ожидается.');
            }
        }
    }
}

namespace Bitrix\Main {
    if (!class_exists(HttpRequest::class, false)) {
        /**
         * Значения задаются статикой: фильтр доступа читает только Origin и REMOTE_ADDR.
         */
        class HttpRequest
        {
            /** @var array<string, string> */
            public static array $headers = [];
            public static string $remoteAddress = '';

            public function getHeader($name)
            {
                return self::$headers[strtolower((string)$name)] ?? null;
            }

            public function getRemoteAddress()
            {
                return self::$remoteAddress;
            }
        }
    }

    if (!class_exists(Context::class, false)) {
        class Context
        {
            public static function getCurrent(): self
            {
                return new self();
            }

            public function getRequest(): HttpRequest
            {
                return new HttpRequest();
            }
        }
    }
}

namespace Bitrix\Main\Config {
    if (!class_exists(Option::class, false)) {
        class Option
        {
            /** @var array<string, string> */
            public static array $values = [];

            public static function get($moduleId, $name, $default = '', $siteId = false)
            {
                return self::$values[$name] ?? $default;
            }
        }
    }
}

namespace Bitrix\Main\Engine\ActionFilter {
    if (!class_exists(Base::class, false)) {
        abstract class Base
        {
            /** @var \Bitrix\Main\Error[] */
            private array $errors = [];

            public function addError(\Bitrix\Main\Error $error): void
            {
                $this->errors[] = $error;
            }

            /**
             * @return \Bitrix\Main\Error[]
             */
            public function getErrors(): array
            {
                return $this->errors;
            }

            public function getAction()
            {
                return null;
            }

            public function listAllowedScopes(): array
            {
                return ['ajax'];
            }
        }
    }
}

namespace Bitrix\Main\Engine {
    /**
     * Только для загрузки наследников BaseController: тела методов ядра в тестах не нужны.
     */
    if (!class_exists(Controller::class, false)) {
        abstract class Controller
        {
        }
    }


    if (!class_exists(CurrentUser::class, false)) {
        class CurrentUser
        {
            private ?\CUser $cuser = null;

            public static function get(): self
            {
                $self = new self();
                if (($GLOBALS['USER'] ?? null) instanceof \CUser) {
                    $self->cuser = $GLOBALS['USER'];
                }

                return $self;
            }

            public function getId()
            {
                return $this->cuser?->getId();
            }

            public function getUserGroups(): array
            {
                $groups = $this->cuser?->GetUserGroupArray();

                return is_array($groups) ? $groups : [];
            }

            public function isAdmin(): bool
            {
                return (bool)$this->cuser?->isAdmin();
            }
        }
    }
}
