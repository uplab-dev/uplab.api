# Changelog

## [1.12.1] - 2026-08-25

> **Версия 1.12.0 отозвана — устанавливать её нельзя.** В ней отсутствовал импорт класса, и любой
> запрос к эндпоинтам инфоблоков и статических страниц завершался ошибкой. Обновляйтесь сразу
> на 1.12.1; если 1.12.0 уже установлена — замените её, откат к 1.11.0 не нужен.

Поведение API по умолчанию не изменилось: значения лимитов, коды ответов и формат тела прежние.

### Fixed

- `Class "Uplab\Api\Model\Iblock\BaseConfig" not found` на всех эндпоинтах инфоблоков и статических страниц: в [lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php) отсутствовал импорт `Uplab\Api\Utils\Config\BaseConfig`, из-за чего вызов `BaseConfig::assertCacheSegment()` в `getIblockByCode()` разрешался в текущий неймспейс. Регрессия 1.12.0.
- Пустой текст ошибки в админке: `admin/uplab_api_content_page_edit.php` выводил сообщение по ключу `MAIN_MODULE_NOT_INSTALLED`, которого нет ни в модуле, ни в ядре, к тому же до загрузки языкового файла. Заменено на собственный ключ `UPLAB_API_MODULE_NOT_INSTALLED`, загрузка сообщений перенесена выше проверки.

### Added

- **Лимиты запросов переопределяются на проекте.** Проверки обращаются к константам через `static::`, поэтому достаточно унаследовать конфиг и переопределить константу — тела проверок копировать не нужно. Подключение наследника — фабрикой класса конфига в контроллере (`get*ConfigClass()`): `IblockController` (7 фабрик), `HlblockController` (3), `PageController`, `MenuController`. Объект создаётся общим `BaseController::createConfig()`.
- Переопределяемые лимиты там, где объекта конфига нет: `Search::getMaxLimit()`, `Search::getMaxOffset()` ([lib/Model/Search.php](lib/Model/Search.php)), `ContentController::getMaxPageSize()`, `getMaxOffset()`, `getMaxBulkIds()` ([lib/Controller/ContentController.php](lib/Controller/ContentController.php)).
- Константы `BaseConfig::MAX_FIELD_NAME_LENGTH` (`128`) и `BaseConfig::MAX_STRING_VALUE_LENGTH` (`4096`) — раньше эти длины были числами внутри проверок и переопределению не поддавались.
- `HttpStatus::PAYLOAD_TOO_LARGE` (`413`) — статус для превышения размера тела запроса.
- Разделы «Переопределение лимитов запросов» и «Тексты ошибок» в README и во встроенной документации админки ([lib/Admin/Docs.php](lib/Admin/Docs.php)).
- Юнит-тесты: переопределение лимитов наследником (включая ужесточение), подмена класса конфига фабрикой контроллера, ключ и подстановка лимита в сообщении об ошибке, статус из `HttpStatus` ([tests/Unit/Security/RequestLimitsTest.php](tests/Unit/Security/RequestLimitsTest.php)) — 105 тестов вместо 92.

### Changed

- **Сообщения ошибок валидации — из языковых сообщений модуля**, а не строками в коде: 22 новых ключа `UPLAB_API.ERROR_*` в [lang/ru/include.php](lang/ru/include.php) и [lang/en/include.php](lang/en/include.php), лимит подставляется через `#MAX#`. Тексты стали локализованными и переопределяются на проекте штатным механизмом Битрикс. Формулировки изменились — если фронтенд сравнивал `detail` с английской строкой (например `limit must be between 1 and 500`), опирайтесь на HTTP-код, а не на текст.
- **HTTP-статусы задаются кейсами `HttpStatus`**, а не числами: `ApiException` принимает `HttpStatus|int` (числа по-прежнему работают) и отдаёт `int` из `getHttpStatus()` ([lib/Exception/ApiException.php](lib/Exception/ApiException.php)).
- Проверки лимитов используют `static::` вместо `self::`/`BaseConfig::` — в [lib/Utils/Config/BaseConfig.php](lib/Utils/Config/BaseConfig.php) и трейтах конфигов.
- `BaseConfig::assertArrayComplexity()`: значения по умолчанию параметров `$maxItems` и `$maxDepth` заменены на `null` (язык запрещает `static::` в значении по умолчанию), лимиты разрешаются в теле метода. Вызовы без аргументов и с явными числами работают как раньше.
- PHPDoc: описаны `BaseConfig` (класс, все константы, свойства и методы с `@throws`), `HttpStatus` (класс и каждый кейс — где применяется) и `ApiException`.

## [1.12.0] - 2026-08-21 — ОТОЗВАНА, НЕ УСТАНАВЛИВАТЬ

> **Релиз неработоспособен.** В `lib/Model/Iblock/IblockService.php` не был импортирован класс
> `Uplab\Api\Utils\Config\BaseConfig`, поэтому `getIblockByCode()` падал с
> `Class "Uplab\Api\Model\Iblock\BaseConfig" not found`. Затронуты все эндпоинты `/api/iblock/*`
> и `/api/pages/*` — то есть основная часть API. Исправлено в 1.12.1, в остальном содержимое
> релиза не менялось: описание изменений ниже относится и к 1.12.1.

Подготовка модуля к публикации: проверка прав HL-блоков, разделение кеша по правам, ограничения на входные данные, штатная защита административных операций.

### Обновление с 1.11.0

Формат ответов, имена полей, список endpoint-ов, события и точки расширения не менялись. Изменения касаются входных параметров и настроек доступа — ниже всё, что нужно проверить на стороне фронтенда и на сайте.

**Параметры запроса (для фронтенда).** Нарушение возвращает `400`, тело больше 64 КиБ — `413`:

| Параметр | Было в 1.11.0 | Стало |
|---|---|---|
| `limit` не передан | `10` | `10` — без изменений |
| `limit: 0` | без `LIMIT` в запросе, то есть **вся** выборка | `400` — используйте постраничный обход |
| `limit` больше `500` | уходил в выборку как есть | `400` |
| `offset` больше `100000` | уходил как есть | `400` |
| `filter` | без ограничений | не более 1000 элементов суммарно (с учётом вложенных), глубина массивов условий не более 5, строковое значение не более 4096 символов |
| `filter` с `LOGIC` на верхнем уровне | превращал условия в `OR` | ключ вырезается, условия склеиваются через `AND` |
| `select`, `propertyCode` | без ограничений | не более 100 полей |
| `order` | уходил как есть | не более 10 полей; направления `ASC`, `DESC`, `ASC,NULLS`, `DESC,NULLS`, `NULLS,ASC`, `NULLS,DESC` |
| `siteId: ""` | фильтр по сайту не применялся | без изменений |
| Некорректный JSON при `Content-Type: application/json` | молча использовались значения по умолчанию | `400` |
| Тело формы (`x-www-form-urlencoded`) | как JSON не разбиралось | без изменений — отправка веб-формы работает как раньше |

Если фронтенд строил OR-условия через `LOGIC` на верхнем уровне `filter`, перенесите их во вложенную группу — она присоединяется к запросу через `AND` и работает как прежде:

```json
{ "filter": { "0": { "LOGIC": "OR", "NAME": "a", "CODE": "b" } } }
```

**Настройки и права (на стороне сайта).**

1. **Доступ к API.** По умолчанию API открыт: `API_ACCESS_ENABLED = N`, и при выключенном чекбоксе запросы не проверяются. Если проверка включена, изменилась логика: заданные списки теперь работают по «ИЛИ» — браузерный запрос проходит по `Origin`, серверный (без заголовка) — по IP-списку. Раньше запрос без `Origin` при пустом IP-списке проходил всегда, что позволяло обойти ограничение снятием заголовка. **Что сделать:** если проверка включена и заполнены только домены, добавьте в `API_ALLOWED_IPS` адреса серверов SSR и интеграций, иначе они получат `403`. Если оба списка пусты, ограничений нет — поведение как раньше.
2. **HL-блоки.** Блок отдаётся публично, пока на нём никому не выдана операция `hl_element_read`. Права только на изменение и удаление (задача с буквой `W`) публичность не отбирают. **Что сделать:** если на используемом в API блоке право чтения уже выдано, выдайте чтение группе «Все пользователи» (код `G2`) — или снимите все записи с правом чтения. Подробнее — в README, раздел «Доступ к данным HL-блоков».
3. **Меню и связанные элементы.** Вторичные выборки меню и значения свойств-привязок теперь идут с `CHECK_PERMISSIONS=Y` и `MIN_PERMISSION=PUBLIC_READ`. Если инфоблок меню или инфоблок-справочник закрыт для группы «Все пользователи», часть пунктов и значений перестанет попадать в ответ — раньше они отдавались в обход прав. **Что сделать:** проверить права инфоблоков, участвующих в меню и в привязках.
4. **Кеш.** Ключи кеша изменились, поэтому после обновления первый запрос каждой комбинации параметров идёт в БД. Данные не теряются; на нагруженном сайте лучше обновляться вне пика. Для инфоблоков в расширенном режиме прав (`RIGHTS_MODE = E`) добавляется по копии кеша на авторизованного пользователя, анонимный трафик не дробится.

### Added

