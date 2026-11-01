<?php

namespace diCore\Push;

/**
 * RuStore Push (vkpns.rustore.ru): Android без Google-сервисов. Формат – как FCM HTTP v1,
 * доступ – сервисный токен проекта из консоли RuStore, без OAuth.
 */
final class RuStore
{
    private Http $http;

    /** @param \Closure|null $http подмена HTTP в тестах, см. Http */
    public function __construct(
        private readonly string $projectId,
        private readonly string $serviceToken,
        ?\Closure $http = null,
    ) {
        $this->http = new Http($http);
    }

    public function send(string $token, Message $message): Delivery
    {
        if ($this->projectId === '' || $this->serviceToken === '') {
            throw new TransportError('RuStore not configured');
        }
        $payload = ['token' => $token] + $message->toFcm();
        if ($message->link !== null) {
            // Тип 1 – открыть deep link; без него нажатие только запускает приложение.
            $payload['android']['notification'] = [
                'click_action_type' => 1,
                'click_action' => $message->link,
            ];
        }
        [$status, $body] = $this->http->post(
            'https://vkpns.rustore.ru/v1/projects/' . rawurlencode($this->projectId) . '/messages:send',
            json_encode(['message' => $payload], JSON_THROW_ON_ERROR),
            ['Content-Type: application/json', "Authorization: Bearer {$this->serviceToken}"],
        );
        if ($status >= 200 && $status < 300) {
            return Delivery::Delivered;
        }
        if (self::isGone($status, $body)) {
            return Delivery::Gone;
        }

        throw new TransportError("RuStore HTTP $status");
    }

    private static function isGone(int $status, array $body): bool
    {
        $code = $body['error']['status'] ?? '';
        foreach ($body['error']['details'] ?? [] as $detail) {
            if (($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                return true;
            }
        }
        // Токен в неверном формате RuStore отклоняет 400 с этой фразой (опыт 1romantic).
        $message = is_string($body['error']['message'] ?? null) ? $body['error']['message'] : '';

        return $status === 404 ||
            in_array($code, ['NOT_FOUND', 'UNREGISTERED'], true) ||
            ($status === 400 && stripos($message, 'registration token') !== false);
    }
}
