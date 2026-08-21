<?php

namespace Uplab\Api\Model\Iblock;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Uplab\Api\Enums\EventLogType;
use Uplab\Api\Model\Hlblock\HlblockService;

class IblockProperty
{
    protected int $iblockId = 0;
    protected int $sectionId = 0;
    protected ?string $siteId = null;
    protected array $iblockTags = [];

    /** Дополнительные теги кеша (например тег HL-блока для свойств-справочников). */
    protected array $cacheTags = [];

    /**
     * Свойство, значения которого собираются в текущий момент. Заполняется диспетчером
     * на время работы резолвера — нужно там, где мета-данные свойства не передаются
     * параметром (например в buildElementsFilter()).
     */
    protected ?array $currentProperty = null;

    /**
     * Параметры необязательны: модель можно создать пустой и настроить сеттерами
     * (см. IblockService::getPropertyModel()).
     */
    public function __construct(int $iblockId = 0, $sectionId = 0, ?string $siteId = null)
    {
        $this->iblockId = $iblockId;
        $this->sectionId = $sectionId;
        $this->siteId = $siteId;
        Loader::includeModule('iblock');
    }

    public function setIblockId(int $iblockId): static
    {
        $this->iblockId = $iblockId;
        return $this;
    }

    public function setSectionId(int $sectionId): static
    {
        $this->sectionId = $sectionId;
        return $this;
    }

    public function setSiteId(?string $siteId): static
    {
        $this->siteId = $siteId;
        return $this;
    }

    /**
     * Возвращает символьный код свойств, которые нужно выводить в умном фильтре.
     * @return array
     */
    protected function getIblockPropertiesFilterable(): array
    {
        $propData = [];

        if ($properties = \CIBlockSectionPropertyLink::GetArray($this->getIblockId(), $this->getSectionId())) {
            foreach ($properties as $PID => $arLink) {
                if (
                    $arLink['SMART_FILTER'] !== 'Y'
                    ||
                    $arLink['ACTIVE'] === 'N'
                ) {
                    continue;
                }
                //$arLink['DISPLAY_TYPE']
                // F - Флажки,
                // K - Радиокнопки,
                // P - Выпадающий список
                // |
                // G - Флажки с картинками,
                // H - Флажки с названиями и картинками,
                // R - Выпадающий список с названиями и картинками

                //get property code
                $rsProperty = \CIBlockProperty::GetByID($PID);
                $arProperty = $rsProperty->Fetch();
                if ($arProperty) {
                    $propData[$arProperty['CODE']] = $arLink;
                }
            }
        }
        return $propData;
    }

    /**
     * Возвращает свойства инфоблока участвующие в смарт-фильтре.
     * @return array
     */
    public function getFilterProperties(?array $requestFilter = null): array
    {
        $data = [];

        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cacheDir = '/iblock/' . $this->getIblockId();
        $cacheId = 'propertySmartFilter' . serialize([
            $this->getSectionId(),
            $this->getAccessCacheId(),
        ]);

        if ($requestFilter) {
            $cacheId .= md5(serialize($requestFilter));
        }

        //свойства
        if ($cache->initCache(86400, $cacheId, $cacheDir)) {
            $data = $cache->getVars() ?? [];
        } elseif ($cache->startDataCache()) {
            $this->iblockTags = [$this->getIblockId()];
            $this->cacheTags = [];

            if ($propertiesFilterable = $this->getIblockPropertiesFilterable()) {
                $rsProp = \CIBlockProperty::GetList(
                    [
                        'sort' => 'asc',
                        'name' => 'asc'
                    ],
                    [
                        'ACTIVE'    => 'Y',
                        'IBLOCK_ID' => $this->getIblockId()
                    ]
                );

                // Типы свойств берутся из карты резолверов — второго списка типов,
                // который может с ней разойтись, быть не должно.
                $arPropertyType = array_keys($this->getValueResolvers());
                while ($arr = $rsProp->Fetch()) {
                    if (isset($propertiesFilterable[$arr['CODE']]) && in_array(
                            $arr['PROPERTY_TYPE'],
                            $arPropertyType
                        )) {
                        if ($items = $this->getPropertyValues($arr, $requestFilter)) {
                            $data[] = [
                                'id'           => $arr['ID'],
                                'userType'     => $arr['USER_TYPE'],
                                'sort'         => $arr['SORT'],
                                'propertyType' => $arr['PROPERTY_TYPE'],
                                'code'         => $arr['CODE'],
                                'name'         => $arr['NAME'],
                                'hint'         => $arr['HINT'],
                                'filterHint'   => $propertiesFilterable[$arr['CODE']]['FILTER_HINT'] ?? null,
                                'multiple'     => $arr['MULTIPLE'],
                                'searchable'   => $arr['SEARCHABLE'],
                                'displayType'  => $propertiesFilterable[$arr['CODE']]['DISPLAY_TYPE'] ?? '',
                                'items'        => $items
                            ];
                        }
                    }
                }

                $cacheManager = \Bitrix\Main\Application::getInstance()->getTaggedCache();
                $cacheManager->startTagCache($cacheDir);
                foreach ($this->iblockTags as $iblockId) {
                    $cacheManager->registerTag('iblock_id_' . $iblockId);
                }
                foreach ($this->cacheTags as $cacheTag) {
                    $cacheManager->registerTag($cacheTag);
                }
                $cacheManager->endTagCache();
            } else {
                $cache->abortDataCache();
            }

            $cache->endDataCache($data);
        }

        return $data;
    }

