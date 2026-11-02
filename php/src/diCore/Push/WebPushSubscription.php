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
        // Только символы имени хоста: «%2f» или «\\» прошли бы проверку суффикса, а HTTP-клиент такой адрес отвергнет.
        if (!preg_match('/^[a-z0-9.-]+$/D', $host)) {
            return false;
        }
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, ".$allowed")) {
                return true;
            }
        }

        return false;
    }

    // SubjectPublicKeyInfo для точки P-256 без самой точки (id-ecPublicKey, prime256v1).
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** p256dh – несжатая точка P-256 (65 байт) на кривой, auth – 16 байт; оба в base64url без «=». */
    public static function hasValidKeys(string $p256dh, string $auth): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]{87}$/D', $p256dh) || !preg_match('/^[A-Za-z0-9_-]{22}$/D', $auth)) {
            return false;
        }
        $point = base64_decode(strtr($p256dh, '-_', '+/'), true);
        if ($point === false || strlen($point) !== 65 || $point[0] !== "\x04") {
            return false;
        }
        // Точку не на кривой OpenSSL не примет – шифрование на неё упало бы при каждой отправке.
        $pem =
            "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX) . $point), 64, "\n") .
            "-----END PUBLIC KEY-----\n";

        return @openssl_pkey_get_public($pem) !== false;
    }
}
