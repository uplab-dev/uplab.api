<?php

namespace Uplab\Api\ActionFilter;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpRequest;
use Uplab\Api\Main;

class ApiAccessFilter extends Base
{
    public function onBeforeAction(Event $event): ?EventResult
    {
        if (Option::get(Main::MODULE_ID, 'API_ACCESS_ENABLED', 'N') !== 'Y') {
            return null;
        }

        $originsRaw = Option::get(Main::MODULE_ID, 'API_ALLOWED_ORIGINS', '');
        $ipsRaw     = Option::get(Main::MODULE_ID, 'API_ALLOWED_IPS', '');

        $allowedOrigins = array_values(array_filter(array_map('trim', explode("\n", $originsRaw))));
        $allowedIps     = array_values(array_filter(array_map('trim', explode("\n", $ipsRaw))));

        // Оба списка пусты — правил нет, ограничивать нечем: API остаётся открытым.
        // Закрывать доступ здесь нельзя: включённый чекбокс без заполненных списков —
        // типовая ситуация, и она не должна выключать API целиком.
        if (empty($allowedOrigins) && empty($allowedIps)) {
            return null;
        }

        $request = $this->getHttpRequest();
        $origin  = trim((string)$request->getHeader('Origin'));
        // Именно REMOTE_ADDR: X-Forwarded-For клиент задаёт сам, доверять ему
        // без модели доверенных прокси нельзя (getRealIp() учитывает заголовок).
        $ip = (string)$request->getRemoteAddress();

        // Списки проверяются по «ИЛИ»: браузерный запрос проходит по Origin, серверный
        // (Nuxt SSR, curl — без заголовка) — по IP. Удаление заголовка доступ не открывает:
        // запрос без Origin обязан пройти по IP-списку, иначе он никуда не попадает.
        if (!empty($allowedIps) && in_array($ip, $allowedIps, true)) {
            return null;
        }

        if (!empty($allowedOrigins) && $this->isOriginAllowed($origin, $allowedOrigins)) {
            return null;
        }

        return $this->deny();
    }

    private function getHttpRequest(): HttpRequest
    {
        return $this->getAction()?->getController()?->getRequest() ?? Context::getCurrent()->getRequest();
    }

    /**
     * @param string[] $allowedOrigins
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        $originNormalized = $this->normalizeOrigin($origin);
        if ($originNormalized === null) {
            return false;
        }

        $normalizedAllowedOrigins = array_values(array_filter(array_map(
            fn(string $entry): ?string => $this->normalizeOrigin($entry),
            $allowedOrigins
        )));

        return in_array($originNormalized, $normalizedAllowedOrigins, true);
    }

    private function normalizeOrigin(string $origin): ?string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return null;
        }

        $hasScheme = str_contains($origin, '://');
        $parts = parse_url($hasScheme ? $origin : '//' . $origin);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        if ($hasScheme && !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }
        if (!empty($parts['user']) || !empty($parts['pass']) || !empty($parts['query']) || !empty($parts['fragment'])) {
            return null;
        }
        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return null;
        }

        return strtolower($parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }

    private function deny(): EventResult
    {
        $this->addError(new Error('Access denied', 403));
        return new EventResult(EventResult::ERROR);
    }
}