    protected function addIblockTags($iblockId)
    {
        if (!in_array($iblockId, $this->iblockTags)) {
            $this->iblockTags[] = $iblockId;
        }
    }

    /**
     * Регистрирует произвольный тег кеша для ответа фильтра (для сущностей, у которых
     * тег не сводится к 'iblock_id_{ID}' — например записи HL-блока-справочника).
     */
    protected function addCacheTag(string $tag): void
    {
        if ($tag !== '' && !in_array($tag, $this->cacheTags, true)) {
            $this->cacheTags[] = $tag;
        }
    }

    //<editor-fold desc="Диспетчер значений свойств">
    /**
     * Карта «тип свойства (PROPERTY_TYPE) => метод-резолвер значений».
     *
     * Наследник добавляет свой тип или подменяет штатный, дописав карту и метод;
     * штатное поведение доступно через parent::метод().
     *
     * Значения карты — имена методов класса и только они: имя резолвера вызывается
     * динамически, поэтому подставлять в карту данные из запроса, настроек или БД нельзя.
     * Указывать здесь getPropertyValues() тоже нельзя — получится бесконечная рекурсия.
     *
     * Карта задаёт и список типов, которые вообще попадают в выборку свойств
     * (см. getFilterProperties()): типа, отсутствующего в карте, в ответе не будет.
     *
     * @return array<string, string>
     */
    protected function getValueResolvers(): array
    {
        return [
            'L' => 'getEnumValues',
            'S' => 'getStringValues',
            'E' => 'getElementLinkValues',
            'G' => 'getSectionLinkValues',
        ];
    }

    /**
     * Возвращает значения свойства для умного фильтра.
     *
     * @param array      $arProperty     мета-данные свойства (по ссылке: CODE приводится к верхнему регистру)
     * @param array|null $requestFilter  фильтр из тела запроса
     *
     * @return array
     */
    protected function getPropertyValues(&$arProperty, ?array $requestFilter = null): array
    {
        if (!$arProperty) {
            return [];
        }

        $arProperty['CODE'] = strtoupper($arProperty['CODE']);

        $resolver = $this->getValueResolvers()[$arProperty['PROPERTY_TYPE'] ?? ''] ?? null;
        // is_callable, а не method_exists: недоступный (например объявленный в наследнике
        // как private) метод — это ошибка конфигурации карты, свойство просто пропускаем.
        if (!$resolver || !is_callable([$this, $resolver])) {
            return [];
        }

        // Предыдущее значение восстанавливается, а не обнуляется: резолвер наследника может
        // по пути собрать значения другого свойства, и внешний контекст должен сохраниться.
        $previousProperty = $this->currentProperty;
        $this->currentProperty = $arProperty;

        try {
            return $this->{$resolver}($arProperty, $this->buildPropertyFilter($arProperty, $requestFilter));
        } catch (\Throwable $exception) {
            // Не роняем весь фильтр из-за одного свойства: Throwable, а не Exception, —
            // ошибка в резолвере (в том числе проектном) не должна ломать весь ответ.
            $this->logPropertyError($arProperty, $exception);
            return [];
        } finally {
            $this->currentProperty = $previousProperty;
        }
    }