* Ограничения сложности входных данных: JSON до 64 КиБ и глубины 32, `limit` до 500, `offset` до 100000, массивы условий до 1000 элементов суммарно и глубины 5, `select`/`propertyCode` до 100 полей, `order` до 10 полей. Нарушения возвращают `400`, слишком большое тело — `413` ([lib/Utils/Config/BaseConfig.php](lib/Utils/Config/BaseConfig.php), [lib/Controller/BaseController.php](lib/Controller/BaseController.php)).
* Публичная инструкция установки в README и политика приватного сообщения об уязвимостях в [SECURITY.md](SECURITY.md).
* README: бейджи Packagist, ссылки на GitHub и Composer, раздел «Гарантии и ответственность» — модуль предоставляется «как есть», риски и последствия использования на стороне пользователя. В `composer.json` добавлены `homepage` и `support`.
* Установка через Composer: добавлена зависимость `composer/installers`, поэтому пакет раскладывается в каталог модулей Битрикса, а не в `vendor/`. Путь задаёт проект-потребитель ключом `extra.installer-paths` (или `extra.bitrix-dir`); без настройки модуль встанет в `bitrix/modules/uplab.api` — порядок описан в README, раздел «Установка».
* Юнит-тесты фильтра доступа, прав HL-блоков, контекста групп пользователя и ограничений запроса ([tests/Unit/Security](tests/Unit/Security)).

### Changed

* Ключи кеша данных, зависящих от прав, включают отсортированные группы текущего пользователя — штатная схема Битрикс `CACHE_GROUPS=Y` ([lib/Security/CacheContext.php](lib/Security/CacheContext.php)).
* `ApiAccessFilter`: включённая защита с двумя пустыми списками закрывает доступ (fail-closed). Заданные списки проверяются по «ИЛИ» — браузерный запрос проходит по `Origin`, серверный без заголовка — по IP-списку ([lib/ActionFilter/ApiAccessFilter.php](lib/ActionFilter/ApiAccessFilter.php)).
* Административные операции контента получили штатные фильтры метода, авторизации и CSRF; добавление результата веб-формы ограничено методом POST ([lib/Controller/ContentController.php](lib/Controller/ContentController.php), [lib/Controller/WebFormController.php](lib/Controller/WebFormController.php)).
* Штатные API вместо суперглобалов и `global $USER`: `ApiAccessFilter` читает `Origin` и `REMOTE_ADDR` через request API, тело запроса — через `HttpRequest::getInput()`, права и группы пользователя — через `CurrentUser` ([lib/ActionFilter/ApiAccessFilter.php](lib/ActionFilter/ApiAccessFilter.php), [lib/Controller/BaseController.php](lib/Controller/BaseController.php), [lib/Security/CacheContext.php](lib/Security/CacheContext.php)).
* `order` принимает направления `ASC`, `DESC` и варианты с `NULLS` — те же, что и ядро ([lib/Utils/Config/BaseConfig.php](lib/Utils/Config/BaseConfig.php)).
* Из `composer.json` удалена закрытая GitLab-ссылка; публичный GitHub URL можно добавить после создания репозитория.
* Лицензия изменена с MIT на проприетарную ([LICENSE.txt](LICENSE.txt), `"license": "proprietary"` в `composer.json`). Безвозмездно разрешено использование Модуля на любых проектах, включая коммерческие, его изменение под нужды проектов (включая исправление ошибок) и передача — исходного или изменённого — заказчику в составе результата работ, при сохранении лицензии, указаний на авторство и пометки об изменении; плата за работы по внедрению и поддержке продажей Модуля не является. Запрещены продажа Модуля и его изменённых версий, включение в платные тиражные решения и шаблоны, размещение от своего имени в каталогах решений, распространение под другой лицензией. Ограничения адресованы третьим лицам и не ограничивают правообладателя. Права, полученные на предыдущие версии под MIT, изменением не затрагиваются.

### Security

* Управляющие ключи фильтра из тела запроса больше не меняют способ сборки выборки инфоблока: `LOGIC`, `PERMISSIONS_BY`, `SHOW_HISTORY`, `SHOW_NEW`, `SHOW_BP_NEW`, `CHECK_BP_PERMISSIONS`, `CHECK_PERMISSIONS` и `MIN_PERMISSION` вырезаются с верхнего уровня переданного `filter` ([lib/Model/Iblock/IblockHelper.php](lib/Model/Iblock/IblockHelper.php)). Порядок слияния защищал только одноимённые ключи, поэтому `{"filter":{"LOGIC":"OR","ID":123}}` снимал привязку к инфоблоку, сайту и `ACTIVE=Y`, `PERMISSIONS_BY` заставлял ядро проверять права от имени другого пользователя, а `SHOW_HISTORY`/`SHOW_NEW` отдавали черновики и историю документооборота. Затронуты были все выборки элементов и разделов, страницы, меню и значения умного фильтра.
* Значение сериализованной опции и имя поля на странице настроек модуля экранируются через `htmlspecialcharsbx()` ([lib/Utils/Options/OptionsBase.php](lib/Utils/Options/OptionsBase.php)).
* `ContentTable::getPageData()` проверяет код страницы и `siteId` перед подстановкой в путь кеша — проверка больше не зависит только от контроллера ([lib/Model/Content/ContentTable.php](lib/Model/Content/ContentTable.php)).
* Endpoint-ы HL-блоков учитывают штатные права блока: блок публичен, пока на нём никому не выдана операция `hl_element_read`; после выдачи читают только обладатели права и администратор, остальные получают `403`. Проверка идёт по кодам доступа (`G<ID>`, `AU`, `U<ID>`), поэтому работает и для анонимного пользователя, для которого штатный `getOperationsName()` всегда пуст ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* Закрыта возможность получить привилегированный ответ инфоблока, меню, страниц, поиска или карты сайта из общего кеша другой группы пользователя.
* Для инфоблоков в расширенном режиме прав (`RIGHTS_MODE = E`) ключ кеша включает и ID пользователя: там права выдаются персонально (`U<ID>`), по отделу и на «создателя» (`CR`), поэтому набора групп недостаточно — пользователь с доступом мог прогреть кеш, а другой пользователь с теми же группами получить его без проверки прав. В простом режиме ключ остаётся групповым, чтобы кеш не дробился по каждому авторизованному пользователю ([lib/Model/Iblock/IblockHelper.php](lib/Model/Iblock/IblockHelper.php), [lib/Security/CacheContext.php](lib/Security/CacheContext.php)).
* Вторичные выборки элементов и разделов меню теперь выполняются с `CHECK_PERMISSIONS=Y` и `MIN_PERMISSION=PUBLIC_READ` ([lib/Model/Iblock/MenuService.php](lib/Model/Iblock/MenuService.php)).
* Значение кода страницы в административном JavaScript кодируется через `json_encode` с `JSON_HEX_*`, что исключает сохранённый XSS через символьный код ([lib/Event/CCustomOETabs.php](lib/Event/CCustomOETabs.php)).
* Коды ресурсов, попадающие в пути кеша, проверяются на обход каталога, управляющие символы и чрезмерную длину.

### Fixed

* Встроенная документация (**Сервисы → API Документация**): в верхней навигации при наведении пропало подчёркивание ссылки и иконки — базовое `text-decoration: none` слабее глобального правила `a:hover` в админке, поэтому свойство продублировано в состояниях. Добавлены состояния нажатия (`:active`) для пунктов навигации, ссылок-якорей, кнопок панели и заголовков блоков, а также видимый фокус для клавиатурной навигации ([lib/Admin/Docs.php](lib/Admin/Docs.php)).
* `ApiAccessFilter` больше нельзя обойти удалением клиентского заголовка `Origin`: запрос без заголовка обязан пройти по IP-списку.
* Некорректный JSON и скалярное JSON-тело возвращают `400`, если клиент объявил `Content-Type` с JSON; тело формы (`x-www-form-urlencoded`, `multipart/form-data`) как JSON не разбирается, а при необъявленном или не-JSON типе неразобранное тело игнорируется (валидный JSON принимается и без заголовка) — отправка веб-формы (`POST /api/forms/{code}/add`) продолжает принимать поля из запроса ([lib/Controller/BaseController.php](lib/Controller/BaseController.php)).
* Убраны предупреждения PHP 8.0+ о необязательных аргументах перед обязательными в методах детального раздела инфоблока.

## [1.11.0] - 2026-08-12

Точки расширения умного фильтра: значения собираются по карте «тип свойства → метод-резолвер», проект добавляет свой `USER_TYPE` или подменяет штатный тип одним методом вместо копирования всего `getPropertyValues()`. Заодно из модуля убрана поддержка типов сторонего модуля `asd.iblock`. Появились юнит-тесты, работающие без ядра Bitrix.

### ⚠️ Обратная совместимость

**Удалена поддержка типов свойств модуля `asd.iblock`** (`SASDCheckboxNum`, `SASDSection`) — вместе с ней из фильтра ушёл тип `N`: числовые свойства теперь в умный фильтр не попадают. Модуль поддерживает только штатные типы Bitrix (`L`, `S`, `E`, `G`).

Проектам, где эти свойства участвуют в фильтре, нужно добавить их в наследнике `IblockProperty` и подключить его через `IblockService::getPropertyModel()`. Готовый класс целиком — в README, раздел «Пример: свои типы свойств (модуль `asd.iblock`)»; он же продублирован в тесте [tests/Unit/Iblock/ProjectCustomTypeExampleTest.php](tests/Unit/Iblock/ProjectCustomTypeExampleTest.php). Без этой правки свойства `SASDCheckboxNum` / `SASDSection` просто исчезнут из ответа `/api/iblock/{code}/filter` (ошибок не будет).

