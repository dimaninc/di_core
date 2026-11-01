<?php

namespace diCore\Push;

/**
 * Проверка подписки, пришедшей от браузера, – и при сохранении, и перед отправкой.
 * Endpoint задаёт клиент, а POST на него делает сервер: без списка хостов поддельная
 * подписка заставила бы сервер ходить по внутренним адресам (SSRF).
 */
final class WebPushSubscription
{
    public const MAX_ENDPOINT_LENGTH = 1024;

    // Точный хост или любой его поддомен.
    private const HOSTS = [
        'fcm.googleapis.com', // Chrome, Opera, Samsung Internet
        'push.services.mozilla.com', // Firefox
        'push.apple.com', // Safari
        'notify.windows.com', // Edge
        'push.yandex.ru', // Яндекс Браузер
    ];

    public static function isValid(string $endpoint, string $p256dh, string $auth): bool
    {
        return self::isAllowedEndpoint($endpoint) && self::hasValidKeys($p256dh, $auth);
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > self::MAX_ENDPOINT_LENGTH || !preg_match('/^[!-~]+$/D', $endpoint)) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (
            !$parts ||
            ($parts['scheme'] ?? '') !== 'https' ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            (isset($parts['port']) && $parts['port'] !== 443)
        ) {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, ".$allowed")) {
                return true;
            }
        }

        return false;
    }

    /** p256dh – несжатая точка P-256 (65 байт), auth – 16 байт; оба в base64url без «=». */
    public static function hasValidKeys(string $p256dh, string $auth): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{87}$/D', $p256dh) &&
            (bool) preg_match('/^[A-Za-z0-9_-]{22}$/D', $auth);
    }
}