    /**
     * Свойство, значения которого собираются в текущий момент, или null вне работы резолвера.
     * Позволяет учитывать конкретное свойство там, где мета-данные не приходят параметром —
     * прежде всего в buildElementsFilter().
     */
    protected function getCurrentProperty(): ?array
    {
        return $this->currentProperty;
    }

    /**
     * Фильтр запроса для сборки значений свойства: без условия по самому свойству —
     * иначе свойство сужало бы собственный список значений и в фильтре оставался бы
     * только уже выбранный вариант.
     *
     * Сравнение по точному совпадению ключа: ключ с префиксом-оператором
     * (>=PROPERTY_PRICE и т.п.) не исключается.
     *
     * @param array      $arProperty
     * @param array|null $requestFilter
     *
     * @return array
     */
    protected function buildPropertyFilter(array $arProperty, ?array $requestFilter = null): array
    {
        $customFilter = [];
        foreach (($requestFilter ?? []) as $key => $value) {
            if ($key != 'PROPERTY_' . ($arProperty['CODE'] ?? '')) {
                $customFilter[$key] = $value;
            }
        }

        return $customFilter;
    }

    /**
     * Пишет подробности ошибки сборки значений свойства в журнал событий,
     * администратору показывает уведомление со ссылкой на журнал.
     */
    protected function logPropertyError(array $arProperty, \Throwable $exception): void
    {
        $auditType = EventLogType::FILTER_ERROR->value;

        \CEventLog::Log(
            \CEventLog::SEVERITY_WARNING,
            $auditType,
            \Uplab\Api\Main::MODULE_ID,
            (string)($arProperty['CODE'] ?? ''),
            sprintf(
                "Ошибка сборки умного фильтра по свойству \"%s\" (инфоблок %d): %s\n%s",
                $arProperty['CODE'] ?? 'unknown',
                $this->getIblockId(),
                $exception->getMessage(),
                $exception->getTraceAsString()
            )
        );

        $logUrl = '/bitrix/admin/event_log.php?set_filter=Y&find_type=audit_type_id'
            . '&find_audit_type%5B0%5D=' . $auditType . '&lang=' . LANGUAGE_ID;

        \CAdminNotify::Add([
            'NOTIFY_TYPE'  => \CAdminNotify::TYPE_ERROR,
            'MESSAGE'      => (string)Loc::getMessage('UPLAB_API.NOTIFY_FILTER_ERROR', ['#URL#' => $logUrl]),
            'TAG'          => 'uplab_api_filter_error',
            'MODULE_ID'    => \Uplab\Api\Main::MODULE_ID,
            'ENABLE_CLOSE' => 'Y',
        ]);
    }
    //</editor-fold>

    //<editor-fold desc="Резолверы значений по типам свойств">
    /**
     * Привязка к разделу (G).
     */
    protected function getSectionLinkValues(array $arProperty, array $customFilter): array
    {
        $iblockID = (int)($arProperty['LINK_IBLOCK_ID'] ?? 0);
        if (!$iblockID) {
            return [];
        }

        //получим ID нужных разделов
        $groupItems = $this->getGroupPropertyItems($arProperty['CODE'], $customFilter);
        if (!$groupItems) {
            return [];
        }

        $this->addIblockTags($iblockID);

        return $this->buildSectionItems(
            $this->fetchSections(
                $this->getLinkedSectionsFilter($iblockID, array_keys($groupItems), $arProperty),
                $this->getSectionsOrder($arProperty),
                $this->getSectionsSelect($arProperty)
            ),
            $arProperty
        );
    }

    /**
     * Привязка к элементу (E).
     */
    protected function getElementLinkValues(array $arProperty, array $customFilter): array
    {
        $iblockID = (int)($arProperty['LINK_IBLOCK_ID'] ?? 0);
        if (!$iblockID) {
            return [];
        }

        //получим ID нужных элементов
        $groupItems = $this->getGroupPropertyItems($arProperty['CODE'], $customFilter);
        if (!$groupItems) {
            return [];
        }

        $this->addIblockTags($iblockID);

        return $this->buildElementItems(
            $this->fetchElements(
                $this->getLinkedElementsFilter($iblockID, array_keys($groupItems), $arProperty),
                $this->getElementsOrder($arProperty),
                $this->getElementsSelect($arProperty)
            ),
            $arProperty
        );
    }