Удалённые методы (использовались только этими типами): `getNumberValues()`, `buildCheckboxNumItems()`, `getActiveSectionsOrder()`, `fetchActiveSections()`. Если проект их переопределял — перенесите логику в свой резолвер по примеру из README.

**Отдельно для проектов, которые переопределяли `getPropertyValues()` целиком** (типичный способ поддержать свои типы до 1.11.0): метод по-прежнему вызывается и его сигнатура не менялась, но список типов, попадающих в выборку, теперь берётся из карты `getValueResolvers()` — свойства типа `N` отфильтровываются **до** вызова `getPropertyValues()`, поэтому код внутри него для них не сработает, а свойства молча исчезнут из фильтра. Правильный путь — перейти на резолверы (пример в README); минимальная правка — вернуть тип в карту:

```php
protected function getValueResolvers(): array
{
    return parent::getValueResolvers() + ['N' => 'getAsdNumberValues'];
}
```

Указывать в карте сам `getPropertyValues` нельзя — диспетчер вызовет его повторно и получится бесконечная рекурсия.

### Added

* Карта резолверов `IblockProperty::getValueResolvers()` (`PROPERTY_TYPE` → метод) и резолверы штатных типов: `getEnumValues()` (`L`), `getStringValues()` (`S`, внутри — `getDirectoryValues()` и `getPlainStringValues()`), `getElementLinkValues()` (`E`), `getSectionLinkValues()` (`G`). Сигнатура резолвера — `(array $arProperty, array $customFilter): array` ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Отдельные точки расширения: `buildPropertyFilter()` (фильтр запроса без условия по самому свойству), `logPropertyError()` (запись ошибки резолвера в Журнал событий), `resolveDirectoryNameFields()` / `buildDirectoryItems()` / `buildPlainStringItems()` (преобразование выбранных данных) ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Точечная настройка под конкретное свойство конкретного инфоблока — без копирования тела резолвера. Хуки сортировки `getEnumOrder()`, `getSectionsOrder()`, `getElementsOrder()`, `getDirectoryOrder()` и хуки формата значений `buildEnumItems()`, `buildSectionItems()`, `buildElementItems()` — все получают `$arProperty`. Прежде жёсткие `ORDER BY` внутри выборок стали параметром обёрток ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Хуки полей выборки `getSectionsSelect()` и `getElementsSelect()` — позволяют добавить свои поля (в том числе `PROPERTY_<КОД>` связанного элемента) и использовать их в `buildSectionItems()` / `buildElementItems()`, не копируя обёртки выборки ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Юнит-тесты-примеры проектного расширения: типы `asd.iblock`, строковое свойство со своим `USER_TYPE` и свойство-привязка `E` со своим названием и сортировкой ([tests/Unit/Iblock/ProjectCustomTypeExampleTest.php](tests/Unit/Iblock/ProjectCustomTypeExampleTest.php)). Те же примеры приведены в README и встроенной документации, поэтому разойтись с кодом модуля они не могут.
* Контекст текущего свойства `getCurrentProperty()` — заполнен на время работы резолвера и сбрасывается после (в том числе при исключении). Нужен там, где мета-данные свойства не приходят параметром, прежде всего в `buildElementsFilter()`, сигнатуру которого менять нельзя без поломки наследников из 1.10.0 ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Обёртки над API Bitrix как точки подмены в тестах: `fetchPropertyValueRows()`, `fetchPropertyValueRowsWithRaw()`, `fetchSections()`, `fetchElements()`, `fetchActiveSections()`, `fetchEnumValues()`, `fetchHlblockFields()`, `fetchDirectoryItems()` ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* `IblockService::getPropertyModel()` — фабрика модели значений фильтра **без параметров**, по образцу `getModelClass()` в контроллерах. Раньше `IblockProperty` создавался в экшене напрямую, и подключить своего наследника можно было только переопределив весь `getFilterElementsAction()`. Инфоблок, сайт и раздел задаются сеттерами `IblockProperty::setIblockId()` / `setSiteId()` / `setSectionId()` (fluent), а параметры конструктора стали необязательными — новые данные модели не потребуют менять сигнатуру фабрики и не сломают проектные наследники ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php), [lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Юнит-тесты (PHPUnit ^10.5, dev-зависимость) — запускаются локально **без ядра Bitrix**: `composer test`. 31 тест на фильтры выборки, диспетчеризацию типов, расширение карты наследником и сборку значений по каждому типу ([phpunit.xml.dist](phpunit.xml.dist), [tests/bootstrap.php](tests/bootstrap.php), [tests/Unit/Iblock](tests/Unit/Iblock)).
* Документация точек расширения фильтра — раздел «Расширение через наследование (`IblockProperty`)» в README, аналогичный раздел на странице «Инфоблоки» встроенной документации, инструкция по юнит-тестам в README и [tests/README.md](tests/README.md) ([README.md](README.md), [lib/Admin/Docs.php](lib/Admin/Docs.php)).

### Changed

* `IblockProperty::getPropertyValues()` вместо `switch` по типу стал диспетчером: приводит `CODE`, строит фильтр, вызывает резолвер из карты и перехватывает исключение (логирование ошибки — прежнее). Метод сохранён, сигнатура не менялась — переопределения в проектах-наследниках продолжают работать ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Список типов свойств, попадающих в фильтр, выводится из карты резолверов вместо отдельного массива `["L","N","S","E","G"]`. Расхождение двух списков, из-за которого свойства типа «Список» выпадали из фильтра до 1.10.0, стало невозможным ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* `composer.json`: добавлены `require-dev` (PHPUnit), `autoload-dev` (PSR-4 для `lib/` и `tests/`) и скрипты `test` / `test:coverage`. На прод (`composer install --no-dev`) ничего из этого не попадает ([composer.json](composer.json)).
* `getIDPropertyItems()` больше не дублирует тело `getGroupPropertyItems()` — делегирует ему и возвращает ключи. Поведение и сигнатура прежние ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

### Removed

* `CLAUDE.md` (внутренняя инструкция для разработки) помечен `export-ignore` — в дистрибутив пакета и на прод больше не попадает; в репозитории остаётся ([.gitattributes](.gitattributes)).
* `composer.lock` убран из репозитория и добавлен в `.gitignore`: prod-зависимостей у модуля нет, а lock пакета потребители не читают (composer использует lock корневого проекта), поэтому фиксировал он только версии dev-инструментов. Локально файл создаётся сам при `composer install` ([.gitignore](.gitignore), [README.md](README.md)).

### Security

* **Проверка прав во вторичных выборках фильтра.** Названия связанных элементов и разделов (свойства `E` и `G`) выбирались фильтром `['ID', 'ACTIVE' => 'Y', 'IBLOCK_ID']` без `CHECK_PERMISSIONS` — если инфоблок-справочник закрыт от чтения, его названия всё равно попадали в фасеты (ID при этом отбирались с правами, поэтому утечка ограничивалась названиями). Добавлены `CHECK_PERMISSIONS=Y` и `MIN_PERMISSION=PUBLIC_READ` через новые переопределяемые хуки `getLinkedSectionsFilter()` / `getLinkedElementsFilter()`. Существовало до рефакторинга, security-фикс 1.7.0 эти выборки не покрывал ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Dev-инфраструктура тестов изолирована от прода: `tests/bootstrap.php` прерывает выполнение вне CLI и объявляет заглушки Bitrix только при отсутствии настоящих классов (подключение при загруженном ядре не подменяет `Loader`/`Loc`/`CIBlockRights` и не даёт конфликта объявлений); в `tests/` добавлен `.htaccess` (`Deny from All`) как дополнение к штатному запрету `/bitrix/modules/`; dev-файлы (`tests/`, `phpunit.xml.dist`, CI-конфиг) помечены `export-ignore` в `.gitattributes` и не попадают в дистрибутив пакета ([tests/bootstrap.php](tests/bootstrap.php), [tests/.htaccess](tests/.htaccess), [.gitattributes](.gitattributes)).
* Ошибка внутри резолвера перехватывается как `\Throwable`, а не `\Exception`: PHP-ошибка в проектном резолвере (`TypeError`, обращение к несуществующему методу и т.п.) теперь тоже пишется в Журнал событий и гасит только своё свойство, а не весь ответ фильтра ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Контекст `currentProperty` восстанавливает предыдущее значение вместо обнуления — вложенный сбор значений другого свойства из проектного резолвера не затирает внешний контекст ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Резолвер из карты вызывается через `is_callable()` вместо `method_exists()`: метод, недоступный базовому классу (например объявленный в наследнике как `private`), раньше дал бы `Error`, который не перехватывается и роняет эндпоинт фильтра целиком — теперь свойство просто не попадает в фильтр. В phpdoc `getValueResolvers()` зафиксировано, что значения карты — только имена методов класса (имя вызывается динамически) ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

### Fixed

* Кеш ответа фильтра не сбрасывался при правке записей HL-блока-справочника: для свойств с `USER_TYPE = directory` регистрируется тег `hlblock_id_{ID}` (`HlblockService::getHlBlockTag()`), который уже сбрасывает существующий обработчик админки в `Uplab\Api\Event\EventManager`. Раньше фильтр отдавал старые названия до истечения суток. Для произвольных тегов добавлен `addCacheTag()` ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Свойство с `USER_TYPE = SASDCheckboxNum` без `USER_TYPE_SETTINGS['VIEW']` приводило к обходу `null` в `foreach` (PHP-ошибка вместо пустого набора значений) — теперь такое свойство просто не попадает в фильтр ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Пустой `$arProperty` проверяется до обращения к `CODE` — раньше обращение шло первым и давало warning ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

## [1.10.0] - 2026-08-12

Умный фильтр (`ANY /api/iblock/{iblockCode}/filter`): переданный `filter` наконец влияет на набор значений, значения свойств типа «Список» больше не теряются, а сборка значений строковых свойств проверяет права.

### ⚠️ Изменения поведения

* Эндпоинт `/api/iblock/{iblockCode}/filter` теперь учитывает переданный `filter` — набор значений сужается по отфильтрованным элементам (раньше параметр парсился и игнорировался). Клиенты, передававшие `filter` в этот эндпоинт и рассчитывавшие на полный набор значений, получат сужённый.
* Свойства типа «Список» (`L`) теперь отдают значения и появляются в ответе фильтра — раньше `items` у них всегда был пустым и свойство молча выпадало из фильтра. В фильтре на фронте появятся новые свойства.
* Значения строковых свойств собираются с проверкой прав — значения, встречающиеся только в недоступных текущему пользователю элементах, из фильтра исчезнут.
* Ключ кеша фильтра учитывает переданный `filter` (было реализовано, но не задействовано, т.к. фильтр не доходил до модели) → после обновления однократное перестроение кеша ответов фильтра.
* Для наследников `IblockProperty`: сигнатуры `getGroupPropertyItems()` и `getIDPropertyItems()` не менялись, переопределения продолжают работать. Изменилось два момента: базовые реализации берут фильтр из нового `buildElementsFilter()`, а `getGroupPropertyItems()` в ветке `SASDSection` теперь вызывается со вторым аргументом (переданный `filter`) — если переопределение его игнорирует, значения этого свойства фильтром сужаться не будут.

### Added

* Поддержка свойств типа «Список» (`L`) в умном фильтре: `case 'L'` собирает фактически использованные значения перечисления и разворачивает их через `CIBlockPropertyEnum::GetList()` с сортировкой `SORT`/`VALUE`; `value` — `ENUM_ID`, `text` — название значения. Новый хук `getEnumPropertyItems()` ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Раздел документации по эндпоинту фильтра: сужение значений переданным `filter`, исключение условия по самому свойству, проверка прав, поддерживаемые типы свойств и формат `items` ([README.md](README.md), [lib/Admin/Docs.php](lib/Admin/Docs.php)).
* Тесты коллекции: проверка структуры ответа фильтра, значений свойств типа `L` и запрос «Данные фильтра — сужение переданным filter», сравнивающий наборы значений с базовым запросом ([tests/uplab.api.postman_collection.json](tests/uplab.api.postman_collection.json)).
* Документация ключей `BREADCRUMBS` и `SECTION_URL` в детальных ответах инфоблока (поведение существовало, но описано не было): состав цепочки для элемента (инфоблок → разделы `GetNavChain` → сам элемент без ключа `HREF`) и для раздела (инфоблок → цепочка, включая сам раздел с пустым `HREF`), источники текста (`IPROPERTY_VALUES.SECTION_PAGE_TITLE` / `ELEMENT_PAGE_TITLE` с фолбэком на `NAME`), а также где цепочка не строится — списки и `/api/pages/{code}` ([README.md](README.md), [lib/Admin/Docs.php](lib/Admin/Docs.php), новый хелпер `Docs::breadcrumbsNotes()`). Тесты: проверки структуры `BREADCRUMBS` и последнего звена для детальных элемента и раздела ([tests/uplab.api.postman_collection.json](tests/uplab.api.postman_collection.json)).
* Документация ключа `CONTENT` в ответе `ANY /api/pages/{code}` (поведение существовало, но описано не было): статический контент страницы из HL-блока `Content` подмешивается в ответ, отбор (`UF_PAGE` = `CODE` элемента, `UF_ACTIVE`, `UF_LANG = siteId`), структура `CONTENT[UF_BLOCK][UF_CODE]`, замена `#YEAR#`, отдельный кеш с тегом `update_contenttable_{UF_PAGE}` и отметка, что запрос `/api/content/page/{pageCode}` для таких страниц избыточен ([README.md](README.md), [lib/Admin/Docs.php](lib/Admin/Docs.php)). Тесты: проверка наличия и структуры `CONTENT` и запрос, сверяющий его с ответом `/api/content/page/{pageCode}` ([tests/uplab.api.postman_collection.json](tests/uplab.api.postman_collection.json)).

### Fixed

* `getFilterElementsAction()` не передавал `filter` из тела запроса в `IblockProperty::getFilterProperties()` — параметр `IblockFilterConfig` парсился и терялся, фильтр не влиял на выдачу ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).
* Внутри `getPropertyValues()` подготовленный `$customFilter` доходил только до веток `G` и `E`: свойства-справочники (`S` + `directory`) и числовые с `USER_TYPE = SASDSection` собирали значения без учёта фильтра ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* Строковые свойства без справочника собирались прямым `CIBlockElement::GetList()` с самостоятельно собранным фильтром — без `CHECK_PERMISSIONS`/`MIN_PERMISSION` и без `$customFilter`. Проверка прав, добавленная в 1.7.0, покрывала только методы-хелперы, поэтому по строковым свойствам утекали значения из недоступных элементов ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

