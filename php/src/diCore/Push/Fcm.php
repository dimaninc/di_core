<?php

namespace diCore\Push;

/**
 * Firebase Cloud Messaging HTTP v1: Android с Google-сервисами и iPhone (ключ APNs – в Firebase).
 * Доступ – по ключу сервисного аккаунта (JSON из консоли Firebase); OAuth-токен живёт в объекте,
 * поэтому долгоживущий воркер держит один экземпляр.
 */
final class Fcm
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private Http $http;
    private array $google = [];

    /**
     * @param string $serviceAccountFile путь к JSON сервисного аккаунта; читается при первой отправке
     * @param \Closure|null $http подмена HTTP в тестах, см. Http
     * @param \Closure|null $clock подмена time() в тестах
     */
    public function __construct(
        private readonly string $serviceAccountFile,
        ?\Closure $http = null,
        private readonly ?\Closure $clock = null,
    ) {
        $this->http = new Http($http);
    }

    public function send(string $token, Message $message): Delivery
    {
        [$project, $authorization] = $this->authorization();
        $payload = ['token' => $token] + $message->toFcm();
        if ($message->ttl !== null) {
            // APNs (iPhone через FCM) берёт срок не из android.ttl, а абсолютным временем в заголовке.
            $payload['apns']['headers']['apns-expiration'] = (string) ($message->ttl ? $this->now() + $message->ttl : 0);
        }
        $json = json_encode(['message' => $payload]);
        if ($json === false) {
            // Токен не в UTF-8 не отправится никогда.
            return Delivery::Gone;
        }
        [$status, $body] = $this->http->post(
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($project) . '/messages:send',
            $json,
            ['Content-Type: application/json', "Authorization: Bearer $authorization"],
        );
        if ($status >= 200 && $status < 300) {
            return Delivery::Delivered;
        }
        if (self::isGone($body)) {
            return Delivery::Gone;
        }
        if ($status === 401) {
            $this->google = [];
        }

        throw new TransportError("FCM HTTP $status");
    }

    private static function isGone(array $body): bool
    {
        $invalidToken = false;
        $badRequest = false;
        $details = $body['error']['details'] ?? [];
        foreach (is_array($details) ? $details : [] as $detail) {
            $type = $detail['@type'] ?? '';
            $code = $detail['errorCode'] ?? '';
            if ($code === 'UNREGISTERED') {
                return true;
            }
            $badRequest = $badRequest || $type === 'type.googleapis.com/google.rpc.BadRequest';
            $invalidToken =
                $invalidToken ||
                ($type === 'type.googleapis.com/google.firebase.fcm.v1.FcmError' && $code === 'INVALID_ARGUMENT');
        }

        // INVALID_ARGUMENT бывает и про сообщение (размер, поля): Gone – только без BadRequest и с упоминанием токена.
        $message = is_string($body['error']['message'] ?? null) ? $body['error']['message'] : '';
        $namesToken = stripos($message, 'registration token') !== false;

        return ($invalidToken && !$badRequest && $namesToken) || ($body['error']['status'] ?? '') === 'UNREGISTERED';
    }

    private function now(): int
    {
        return $this->clock ? ($this->clock)() : time();
    }

    /** @return array{0: string, 1: string} проект Firebase и OAuth-токен */
    private function authorization(): array
    {
        if (($this->google['expires'] ?? 0) > $this->now()) {
            return [$this->google['project'], $this->google['token']];
        }
        if ($this->serviceAccountFile === '') {
            throw new TransportError('FCM not configured');
        }
        $raw = @file_get_contents($this->serviceAccountFile);
        if ($raw === false) {
            throw new TransportError('FCM service account unreadable');
        }
        try {
            $key = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TransportError('FCM service account invalid JSON');
        }
        foreach (['project_id', 'client_email', 'private_key'] as $field) {
            if (!is_array($key) || !is_string($key[$field] ?? null) || $key[$field] === '') {
                throw new TransportError('FCM service account missing required fields');
            }
        }
        [$token, $ttl] = $this->oauthToken($key);
        // Обновляем за минуту до конца срока: запрос с почти истёкшим токеном получил бы 401.
        $this->google = [
            'project' => $key['project_id'],
            'token' => $token,
            'expires' => $this->now() + max(0, $ttl - 60),
        ];

        return [$this->google['project'], $this->google['token']];
    }

    /** @return array{0: string, 1: int} токен и срок в секундах */
    private function oauthToken(array $key): array
    {
        $now = $this->now();
        $unsigned =
            self::base64url('{"alg":"RS256","typ":"JWT"}') .
            '.' .
            self::base64url(
                json_encode(
                    [
                        'iss' => $key['client_email'],
                        'scope' => self::SCOPE,
                        'aud' => self::TOKEN_URL,
                        'iat' => $now,
                        'exp' => $now + 3600,
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            );
        if (!@openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new TransportError('FCM signing failed');
        }
        [$status, $body] = $this->http->post(
            self::TOKEN_URL,
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . self::base64url($signature),
            ]),
            ['Content-Type: application/x-www-form-urlencoded'],
        );
        if ($status !== 200 || empty($body['access_token']) || !is_string($body['access_token'])) {
            throw new TransportError("FCM authorization failed (HTTP $status)");
        }

        return [$body['access_token'], (int) ($body['expires_in'] ?? 0)];
    }

    private static function base64url(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}