    /**
     * Список (L): значения перечисления, реально использованные в доступных элементах.
     */
    protected function getEnumValues(array $arProperty, array $customFilter): array
    {
        $enumIds = $this->getEnumPropertyItems($arProperty['CODE'], $customFilter);
        if (!$enumIds) {
            return [];
        }

        return $this->buildEnumItems(
            $this->fetchEnumValues((int)$arProperty['ID'], $enumIds, $this->getEnumOrder($arProperty)),
            $arProperty
        );
    }

    /**
     * Строка (S): справочник HL-блока (USER_TYPE = directory) либо значения свойства как есть.
     */
    protected function getStringValues(array $arProperty, array $customFilter): array
    {
        if (($arProperty['USER_TYPE'] ?? '') === 'directory') {
            return $this->getDirectoryValues($arProperty, $customFilter);
        }

        return $this->getPlainStringValues($arProperty, $customFilter);
    }

    /**
     * Строковое свойство-справочник (USER_TYPE = directory): значения из HL-блока.
     */
    protected function getDirectoryValues(array $arProperty, array $customFilter): array
    {
        $tableName = $arProperty['USER_TYPE_SETTINGS']['TABLE_NAME'] ?? '';
        $hlblockID = $this->getHighloadblockID($tableName);
        if (!$hlblockID || !Loader::includeModule('highloadblock')) {
            return [];
        }

        // Тег HL-блока: правка записей справочника в админке сбрасывает кеш ответа фильтра
        // (обработчик в Uplab\Api\Event\EventManager)
        $this->addCacheTag(HlblockService::getHlBlockTag($hlblockID));

        //получим значения свойства (XML_ID записей справочника) по доступным элементам
        $xmlIds = $this->getIDPropertyItems($arProperty['CODE'], $customFilter);
        if (!$xmlIds) {
            return [];
        }

        $nameFields = $this->resolveDirectoryNameFields($this->fetchHlblockFields($hlblockID));

        $select = array_merge(['ID', 'UF_XML_ID'], $nameFields);

        return $this->buildDirectoryItems(
            $this->fetchDirectoryItems(
                $hlblockID,
                array_unique($select),
                $xmlIds,
                $this->getDirectoryOrder($arProperty)
            ),
            $nameFields,
            $arProperty
        );
    }

    /**
     * Сортировка разделов для свойства-привязки к разделу (G).
     * Переопределяйте, чтобы задать свой порядок — в том числе для конкретного свойства
     * конкретного инфоблока (доступны $arProperty и $this->getIblockId()).
     */
    protected function getSectionsOrder(array $arProperty): array
    {
        return ['SORT' => 'ASC', 'NAME' => 'ASC'];
    }

    /**
     * Сортировка элементов для свойства-привязки к элементу (E).
     */
    protected function getElementsOrder(array $arProperty): array
    {
        return ['SORT' => 'ASC', 'NAME' => 'ASC'];
    }

    /**
     * Сортировка значений перечисления для списочного свойства (L).
     */
    protected function getEnumOrder(array $arProperty): array
    {
        return ['SORT' => 'ASC', 'VALUE' => 'ASC'];
    }

    /**
     * Фильтр выборки связанных разделов (свойство G). Проверка прав обязательна:
     * в фильтр не должны попадать названия разделов, недоступных пользователю.
     *
     * @param int   $iblockId   инфоблок, на который ссылается свойство
     * @param array $ids        ID разделов, использованных в доступных элементах
     * @param array $arProperty мета-данные свойства
     *
     * @return array
     */
    protected function getLinkedSectionsFilter(int $iblockId, array $ids, array $arProperty): array
    {
        return [
            'ID'                => $ids,
            'ACTIVE'            => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => \CIBlockRights::PUBLIC_READ,
        ];
    }