### Changed

* Базовый фильтр выборки значений вынесен в `buildElementsFilter()` — единая точка для `getGroupPropertyItems()`, `getIDPropertyItems()`, `getEnumPropertyItems()` и ветки строковых свойств; приоритет защитных ключей над пользовательским фильтром сохранён ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

## [1.9.1] - 2026-07-13

### Added

* Добавлен [.gitlab-ci.yml](.gitlab-ci.yml) 

## [1.9.0] - 2026-07-13

Точки расширения кешируемых выборок `IblockService` (фаза 1 рефакторинга): проекты-наследники дополняют payload и ключ кеша переопределением одного хука, без копирования тел методов.

### Added

* Хуки payload в `IblockService` — вызываются внутри кешируемой области перед `endDataCache()` (поэлементные — в цикле выборки), результат попадает в кеш: `appendElementListData()` / `appendSectionListData()` (весь payload списка), `appendElementListItemData()` / `appendSectionListItemData()` (один элемент/раздел списка), `appendElementDetailData()` / `appendSectionDetailData()` (детальные). Порядок параметров единый: данные → `$rawFields` (снимок полей сразу после выборки, до преобразований для фронта — с `~`-полями и системными полями) → `$rawProperties` (для элементов: свойства из `GetProperties()` до преобразований, без повторного запроса) → `$obj` (объект строки выборки `_CIBElement`, где есть) → конфиг. Дефолтные реализации возвращают данные без изменений ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).
* Хуки ключа кеша — `getElementListCacheKeyParts()`, `getElementDetailCacheKeyParts()`, `getSectionListCacheKeyParts()`, `getSectionDetailCacheKeyParts()`: наследник добавляет к ключу входные параметры, от которых зависят его доп. данные ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).
* Документация расширения: раздел «Расширение через наследование (`IblockService`)» с описанием хуков, требований к ключу кеша и регистрации тегов — в README и на странице «Инфоблоки» встроенной документации админки ([README.md](README.md), [lib/Admin/Docs.php](lib/Admin/Docs.php)).

### Changed

* Ключи кеша четырёх выборок (`elements`, `element/code|id`, `sections`, `section/code|id`) теперь включают `static::class` — кеш проектного наследника с изменённым payload не пересекается с кешем базового класса. HTTP-поведение эндпоинтов не меняется; после обновления — **однократное** перестроение кеша этих ответов (разовый всплеск нагрузки, не поломка). Директории кеша и теги (`/iblock/{ID}`, `iblock_id_{ID}`) прежние — штатная инвалидация работает как раньше.

## [1.8.0] - 2026-07-09

Аудит безопасности и корректности: исправлены фатальные ошибки, баги кеширования и ряд PHP 8.1+ проблем.

### ⚠️ Обратная совместимость (для разработчиков проектов-наследников)

**Критично (может уронить проект при обновлении) — 1 пункт:**

Если проект унаследовал `IblockController` и **переопределил** `getSectionDetailByIdAction` с первым параметром `int $iblockCode` — при автозагрузке подкласса будет `Fatal error: Declaration ... must be compatible` (в базе тип изменён `int` → `string`, иначе символьный код инфоблока не проходил). **Фикс:** заменить в своём переопределении `int $iblockCode` → `string $iblockCode` (или убрать переопределение — базовый метод делегирует в `getModelClass()`). Наследников `IblockService` не касается — там сигнатура уже была `string`.

**Изменения поведения (не падают, но учтите):**

