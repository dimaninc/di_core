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
        $json = json_encode(['message' => $payload]);
        if ($json === false) {
            // Токен не в UTF-8 не отправится никогда.
            return Delivery::Gone;
        }
        [$status, $body] = $this->http->post(
            'https://vkpns.rustore.ru/v1/projects/' . rawurlencode($this->projectId) . '/messages:send',
            $json,
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
        $details = $body['error']['details'] ?? [];
        foreach (is_array($details) ? $details : [] as $detail) {
            if (($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                return true;
            }
        }
        // NOT_FOUND – неверный или истёкший токен (документация RuStore); UNREGISTERED в ней нет – как у FCM,
        // на случай совместимого ответа. Токен в неверном формате – 400 с этой фразой (опыт 1romantic).
        $message = is_string($body['error']['message'] ?? null) ? $body['error']['message'] : '';

        // Только признак в теле ответа vkpns: голый 404 (прокси, сменённый путь) удалил бы все токены разом.
        return in_array($code, ['NOT_FOUND', 'UNREGISTERED'], true) ||
            ($status === 400 && stripos($message, 'registration token') !== false);
    }
}