    /**
     * Фильтр выборки связанных элементов (свойство E). Проверка прав обязательна:
     * в фильтр не должны попадать названия элементов, недоступных пользователю.
     *
     * @param int   $iblockId   инфоблок, на который ссылается свойство
     * @param array $ids        ID элементов, использованных в доступных элементах
     * @param array $arProperty мета-данные свойства
     *
     * @return array
     */
    protected function getLinkedElementsFilter(int $iblockId, array $ids, array $arProperty): array
    {
        return [
            'ID'                => $ids,
            'ACTIVE'            => 'Y',
            'IBLOCK_ID'         => $iblockId,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => \CIBlockRights::PUBLIC_READ,
        ];
    }

    /**
     * Поля выборки разделов для свойства-привязки к разделу (G).
     * Добавьте свои поля, если они нужны в buildSectionItems().
     */
    protected function getSectionsSelect(array $arProperty): array
    {
        return ['ID', 'NAME', 'IBLOCK_ID', 'CODE'];
    }

    /**
     * Поля выборки элементов для свойства-привязки к элементу (E).
     * Добавьте свои поля (в том числе PROPERTY_<КОД>), если они нужны в buildElementItems().
     */
    protected function getElementsSelect(array $arProperty): array
    {
        return ['ID', 'NAME'];
    }

    /**
     * Сортировка записей справочника. Пустой массив — порядок по умолчанию (без ORDER BY).
     */
    protected function getDirectoryOrder(array $arProperty): array
    {
        return [];
    }

    /**
     * Формат значений фильтра для разделов (свойство G, а также проектные типы,
     * значения которых — разделы инфоблока).
     */
    protected function buildSectionItems(array $rows, array $arProperty): array
    {
        $result = [];
        foreach ($rows as $item) {
            $result[] = [
                'id'    => $item['ID'],
                'value' => $item['ID'],
                'text'  => $item['NAME']
            ];
        }

        return $result;
    }

    /**
     * Формат значений фильтра для элементов (свойство E).
     */
    protected function buildElementItems(array $rows, array $arProperty): array
    {
        $result = [];
        foreach ($rows as $item) {
            $result[] = [
                'id'    => $item['ID'],
                'value' => $item['ID'],
                'text'  => $item['NAME']
            ];
        }

        return $result;
    }

    /**
     * Формат значений фильтра для перечисления (свойство L).
     */
    protected function buildEnumItems(array $rows, array $arProperty): array
    {
        $result = [];
        foreach ($rows as $item) {
            $result[] = [
                'id'    => $item['ID'],
                'value' => $item['ID'],
                'text'  => $item['VALUE']
            ];
        }

        return $result;
    }

    /**
     * Поля-кандидаты для «text» записи справочника по приоритету: локализованное имя
     * (UF_NAME_<ЯЗЫК>, язык из siteId запроса) → общее UF_NAME. Значение берётся
     * построчно: если локализованное пустое — подставляется UF_NAME, если и оно пустое — UF_XML_ID.
     *
     * @param array $fields мета-данные пользовательских полей HL-блока
     *
     * @return array<int, string>
     */
    protected function resolveDirectoryNameFields(array $fields): array
    {
        $langField = 'UF_NAME_' . strtoupper($this->siteId ?: LANGUAGE_ID);

        $nameFields = [];
        if (!empty($fields[$langField])) {
            $nameFields[] = $langField;
        }
        if (!empty($fields['UF_NAME'])) {
            $nameFields[] = 'UF_NAME';
        }

        return $nameFields;
    }

    /**
     * Собирает значения фильтра из записей справочника.
     *
     * @param array $rows       записи справочника
     * @param array $nameFields поля-кандидаты для «text» (см. resolveDirectoryNameFields())
     * @param array $arProperty мета-данные свойства
     *
     * @return array
     */
    protected function buildDirectoryItems(array $rows, array $nameFields, array $arProperty = []): array
    {
        $result = [];
        foreach ($rows as $item) {
            $text = '';
            foreach ($nameFields as $nameField) {
                if (isset($item[$nameField]) && (string)$item[$nameField] !== '') {
                    $text = $item[$nameField];
                    break;
                }
            }
            if ($text === '') {
                $text = $item['UF_XML_ID'];
            }

            $result[] = [
                'id'    => $item['ID'],
                'value' => $item['UF_XML_ID'],
                'text'  => $text,
            ];
        }

        return $result;
    }