- **Формат тела ошибок изменился:** было — голая строка, стало — JSON `{"message": "..."}` (HTTP-статус прежний). Клиенты, читавшие текст ошибки как строку, должны брать поле `message`.
- **Внутренние ошибки: `HTTP 500` + RFC 9457** (`application/problem+json`, обобщённое сообщение) вместо прежних `200` + текст исключения. Клиенты, читавшие текст исключения или полагавшиеся на `200`, должны учитывать `500` и поле `detail`; детали — в Журнале событий. На серверах с `fastcgi_intercept_errors on` для доставки тела `500` нужен ops-твик nginx (см. README).
- **CSRF:** `POST /api/content/bulk` и `DELETE /api/content/admin/{id}` требуют `sessid` (bitrix_sessid). Серверные вызовы этих эндпоинтов — добавьте параметр.
- **Кеш:** ключи кеша изменились → после деплоя **однократное** перестроение кеша ответов (разовый всплеск нагрузки, не поломка).
- **Язык:** ключ `ACCESS_DENIED` → `UPLAB_API.ACCESS_DENIED` (если переопределяли фразу — задайте под новым ключом).
- **`ContentEvent`** сбрасывает кеш контента теперь на всех запросах, а не только в админке.
- **`OptionsBase::__construct`** теперь учитывает переданный `$moduleId` (раньше игнорировался, всегда `uplab.api`).
- **`BaseController::getJsonRequestBody()`** возвращает `array|null` (раньше мог вернуть скаляр). Тип объявления не менялся — переопределения не ломаются.
- **Множественные привязки HL** (`iblock_element`/`hlblock`) кладут в `INFO[ПОЛЕ]` массив записей — учтите в обработчиках `OnAfterHlblockConvertFieldValue`.
- **`IblockProperty::__construct`** получил необязательный 3-й параметр `?string $siteId` (безопасно — конструкторы вне LSP). Если переопределили конструктор двумя параметрами — прокиньте 3-й в `parent::__construct()`, иначе локализация названий справочников в фильтре не сработает.
- **Новый `HlblockService::getFieldsAction()`** — алиас к `getFiedlsAction()` (остаётся основным). Конфликт только если у вас уже был свой `getFieldsAction` с иной сигнатурой.
- Эндпоинты детальных разделов ИБ (`/section/code|id/...`) раньше падали — теперь работают.

### Added

* Поиск (`/api/search`): текст «ничего не найдено» (`message.title`/`message.text`) при пустом статическом контенте (`Content`/`search`/`empty`) теперь фолбэчит на языковые сообщения `UPLAB_API.SEARCH_EMPTY_TITLE` / `UPLAB_API.SEARCH_EMPTY_TEXT` (раньше отдавались пустые строки). Источник и способ переопределения задокументированы в README и `Docs.php` ([lib/Model/Search.php](lib/Model/Search.php), [lang/ru/include.php](lang/ru/include.php), [lang/en/include.php](lang/en/include.php)).
* `Uplab\Api\Exception\ApiException` — исключение для намеренных клиентских ошибок: `throw new ApiException($message, $httpStatus)` перехватывается в `BaseController` и отдаётся как RFC 9457 (`application/problem+json`) с указанным `4xx`-статусом и сообщением ([lib/Exception/ApiException.php](lib/Exception/ApiException.php)).
* Тестовая коллекция всех эндпоинтов API (Postman v2.1, импортируется и в Insomnia) с авто-проверками статусов и формата ответа, включая регрессы 1.8.0 (раздел по id с символьным кодом, множественные привязки HL, поиск с пустым телом/`limit=0`, CSRF на content) ([tests/uplab.api.postman_collection.json](tests/uplab.api.postman_collection.json), [tests/README.md](tests/README.md)).

### Security

* **Утечка внутренней информации в ответах об ошибках (+ неверный код).** Любое необработанное исключение Bitrix заворачивал в `{status:error}` с полным текстом исключения (namespace, сигнатуры методов, трейс) и HTTP 200 — пользователи видели внутреннюю архитектуру. Теперь `BaseController::getActionResponse()` перехватывает исключения экшена и отдаёт корректный REST-ответ: `HTTP 500` + тело RFC 9457 (`application/problem+json`) с обобщённым сообщением, без раскрытия внутренностей. Полные детали (класс, сообщение, файл:строка, трейс, URL) пишутся в Журнал событий (тип `UPLAB_API_INTERNAL_ERROR`) с уведомлением администратора. Дополнительно `runProcessingException`/`runProcessingError` подстраховывают исключения вне тела экшена (тоже без утечки) ([lib/Controller/BaseController.php](lib/Controller/BaseController.php), [lib/Enums/EventLogType.php](lib/Enums/EventLogType.php)). Примечание: на bitrix-env с `fastcgi_intercept_errors on` тело `5xx` подменяется HTML-страницей nginx — чтобы `problem+json` доходил на 500, перехват для `/api/` отключается на стороне nginx (см. README).
* **CSRF на мутирующих admin-эндпоинтах контента.** `POST /api/content/bulk` и `DELETE /api/content/admin/{elementId}` авторизовались только сессией и правами HL-блока, без проверки CSRF-токена — залогиненного пользователя можно было принудить к удалению/деактивации записей через межсайтовый запрос. Добавлена проверка `check_bitrix_sessid()` в обоих действиях; админ-интерфейс (`CCustomOETabs.php`) теперь передаёт `sessid` в запросах ([lib/Controller/ContentController.php](lib/Controller/ContentController.php), [lib/Event/CCustomOETabs.php](lib/Event/CCustomOETabs.php)).

### Fixed

