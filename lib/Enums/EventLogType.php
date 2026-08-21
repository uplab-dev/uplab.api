<?php

namespace Uplab\Api\Enums;

use Bitrix\Main\Localization\Loc;

/**
 * Типы событий модуля для Журнала событий Bitrix (audit_type_id).
 *
 * Единый источник правды: значение case — это audit_type_id для {@see \CEventLog::Log()},
 * {@see label()} — читаемое название, {@see auditTypes()} — карта для события
 * main:OnEventLogGetAuditTypes (регистрируется в {@see \Uplab\Api\Event\EventManager}).
 */
enum EventLogType: string
{
    /** Ошибка сборки данных умного фильтра (см. IblockProperty::getPropertyValues). */
    case FILTER_ERROR = 'UPLAB_API_FILTER_ERROR';

    /** Необработанное исключение/ошибка в контроллере API (см. BaseController). */
    case INTERNAL_ERROR = 'UPLAB_API_INTERNAL_ERROR';

    /**
     * Читаемое название типа события (отображается в Журнале событий и его фильтре).
     */
    public function label(): string
    {
        return match ($this) {
            self::FILTER_ERROR   => (string)Loc::getMessage('UPLAB_API.EVENT_LOG_FILTER_ERROR'),
            self::INTERNAL_ERROR => (string)Loc::getMessage('UPLAB_API.EVENT_LOG_INTERNAL_ERROR'),
        };
    }

    /**
     * Карта «audit_type_id => название» для обработчика main:OnEventLogGetAuditTypes.
     *
     * @return array<string, string>
     */
    public static function auditTypes(): array
    {
        $types = [];
        foreach (self::cases() as $case) {
            $types[$case->value] = $case->label();
        }

        return $types;
    }
}