    /**
     * Строковое свойство без справочника: значения свойства как есть.
     */
    protected function getPlainStringValues(array $arProperty, array $customFilter): array
    {
        $this->addIblockTags($arProperty['IBLOCK_ID']);

        $rows = $this->fetchPropertyValueRowsWithRaw(
            $arProperty['CODE'],
            $this->buildElementsFilter($customFilter)
        );

        return $this->buildPlainStringItems($arProperty, $rows);
    }

    /**
     * Собирает значения фильтра из строк выборки строкового свойства.
     *
     * @param array $arProperty
     * @param array $rows строки выборки (с «~»-полями)
     *
     * @return array
     */
    protected function buildPlainStringItems(array $arProperty, array $rows): array
    {
        $propCodeValue = 'PROPERTY_' . $arProperty['CODE'];

        $result = [];
        foreach ($rows as $item) {
            $val = $item['~' . $propCodeValue . '_VALUE'] ?? null;
            if ($val == '') {
                continue;
            }

            //свойство HTML/TEXT
            if (is_array($val) && isset($val['TEXT']) && isset($val['TYPE'])) {
                $val = $val['TEXT'];
            }

            $result[] = [
                'id'    => $arProperty['ID'],
                'value' => $val,
                'text'  => $val
            ];
        }

        return $result;
    }
    //</editor-fold>

    //<editor-fold desc="Выборка значений свойств">
    /**
     * Базовый фильтр выборки элементов для сборки значений фильтра.
     * Базовые (защитные) ключи имеют приоритет — пользовательский фильтр только добавляет условия.
     *
     * @param array $additionalFilter
     *
     * @return array
     */
    protected function buildElementsFilter(array $additionalFilter = []): array
    {
        $filter = [
            'ACTIVE'            => 'Y',
            'IBLOCK_ID'         => $this->getIblockId(),
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION'    => \CIBlockRights::PUBLIC_READ,
        ];

        if ($val = $this->getSectionId()) {
            $filter['SECTION_ID'] = $val;
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }

        return IblockHelper::mergeProtectedFilter($additionalFilter, $filter);
    }

    /**
     * Получает ID значений списочного свойства (перечисления), которые реально
     * используются в доступных элементах выборки.
     *
     * @param       $propertyCode
     * @param array $additionalFilter
     *
     * @return array - список ENUM_ID
     */
    protected function getEnumPropertyItems($propertyCode, array $additionalFilter = []): array
    {
        $rows = $this->fetchPropertyValueRows($propertyCode, $this->buildElementsFilter($additionalFilter));

        $enumIds = [];
        foreach ($rows as $item) {
            $enumId = (int)($item['PROPERTY_' . $propertyCode . '_ENUM_ID'] ?? 0);
            if ($enumId) {
                $enumIds[$enumId] = $enumId;
            }
        }

        return array_values($enumIds);
    }

    /**
     * Получает все возможные значения для из текущего BLOCK_ID
     *
     * @param       $propertyCode
     * @param array $additionalFilter
     *
     * @return array - ['ID значения'=>'кол-во элементов с таким значение']
     */
    protected function getGroupPropertyItems($propertyCode, array $additionalFilter = [])
    {
        $rows = $this->fetchPropertyValueRows($propertyCode, $this->buildElementsFilter($additionalFilter));

        $itemsGroup = [];
        foreach ($rows as $item) {
            if ($item['PROPERTY_' . $propertyCode . '_VALUE']) {
                $itemsGroup[$item['PROPERTY_' . $propertyCode . '_VALUE']] = $item['CNT'];
            }
        }

        return $itemsGroup;
    }