* **Умный фильтр по справочнику (`/api/iblock/{code}/filter`): `text` подставляется построчно.** Раньше поле для названия выбиралось по факту существования (если у справочника есть `UF_NAME_<ЯЗЫК>`), и при пустом значении в записи в `text` прилетал `null`. Теперь построчный фолбэк: `UF_NAME_<ЯЗЫК>` → `UF_NAME` → `UF_XML_ID` (пустое не показываем) ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).
* **Тело ответов-ошибок — валидный JSON.** Сервисные эндпоинты под `Content-Type: application/json` отдавали в ошибках **голую строку** (напр. `Инфоблок не найден.`), из-за чего `response.json()` на клиенте падал. Теперь тело ошибки — `{"message": "..."}` (HTTP-статус 404/424 и т.п. сохранён). Затронуты пути «инфоблок/HL/форма не найдены» и «модуль не установлен» во всех сервисах ([lib/Model/ControllerAction/BaseAction.php](lib/Model/ControllerAction/BaseAction.php), [lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php), [lib/Model/Iblock/PageService.php](lib/Model/Iblock/PageService.php), [lib/Model/Iblock/MenuService.php](lib/Model/Iblock/MenuService.php), [lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php), [lib/Model/WebForm/WebFormService.php](lib/Model/WebForm/WebFormService.php), [lib/Model/Sitemap/Sitemap.php](lib/Model/Sitemap/Sitemap.php)).
* **Множественные поля-привязки (`iblock_element`/`hlblock`) в HL-блоках** роняли `GET /api/hlblock/{code}/elements` с `TypeError` (массив ID использовался как ключ массива). Добавлена обработка множественных значений — в `INFO` кладётся массив связанных записей ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* **Веб-формы с картинкой** роняли `/api/forms/{code}` и `/api/forms/{code}/fields` — сравнение `=== '' / ''` (деление строк) вместо `=== '/'` давало `TypeError` ([lib/Model/WebForm/WebFormService.php](lib/Model/WebForm/WebFormService.php)).
* **Меню (`/api/menus/{code}`):** `getPagesIblockId()` кастил массив инфоблока к `int` (всегда `1`), из-за чего URL страниц резолвились через неверный инфоблок. Теперь берётся `['ID']` ([lib/Model/Iblock/MenuService.php](lib/Model/Iblock/MenuService.php)).
* **Детальные ответы и свойства инфоблока не регистрировали теги кеша** (`iblock_id_*`) — правка элемента/раздела не сбрасывала его детальный кеш до истечения TTL. Добавлен `registerTag` в детальные элемент/раздел, свойства и статические страницы ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php), [lib/Model/Iblock/PageService.php](lib/Model/Iblock/PageService.php)).
* **`total` в списке разделов** (`/api/iblock/{code}/sections`) считался по обрезанной пагинацией выборке; теперь общее число берётся отдельным запросом и учитывает `SHOW_TOTAL` (как в списке элементов) ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).
* **Отравление кеша неучтёнными параметрами.** В ключи кеша добавлены влияющие на ответ параметры: `SHOW_TOTAL`/`SHOW_IPROPERTY_VALUES` (список элементов), `SHOW_PREFIX_FIELD_DATA` (детальный элемент, статическая страница), `bIncCnt` (детальный раздел), `SHOW_TOTAL` (список разделов), а также параметры детальной статической страницы. Для HL-блоков `order` больше не подвергается `ksort` (порядок ключей сортировки семантически значим) ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php), [lib/Model/Iblock/PageService.php](lib/Model/Iblock/PageService.php), [lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* **`GET /api/search` без тела запроса** отдавал 500 (`null` в типизированный `array`); деление на ноль при `limit=0` и дробная страница при некратном `offset`. Сигнатуры `Search::getAction`/`getData` сохранены (`array $config = []`) для совместимости с переопределениями; пустое тело нормализуется в контроллере (`getJsonRequestBody() ?? []`), `limit` нормализуется (`max(1, ...)`), страница считается через `intdiv` ([lib/Model/Search.php](lib/Model/Search.php), [lib/Controller/SearchController.php](lib/Controller/SearchController.php)). `getJsonRequestBody()` теперь возвращает массив или `null` (без изменения объявленного типа) — скалярный/невалидный JSON не ломает `::create()` ([lib/Controller/BaseController.php](lib/Controller/BaseController.php)).
* **`getSectionDetailByIdAction`** типизировал символьный код инфоблока как `int` — запрос вида `/api/iblock/news/section/id/5` падал с `TypeError`. Исправлено на `string` ([lib/Controller/IblockController.php](lib/Controller/IblockController.php)).
* **Статические страницы (`/api/pages/{code}`):** не устанавливался `$this->config`, из-за чего игнорировались `HIDE_DISPLAY_VALUES`/`SHOW_PREFIX_FIELD_DATA`/`GET_PROPERTIES_PROPERTY`; `propertyCode: ["*"]` не срабатывал (сравнение с `' * '`) ([lib/Model/Iblock/PageService.php](lib/Model/Iblock/PageService.php)).
* **Инвалидация кеша контента** (`ContentEvent`) регистрировалась только в админ-разделе, поэтому мутации через публичные роуты `/api/content/admin/*` и `/api/content/bulk` не сбрасывали кеш. Обработчики привязываются безусловно ([lib/Event/EventManager.php](lib/Event/EventManager.php)).
* **Умный фильтр по справочнику (`directory`) в `/api/iblock/{code}/filter`.** Удалена мёртвая ветка выбора поля названия по `SETTINGS['HLFIELD_ID']` (такого ключа у directory-свойства нет — в `USER_TYPE_SETTINGS` есть `TABLE_NAME`); локализованное название справочника (`UF_NAME_<ЯЗЫК>`) теперь берётся по языку из запроса (`siteId`, напр. `ru`), а не по серверному `LANGUAGE_ID`. В `IblockProperty` добавлен параметр `siteId` ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php), [lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).
* **PHP 8.1+ / мелкие баги:** приоритет операторов в проверке каптчи веб-формы (`?? 'N' === 'Y'`); общий ключ кеша у `getWebFormFieldsByCode`/`getWebFormMixedFieldsByCode` (методы затирали друг друга) + отсутствие дефолта `siteId`; `null` в `strError`/`new Error()` при ошибке добавления формы; `SORT` свойства заполнялся из `NAME`; неинициализированная `$dataClass` в `IblockHelper::getIblockDataClassByElementId()`; фантомный пустой пункт меню при отфильтрованном родителе; `JSON_THROW_ON_ERROR` и `htmlspecialchars(null)` в настройках модуля; игнорирование переданного `$moduleId` в `OptionsBase`; неверная маска минут (`MM`→`MI`) в fallback-формате даты sitemap; TTL кеша `84600`→`86400`; мемоизация полей HL-блока без учёта языка; баланс тегированного кеша при исключениях/пустом результате ([lib/Model/WebForm/WebFormService.php](lib/Model/WebForm/WebFormService.php), [lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php), [lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php), [lib/Model/Iblock/IblockHelper.php](lib/Model/Iblock/IblockHelper.php), [lib/Model/Iblock/MenuService.php](lib/Model/Iblock/MenuService.php), [lib/Utils/Options/OptionsBase.php](lib/Utils/Options/OptionsBase.php), [lib/Model/Sitemap/Sitemap.php](lib/Model/Sitemap/Sitemap.php), [lib/Model/Content/ContentTable.php](lib/Model/Content/ContentTable.php), [lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).

### Changed

* Локализация отображаемых текстов: названия типов событий журнала (`EventLogType::label()`) и тексты admin-уведомлений (`CAdminNotify` в `BaseController` и `IblockProperty`) вынесены из хардкода в языковые сообщения (`UPLAB_API.EVENT_LOG_*`, `UPLAB_API.NOTIFY_*`) с переводами RU/EN. Также исправлен русский текст в EN-файле у `UPLAB_API.HIGHLOADBLOCK_NOT_FOUND` ([lib/Enums/EventLogType.php](lib/Enums/EventLogType.php), [lib/Controller/BaseController.php](lib/Controller/BaseController.php), [lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php), [lang/ru/include.php](lang/ru/include.php), [lang/en/include.php](lang/en/include.php)).
* Сборка значений умного фильтра (`IblockProperty::getPropertyValues`) больше не глушит исключения молча: при ошибке по конкретному свойству она по-прежнему не роняет весь фильтр, но подробности (свойство, инфоблок, сообщение и трейс) пишутся в журнал событий (`CEventLog`, тип `UPLAB_API_FILTER_ERROR`), а администратору показывается краткое уведомление `CAdminNotify` со ссылкой на отфильтрованный журнал ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)). Тип события описан в enum `Uplab\Api\Enums\EventLogType` (единый источник: значение + читаемое название) и регистрируется через `main:OnEventLogGetAuditTypes`, поэтому виден в фильтре Журнала событий с понятным названием ([lib/Enums/EventLogType.php](lib/Enums/EventLogType.php), [lib/Event/EventManager.php](lib/Event/EventManager.php)).
* Добавлен алиас с корректным написанием `HlblockService::getFieldsAction()` для `getFiedlsAction()` (историческая опечатка). Основным остаётся `getFiedlsAction()` — его вызывает контроллер, поэтому переопределения в проектах-наследниках продолжают работать ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* Убран лишний повторный запрос ID HL-блока в `ContentController::getListAction()` (используется уже полученный `$hlblock['ID']`) ([lib/Controller/ContentController.php](lib/Controller/ContentController.php)).
* Добавлено сообщение `UPLAB_API.ACCESS_DENIED` в языковые файлы (ранее использовался неопределённый код `ACCESS_DENIED`) ([lang/ru/include.php](lang/ru/include.php), [lang/en/include.php](lang/en/include.php)).

## [1.7.0] - 2026-07-03

### Added

* Встроенная документация REST API в админ-панели: пункт меню **«API Документация (uplab.api)»** в разделе **«Сервисы»** с отдельными страницами по модулям (Обзор, Инфоблоки, Highload-блоки, Статические страницы, Веб-формы, Статический контент, Меню, Поиск, Карта сайта) и страницей «События» ([lib/Admin/Docs.php](lib/Admin/Docs.php)).
* `Uplab\Api\Admin\Docs` — Swagger-подобный интерфейс документации в блочном стиле админки Bitrix (белые контентные блоки на сером фоне): горизонтальная навигация по разделам с иконками и подсветкой активного, сворачиваемые операции с бейджами методов, чипами (контроллер/модель/конфиг, кеширование, требуемая авторизация), таблицами path/query/body-параметров, примерами тела запроса, автогенерацией curl (домен берётся из текущего запроса) и примерами ответа; операции по умолчанию свёрнуты; якорные ссылки на каждый endpoint/событие (клик по «#» копирует ссылку, переход по ссылке с `#hash` раскрывает, подсвечивает и прокручивает к нужному разделу); поиск-фильтр по операциям, «развернуть/свернуть всё» и копирование блоков кода; светлая тема блоков кода с лёгкой серверной подсветкой синтаксиса (JSON / bash / PHP, без внешних библиотек) и выделением плейсхолдеров (`{параметр}`, `#YEAR#`); таблицы параметров с рамкой, зеброй и подсветкой строки под курсором для читаемости. Примеры тела запроса включают `siteId` (`"ru"`); для эндпоинтов инфоблоков/HL/поиска задокументированы значения, применяемые на бэкенде по умолчанию (базовый фильтр `ACTIVE=Y`/`CHECK_PERMISSIONS=Y`/`IBLOCK_LID`, сортировка по умолчанию, `limit`/`offset`). На «Обзоре» — формат конверта ответа (`status`/`data`/`errors`) и таблица HTTP-кодов ([lib/Admin/Docs.php](lib/Admin/Docs.php)).
* `Uplab\Api\Security\AccessManager` — единая точка проверки прав доступа к операциям модуля: `canRead()` (пользователь авторизован и право модуля ≥ `R`) и `checkRead()` ([lib/Security/AccessManager.php](lib/Security/AccessManager.php)). Используется в `admin/menu.php` и на всех admin-страницах документации.
* `admin/menu.php` — пункт левого меню в разделе «Сервисы» (`global_menu_services`), автоматически сканируется Bitrix; доступ ограничен через `AccessManager::canRead()` ([admin/menu.php](admin/menu.php)).
* Тонкие admin-страницы `admin/uplab_api_docs*.php` (по одной на модуль) и install-обёртки `install/bitrix/admin/uplab_api_docs*.php`.

### Security

* **Обход проверки прав через тело запроса (`filter`).** Пользовательский `filter` объединялся с базовым как `array_merge($base, $filter)`, из-за чего клиент мог переопределить защитные ключи — `CHECK_PERMISSIONS`, `MIN_PERMISSION`, `ACTIVE`, `IBLOCK_ID`, а на детальных — даже селектор `ID`/`CODE`. Например, `{"filter":{"CHECK_PERMISSIONS":"N"}}` отключал проверку прав. Порядок слияния изменён на `array_merge($filter, $base)` — теперь `filter` только добавляет условия и не может переопределить защитные/скоуп-ключи. Исправлено во всех местах сборки фильтра:
  * элементы/разделы (списки и детальные), свойства инфоблока ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php));
  * статические страницы `/api/pages/{code}` ([lib/Model/Iblock/PageService.php](lib/Model/Iblock/PageService.php));
  * меню `/api/menus/{code}` — защищены `ACTIVE`/`GLOBAL_ACTIVE`/`IBLOCK_ID` ([lib/Model/Iblock/MenuService.php](lib/Model/Iblock/MenuService.php)).
