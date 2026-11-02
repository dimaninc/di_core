<?php

namespace diCore\Push;

/**
 * Одно уведомление для любого сервиса push. Текст видит каждый посредник (FCM, RuStore,
 * сервис браузера – только зашифрованным), поэтому персональных данных в нём быть не должно.
 */
final class Message
{
    /** @var array<string, string> */
    public readonly array $data;

    /**
     * @param array<string, scalar> $data значения уходят строками – так требует FCM
     * @param string|null $link куда ведёт нажатие: deep link RuStore, поле link в payload web push
     * @param int|null $ttl секунды, сколько сервис хранит push для выключенного устройства (Android, iPhone
     *   через FCM, браузер); null – срок сервиса
     * @throws \InvalidArgumentException не скаляр в data, отрицательный ttl, текст не в UTF-8 – ошибка вызова,
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
                throw new \InvalidArgumentException('Push data must be a map of scalars');
            }
        }
        $this->data = array_map(static fn($value) => (string) $value, $data);
        if ($ttl !== null && $ttl < 0) {
            throw new \InvalidArgumentException('Push ttl must not be negative');
        }
        // Проверка здесь: иначе битый UTF-8 всплыл бы JsonException посреди отправки.
        if (json_encode([$title, $body, $this->data, $link]) === false) {
            throw new \InvalidArgumentException('Push text must be valid UTF-8');
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
