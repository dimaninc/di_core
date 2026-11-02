<?php

namespace diCore\Push;

/**
 * Одно уведомление для любого сервиса push. Текст видит каждый посредник (FCM, RuStore,
 * сервис браузера – только зашифрованным), поэтому персональных данных в нём быть не должно.
 */
final class Message
{
    // 28 дней – предел FCM: больший ttl он отвергает 400.
    public const MAX_TTL = 2419200;

    // Предел FCM на notification и data вместе.
    public const MAX_BYTES = 4096;

    private const RESERVED_KEYS = ['from', 'notification', 'message_type', 'collapse_key'];

    /** @var array<string, string> */
    public readonly array $data;

    /**
     * @param array<string, scalar> $data значения уходят строками – так требует FCM, bool – "true"/"false";
     *   ключ не из одних цифр (PHP делает из '1' число) и не зарезервирован FCM (from, google.*, gcm.*…)
     * @param string|null $link куда ведёт нажатие: deep link RuStore, поле link в payload web push; FCM его
     *   не передаёт – приложение строит переход из data
     * @param int|null $ttl секунды, сколько сервис хранит push для выключенного устройства (Android, iPhone
     *   через FCM, браузер), не больше MAX_TTL; null – срок сервиса
     * @throws \InvalidArgumentException неверный data, ttl вне 0…MAX_TTL, текст не в UTF-8, больше MAX_BYTES – ошибка вызова,
     *   а не сбой доставки: повтор не поможет
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        array $data = [],
        public readonly ?string $link = null,
        public readonly ?int $ttl = null,
    ) {
        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                throw new \InvalidArgumentException('Push data must map non-numeric string keys to scalars');
            }
            // FCM отвергает их 400, и правило Gone тогда зависело бы от формата ответа.
            if (in_array($key, self::RESERVED_KEYS, true) || preg_match('/^(google|gcm)\./', $key)) {
                throw new \InvalidArgumentException("Push data key '$key' is reserved by FCM");
            }
        }
        $this->data = array_map(static fn($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $data);
        if ($ttl !== null && ($ttl < 0 || $ttl > self::MAX_TTL)) {
            throw new \InvalidArgumentException('Push ttl must be within 0…' . self::MAX_TTL);
        }
        // Проверка здесь: иначе битый UTF-8 всплыл бы JsonException посреди отправки.
        if (json_encode([$title, $body, $this->data, $link]) === false) {
            throw new \InvalidArgumentException('Push text must be valid UTF-8');
        }
        // Слишком большое FCM отвергает тем же INVALID_ARGUMENT, что и битый токен, – не доводить до отправки.
        if (strlen(json_encode($this->toFcm(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Push message must fit ' . self::MAX_BYTES . ' bytes');
        }
    }

    /** Сообщение в формате FCM HTTP v1, его же принимает RuStore. */
    public function toFcm(): array
    {
        $message = [
            'notification' => ['title' => $this->title, 'body' => $this->body],
        ];
        if ($this->data) {
            $message['data'] = $this->data;
        }
        if ($this->ttl !== null) {
            $message['android']['ttl'] = "{$this->ttl}s";
        }

        return $message;
    }

    /** Payload web push: его читает service worker сайта. */
    public function toWebPush(): string
    {
        $payload = ['title' => $this->title, 'body' => $this->body, 'data' => (object) $this->data];
        if ($this->link !== null) {
            $payload['link'] = $this->link;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