* **Утечка данных фасетов фильтра.** Агрегатор значений для `/api/iblock/{code}/filter` собирал значения через `CIBlockElement::GetList` **без** проверки прав (`CHECK_PERMISSIONS` отсутствовал), а пользовательский фильтр мог переопределить `ACTIVE`/`IBLOCK_ID` — в фасеты попадали значения из недоступных/неактивных элементов. Добавлены `CHECK_PERMISSIONS=Y` и `MIN_PERMISSION=PUBLIC_READ`, порядок слияния защищён ([lib/Model/Iblock/IblockProperty.php](lib/Model/Iblock/IblockProperty.php)).

### Fixed

* `HlblockListConfig::create` — теперь читает `limit` и `offset` из тела запроса (по умолчанию `10` / `0`). Ранее эти параметры игнорировались, и список элементов HL-блока (`ANY /api/hlblock/{code}/elements`) всегда возвращал первые 10 элементов без возможности постраничного вывода ([lib/Utils/Config/Hlblock/HlblockListConfig.php](lib/Utils/Config/Hlblock/HlblockListConfig.php)).

### Changed

* Встроенная документация: значения по умолчанию вынесены в отдельный структурированный блок «Значения по умолчанию» (права / базовый фильтр / сортировка / пагинация; строка о неизменяемости проверки прав вынесена с акцентом) вместо сплошного текста; примеры тела запроса дополнены `siteId: "ru"`; задокументировано необязательное поведение `propertyCode` (без него список выводит свойства с флагом «Показывать на странице списка элементов», детальная — «Показывать на детальной странице элемента») ([lib/Admin/Docs.php](lib/Admin/Docs.php)).
* README: добавлен раздел «Поведение по умолчанию» для инфоблоков (базовый фильтр, неизменяемость проверки прав, сортировка/пагинация, `propertyCode`); таблица HL-блока обновлена (пагинация `limit`/`offset`, структура ответа `data.meta`) ([README.md](README.md)).
* README / CLAUDE.md: зафиксировано требование синхронно обновлять документацию (README и встроенные docs `lib/Admin/Docs.php`) при изменениях в API-коде.

## [1.6.0] - 2026-07-02

### Added

* `HlblockService::convertFieldsDataForFront` — добавлена обработка пользовательских полей типа `file`: полный массив файла (`CFile::GetFileArray()` — `SRC`, `FILE_NAME`, `FILE_SIZE`, `CONTENT_TYPE` и т.д.) кладётся в `INFO[FIELD_NAME]` с сохранением исходного значения; поддерживаются одиночные и множественные поля (для множественного — массив файлов) ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* Два новых события для модификации данных элементов HL-блока (`GET /api/hlblock/{code}/elements`), вызываются для каждого элемента выборки с параметрами `[$fields, &$dataItem]` ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)):
  * `OnBeforeHlblockConvertFieldValue` — перед конвертацией значений полей.
  * `OnAfterHlblockConvertFieldValue` — после конвертации значений полей.
* Документация системы событий: описаны все события модуля с сигнатурами параметров, включая ранее не задокументированные `OnIblockConvertSectionDisplayValue` и `OnIblockConvertPropertyDisplayValue`, а также правила конвертации полей HL-блока ([README.md](README.md)).

### Changed

* `HlblockService::convertFieldsDataForFront` — список обработчиков событий запрашивается один раз (`GetModuleEvents` вынесен из цикла по элементам) вместо вызова на каждый элемент ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).
* `HlblockService::convertFieldsDataForFront` — после циклов `foreach` по ссылке добавлен `unset($dataItem)` (защитная мера от классической ловушки PHP с переменной цикла по ссылке; фактического повреждения данных не было — все проходы по `$data` идут по ссылке) ([lib/Model/Hlblock/HlblockService.php](lib/Model/Hlblock/HlblockService.php)).

## [1.5.2] - 2026-06-24

### Fixed

* `IblockService::getIblockByCode` — исправлены ключи фильтра `CIBlock::GetList`: `IBLOCK_LID` / `IBLOCK_TYPE` (поля для `CIBlockElement::GetList`) заменены на корректные `SITE_ID` / `TYPE`. Ранее фильтрация инфоблока по сайту и типу молча игнорировалась ([lib/Model/Iblock/IblockService.php](lib/Model/Iblock/IblockService.php)).

## [1.5.1] - 2026-06-24

### Removed

* `ContentEvent` — удалена проверка уникальности `UF_PAGE` + `UF_CODE`, добавленная в 1.5.0 (обработчики `OnBeforeAdd` / `OnBeforeUpdate`). Реализация была некорректной и ломала сохранение элементов контента ([lib/Event/ContentEvent.php](lib/Event/ContentEvent.php)). Удалены связанные lang-файлы `CONTENT_EVENT_DUPLICATE_ERROR`.

## [1.5.0] - 2026-06-23 [YANKED]

> **Критическая ошибка — не устанавливать.** Проверка уникальности `UF_PAGE` + `UF_CODE` в `ContentEvent` ломала сохранение элементов контента. Исправлено в версии 1.5.1.

### Added

* `ApiAccessFilter` — новый фильтр доступа к API ([lib/ActionFilter/ApiAccessFilter.php](lib/ActionFilter/ApiAccessFilter.php)). Проверяет заголовок `Origin` для браузерных запросов и `REMOTE_ADDR` для серверных. Применяется ко всем контроллерам через `BaseController::getDefaultPreFilters()`.
* Настройки модуля, вкладка «Доступ к API» ([options.php](options.php)):
  * `API_ACCESS_ENABLED` — чекбокс включения проверки (по умолчанию выключено).
  * `API_ALLOWED_ORIGINS` — whitelist доменов (`Origin`). Поддерживаются форматы `site.ru` и `https://site.ru`.
  * `API_ALLOWED_IPS` — whitelist IP для серверных запросов без `Origin` (Nuxt SSR и др.).
* `POST /api/content/bulk` — групповые действия над записями контента: `activate`, `deactivate`, `delete`. Требует авторизации и прав `hl_element_write` / `hl_element_delete` на HLBlock ([ContentController::bulkAction](lib/Controller/ContentController.php)).
* `ContentEvent` — уникальность пары `UF_PAGE` + `UF_CODE` теперь проверяется на уровне событий HLBlock (`OnBeforeAdd`, `OnBeforeUpdate`), а не только в форме редактирования ([lib/Event/ContentEvent.php](lib/Event/ContentEvent.php)).
* `CCustomOETabs` — административный список контента доработан: чекбоксы выбора строк, групповые действия (активировать / деактивировать / удалить), выпадающее меню действий строки через `BX.Main.MenuManager`, skeleton-загрузка, список не обновляется при закрытии панели без сохранения ([lib/Event/CCustomOETabs.php](lib/Event/CCustomOETabs.php)).
* `uplab_api_content_page_edit.php` — popup-форма редактирования элемента контента: кнопки «Сохранить» (сохранить + закрыть) и «Применить» (сохранить без закрытия), поддержка MULTIPLE-полей, стилизация Bitrix UI ([admin/uplab_api_content_page_edit.php](admin/uplab_api_content_page_edit.php)).
* Добавлены lang-файлы `ru`/`en` для формы редактирования контента и событий ContentEvent.

### Fixed

* `ContentController` — исправлен неверный импорт `Bitrix\Bizproc\Error` (заменён на `Bitrix\Main\Error`).
* `ContentController::getListAction` — при отсутствии прав теперь возвращается ошибка `ACCESS_DENIED` вместо пустого успешного ответа.
* `ContentController::bulkAction` — `compileEntity()` больше не вызывается до проверки прав (предотвращает исключение при `entityId = 0`).
* `ContentController::getListAction` — переведён с `ContentTable::getList()` на `compileEntity()->getDataClass()::getList()` для корректной десериализации множественных полей HLBlock.
* `ContentEvent::eventHandlerOnBeforeUpdate` — проверка уникальности пропускается, если в обновляемых полях нет ни `UF_PAGE`, ни `UF_CODE` (исправляет ошибку при обновлении только `UF_ACTIVE`).

## [1.4.10] - 2026-05-27

### Changed
* `IblockService::getElementListAction` — при сортировке `{"RAND": "ASC"}` кеш ответа сокращается до 30 секунд вместо стандартных ~24 часов. Это позволяет возвращать разные случайные наборы элементов без риска DoS: не более 1 запроса к БД в 30 секунд на каждую уникальную комбинацию параметров.

## [1.4.9] - 2026-05-19

### Added
* `SitemapInterface` — введён контракт для модели sitemap. `SitemapController::getModelClass()` теперь можно переопределять, возвращая любой класс, реализующий интерфейс.
* `Sitemap` — рефакторинг: методы сбора страниц разбиты на точечно переопределяемые хуки (`getStaticPagesIblockCode`, `getStaticPagesOrder`, `getStaticPagesFilter`, `getStaticPagesSelect`, `buildStaticPageItem`, `getElementPagesOrder`, `getElementPagesFilter`, `buildElementPageItem`, `getSectionPagesOrder`, `getSectionPagesFilter`, `buildSectionPageItem`).

### Fixed
* `ContentEvent::eventHandlerOnDelete` — исправлена неопределённая переменная `$parameters` (событие удаления не сбрасывало кеш).
* `CCustomOETabs` — исправлена неявная глобальная переменная `self` в JS-методе `deleteElement`.
* `Sitemap::getXml` — URL с `&` теперь корректно экранируются через `htmlspecialchars(ENT_XML1)`.
* `Sitemap::getIblockSectionsPages` — `MakeTimeStamp` вызывается после проверки наличия `TIMESTAMP_X`.