    /**
     * Получение ID highloadBlock
     *
     * @param $tableName - название highloadBlock
     *
     * @return int ID highloadBlock
     */
    protected function getHighloadblockID($tableName)
    {
        $id = 0;
        if (Loader::includeModule('highloadblock')) {
            $rsData = \Bitrix\Highloadblock\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => $tableName]]);
            $hldata = $rsData->fetch();
            if ($hldata["ID"]) {
                $id = $hldata["ID"];
            }
        }
        return $id;
    }

    /**
     * Получает все возможные значения для из текущего BLOCK_ID
     *
     * @param       $FIELD_NAME
     * @param array $customFilter
     *
     * @return array|bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    private function getIDPropertyItems($FIELD_NAME, $customFilter = [])
    {
        return array_keys(
            $this->getGroupPropertyItems($FIELD_NAME, is_array($customFilter) ? $customFilter : [])
        );
    }
    //</editor-fold>

    /**
     * Сегмент доступа для ключа кеша значений фильтра: в расширенном режиме прав
     * инфоблока набор доступных элементов зависит от конкретного пользователя.
     *
     * @return string
     */
    protected function getAccessCacheId(): string
    {
        return IblockHelper::getAccessCacheId($this->getIblockId());
    }

    //<editor-fold desc="Обращения к API Битрикс (точки подмены в тестах)">
    /**
     * Строки выборки значений свойства, сгруппированные по значению.
     *
     * @return array<int, array>
     */
    protected function fetchPropertyValueRows(string $propertyCode, array $filter): array
    {
        $res = \CIBlockElement::GetList([], $filter, ['PROPERTY_' . $propertyCode], false);

        $rows = [];
        while ($item = $res->Fetch()) {
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * То же, что fetchPropertyValueRows(), но строки содержат «~»-поля
     * (нужны строковым свойствам: значение берётся без html-преобразований).
     *
     * @return array<int, array>
     */
    protected function fetchPropertyValueRowsWithRaw(string $propertyCode, array $filter): array
    {
        $res = \CIBlockElement::GetList(
            [],
            $filter,
            ['PROPERTY_' . $propertyCode],
            false,
            ['ID', 'IBLOCK_ID']
        );

        $rows = [];
        while ($item = $res->GetNext()) {
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * Разделы указанного инфоблока по списку ID.
     *
     * @return array<int, array>
     */
    protected function fetchSections(array $filter, array $order = [], array $select = []): array
    {
        $res = \CIBlockSection::GetList(
            $order,
            $filter,
            false,
            $select ?: ['ID', 'NAME', 'IBLOCK_ID', 'CODE']
        );

        $rows = [];
        while ($item = $res->Fetch()) {
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * Элементы указанного инфоблока по списку ID.
     *
     * @return array<int, array>
     */
    protected function fetchElements(array $filter, array $order = [], array $select = []): array
    {
        $res = \CIBlockElement::GetList(
            $order,
            $filter,
            false,
            false,
            $select ?: ['ID', 'NAME']
        );

        $rows = [];
        while ($item = $res->Fetch()) {
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * Значения перечисления свойства по списку ENUM_ID.
     *
     * @return array<int, array>
     */
    protected function fetchEnumValues(int $propertyId, array $enumIds, array $order = []): array
    {
        $res = \CIBlockPropertyEnum::GetList(
            $order,
            [
                // Именно ID свойства: по коду GetList уводит фильтр в P.ID,
                // если код начинается с цифры
                'PROPERTY_ID' => $propertyId,
                'ID'          => $enumIds
            ]
        );

        $rows = [];
        while ($item = $res->Fetch()) {
            $rows[] = $item;
        }

        return $rows;
    }

    /**
     * Мета-данные пользовательских полей HL-блока.
     */
    protected function fetchHlblockFields(int $hlblockID): array
    {
        return (array)$GLOBALS['USER_FIELD_MANAGER']->GetUserFields('HLBLOCK_' . $hlblockID, 0, LANGUAGE_ID);
    }

    /**
     * Записи справочника HL-блока по списку UF_XML_ID.
     *
     * @return array<int, array>
     */
    protected function fetchDirectoryItems(int $hlblockID, array $select, array $xmlIds, array $order = []): array
    {
        $hlblock = HighloadBlockTable::getById($hlblockID)->fetch();
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $strEntityDataClass = $entity->getDataClass();

        $parameters = [
            'select' => array_values($select),
            'filter' => ['UF_XML_ID' => $xmlIds]
        ];
        if ($order) {
            $parameters['order'] = $order;
        }

        $res = $strEntityDataClass::getList($parameters);

        $rows = [];
        while ($item = $res->Fetch()) {
            $rows[] = $item;
        }

        return $rows;
    }
    //</editor-fold>

    public function getIblockId(): int
    {
        return $this->iblockId;
    }

    public function getSectionId(): int
    {
        return $this->sectionId;
    }
}