### Changed
* `Sitemap` — кеш изолируется по сайту: директория `/{siteId}/sitemap` вместо общего ключа `sitemap`.
* `Sitemap` — время кеша вынесено в переопределяемое свойство `$cacheTime` (по умолчанию `86400`).
* `Sitemap::getData` — пустой sitemap возвращает `200` с пустым массивом вместо `404`.
* `Sitemap` — все фильтры выборки включают `CHECK_PERMISSIONS`, `IBLOCK_ACTIVE`, `MIN_PERMISSION` и фильтрацию по `IBLOCK_LID` при наличии `siteId`.
* `OptionsBase::drawSerializedRow` — убран дублирующийся флаг `JSON_PRETTY_PRINT`; `unserialize` защищён через `allowed_classes => false`.

## [1.4.8] - 2026-05-14

### Fixed
* При выводе элементов инфоблока возвращается LANG_DIR, IBLOCK_CODE, LID.

## [1.4.7] - 2026-05-14

### Fixed
* В методе Uplab\Api\Model\Content\ContentTable::getPageData добавлено подключение модуля highloadblock.

## [1.4.6] - 2026-05-14 [YANKED]

> **Критическая ошибка — не устанавливать.** Исправлено в версии 1.4.7.

### Fixed
* В методе Uplab\Api\Model\Content\ContentTable::getPageData файла [ContentTable.php](lib/Model/Content/ContentTable.php) теперь используется API HighloadBlock, так как фильтр корректно работает со множественными полями.

## [1.4.5] - 2026-05-14

### Added
* `/api/content/page/{pageCode}` — добавлена обработка входного параметра `siteId` через `BaseConfig` в [ContentPageController.php](lib/Controller/ContentPageController.php). Параметр передаётся в `ContentTable::getPageData()` и учитывается в ключе кеша.
* Добавлен файл [README.md](README.md)


## [1.4.4] - 2026-02-17

### Added
* Добавлен обработчик uplab.api OnBeforeCheckForm

Пример использования:

```php
$eventManager->addEventHandler('uplab.api', 'OnBeforeCheckForm', [static::class, 'OnBeforeCheckForm']);
public static function OnBeforeCheckForm(\Uplab\Api\EventResult\BeforeCheckForm $event): \Bitrix\Main\EventResult
    {
        $error = "";
        $captchaToken = $event->request['smart_token'] ?? '';
        if (empty($captchaToken)) {
            $error = "Captcha token is required";
        } elseif (!(new YandexSmartCaptcha(Option::get(Main::MODULE_ID, 'YA_CAPTCHA_PRIVATE', '')))->check($captchaToken, \Bitrix\Main\Service\GeoIp\Manager::getRealIp())) {
            $error = "Некорректная Captcha";
        }
        if ($error) {
            return new \Bitrix\Main\EventResult(
                \Bitrix\Main\EventResult::ERROR,
                parameters: [
                    'message' => $error
                ],
                moduleId: Main::MODULE_ID
            );
        }
        return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS);
    }
```


## [1.4.3] - 2026-02-13

### Fixed
* Fix не кешировался getPropertiesProperty [IblockService.php](lib/Model/Iblock/IblockService.php) при выводе элемента инфоблока.


## [1.4.2] - 2026-02-09

### Fixed
* Fix fatal error "Argument #2 ($timestamp) must be of type ?int, string given" in [Sitemap.php](lib/Model/Sitemap/Sitemap.php)

## [1.4.1] - 2026-01-30  [YANKED]
> **Критическая ошибка — не устанавливать.** Исправлено в версии 1.4.2.
### Added
* Добавлен параметр GET_PROPERTIES_PROPERTY в Body при выводе элементов инфоблока. Тем самым можно получить значение свойств и свойств.

Пример, как получить значение свойство LOGO и элементов свойства VENDORS
```
{
  "GET_PROPERTIES_PROPERTY":{
    "VENDORS":["LOGO"]
  }
}
```

### Fixed
* Вернул обработку bIncCnt из Body [IblockListSectionsConfig.php](lib/Utils/Config/Iblock/IblockListSectionsConfig.php)


## [1.4.0] - 2026-01-12
### Fixed
* Доработан вывод данных в классе [CCustomOETabs.php](lib/Event/CCustomOETabs.php) 

## [1.3.11] - 2025-12-18
### Added
* Доработан класс поиска [Search.php](lib/Model/Search.php) для удобной модификации логики класса.

## [1.3.10] - 2025-12-15

### Fixed
* Исправлен баг c отображением "value" в "dropdown" [WebFormService.php](lib/Model/WebForm/WebFormService.php)

### Added
* Добавлено multiple для type file [WebFormService.php](lib/Model/WebForm/WebFormService.php)


## [1.3.9] - 2025-12-01

### Fixed

* Исправлен баг в [IblockService.php](lib/Model/Iblock/IblockService.php) в апи перестали передаваться изображения.

## [1.3.8] - 2025-12-01

### Fixed

* Исправлен баг в [IblockService.php](lib/Model/Iblock/IblockService.php).

## [1.3.7] - 2025-12-01

### Added

* Добавлен параметр HIDE_DISPLAY_VALUES при выводе элементов инфоблока, чтобы не получать DISPLAY_VALUES у свойств. Сделано для уменьшения отдаваемой информации.
* В FIELD полях элементах и разделах инфоблока значения выводятся как с "~", а сама "~" удалена. Сделано для уменьшения отдаваемой информации. Для 50 элементов с 1Мб уменьшено до 0.3Мб. Если значения нужны, то нужно передать SHOW_PREFIX_FIELD_DATA = Y

## [1.3.6] - 2025-11-19

### Added

* Для элементов инфоблока можно передать activeToDateFormat для форматирования даны ACTIVE_TO

## [1.3.5] - 2025-11-19

### Fixed

* [HlblockListConfig.php](lib/Utils/Config/Hlblock/HlblockListConfig.php) исправлен некорректный namespace

## [1.3.4] - 2025-11-19

### Fixed

* [MenuService.php](lib/Model/Iblock/MenuService.php) исправление пустого SUBMENU.
 

## [1.3.3] - 2025-11-19

### Fixed

* [IblockService.php](lib/Model/Iblock/IblockService.php) у элементов LINK_ELEMENT_VALUE и LINK_SECTION_VALUE просто ID файла вместо массива.

## [1.3.2] - 2025-11-19

### Fixed

* рефакторинг кода

## [1.3.1] - 2025-11-19

### Fixed

* [MenuController.php](lib/Controller/MenuController.php) исправление fatal error.
* [CHANGELOG.md](CHANGELOG.md) изменена сортировка версий.

### Added

* [MenuService.php](lib/Model/Iblock/MenuService.php) доработан чтобы было удобнее менять логику у наследуемых классов.

## [1.3.0] - 2025-11-18

### Added

* [MenuService.php](lib/Model/Iblock/MenuService.php) переписан чтобы было удобнее менять логику у наследуемых классов.

## [1.2.1] - 2025-11-17

### Added

* Добавлен более удобный способ для управления классом модели контроллера.

## [1.2.0] - 2025-11-17

### Added

* Удалён класс IblockConfig и HlblockConfig. Вместо них теперь каждый отдельный класс:
    * IblockConfig::
      createForElementFilter -> [IblockFilterConfig::create](lib/Utils/Config/Iblock/IblockFilterConfig.php)
    * IblockConfig::
      createForList -> [IblockListElementsConfig::create](lib/Utils/Config/Iblock/IblockListElementsConfig.php)
    * IblockConfig::
      createForSectionList -> [IblockListSectionsConfig::create](lib/Utils/Config/Iblock/IblockListSectionsConfig.php)
    * IblockConfig::
      createForDetail -> [IblockDetailElementConfig::create](lib/Utils/Config/Iblock/IblockDetailElementConfig.php)
    * IblockConfig::createDefault -> [BaseConfig::create](lib/Utils/Config/BaseConfig.php)
    * IblockConfig::createForDetail -> [IblockDetailConfig::create](lib/Utils/Config/Iblock/IblockDetailConfig.php)
    * HlblockConfig::createForList -> [HlblockListConfig::create](lib/Utils/Config/Hlblock/HlblockListConfig.php)
    * HlblockConfig::createDefault -> [BaseConfig::create](lib/Utils/Config/BaseConfig.php)

## [1.1.2] - 2025-11-14

### Added

* Для детальных страниц раздела и элемента добавлен функционал хлебных крошек
* UF_ поля раздела получаются только по запросу от FRONT, иначе данных слишком много

## [1.1.1] - 2025-11-13

### Fixed

* Рефакторинг кода, исправление мелких багов.

## [1.1.0] - 2025-11-11

### Added

* При выводе разделов для UF_ полей добавлен отдельный массив FIELDS ['UF_'] и обработчик события
  OnIblockConvertSectionDisplayValue по аналогии с OnIblockConvertPropertyDisplayValue

## [1.0.5] - 2025-11-10

### Fixed

* Исправлена ошибка добавления нового результата в форме

## [1.0.4] - 2025-10-29

### Added

* Добавлен функционал опции RESTART для морфологического поиска

## [1.0.3] - 2025-10-29

### Fixed

* расширена автономность модуля

## [1.0.2] - 2025-10-22

### Fixed

* рефакторинг поиска с GET-запросов на JSON-body

## [1.0.1] - 2025-10-21

### Fixed

* /api/menus/{code} - выводил до 2 эровня вложенности
