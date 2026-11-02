<?php

namespace diCore\Push;

use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as Library;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Web Push (RFC 8030/8291) с ключами VAPID (RFC 8292) – браузер на компьютере.
 * Шифрование и подпись – minishlink/web-push ^11 (в suggest; проект ставит его сам вместе
 * с symfony/http-client и nyholm/psr7). Смена пары ключей обнуляет все подписки.
 */
final class WebPush
{
    public const TIMEOUT = 10;

    private Library $push;

    /**
     * @param string $publicKey ключи в base64url – в этом виде их выдаёт createKeys() и берёт браузер
     * @param string $subject контакт для сервиса push: mailto: или https:
     * @param ClientInterface|null $client PSR-18; по умолчанию symfony/http-client без редиректов
     */
    public function __construct(
        private readonly string $publicKey,
        string $privateKey,
        string $subject,
        ?ClientInterface $client = null,
    ) {
        $client ??= self::defaultClient();
        try {
            $this->push = new Library(
                ['VAPID' => ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey]],
                ['urgency' => 'high'],
                $client,
                $client instanceof Psr18Client ? $client : null,
                $client instanceof Psr18Client ? $client : null,
            );
        } catch (\ErrorException | \InvalidArgumentException) {
            // Как «FCM not configured»: ошибка настройки проекта, а не устройства.
            throw new TransportError('Web push VAPID keys invalid');
        }
    }

    /** @return array{publicKey: string, privateKey: string} */
    public static function createKeys(): array
    {
        return VAPID::createVapidKeys();
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function send(string $endpoint, string $p256dh, string $auth, Message $message): Delivery
    {
        // Подписка не из списка сервисов push отправлена не будет никогда – её только удалить.
        if (!WebPushSubscription::isValid($endpoint, $p256dh, $auth)) {
            return Delivery::Gone;
        }
        $payload = $message->toWebPush();
        if (strlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new TransportError('Web push payload too large');
        }
        $options = $message->ttl === null ? [] : ['TTL' => $message->ttl];
        try {
            $report = $this->push->sendOneNotification(
                new Subscription($endpoint, $p256dh, $auth, 'aes128gcm'),
                $payload,
                $options,
            );
        } catch (\InvalidArgumentException | \ErrorException) {
            // Размер проверен выше: библиотека отвергла ключи подписки, а их браузер уже не исправит.
            return Delivery::Gone;
        } catch (\RuntimeException | \ValueError) {
            // Сбой своей стороны (OpenSSL не создал ключ) – устройство не виновато.
            throw new TransportError('Web push encryption failed');
        }
        $status = $report->getResponse()?->getStatusCode();
        if ($status === null) {
            throw new TransportError('Web push service unavailable');
        }
        if ($status >= 200 && $status < 300) {
            return Delivery::Delivered;
        }
        if ($status === 404 || $status === 410) {
            return Delivery::Gone;
        }

        throw new TransportError("Web push HTTP $status");
    }

    private static function defaultClient(): ClientInterface
    {
        if (!class_exists(Psr18Client::class)) {
            throw new \LogicException('diCore\Push\WebPush: install symfony/http-client and nyholm/psr7 or pass a PSR-18 client');
        }

        // Редирект увёл бы запрос мимо проверки хоста в WebPushSubscription.
        return new Psr18Client(
            HttpClient::create([
                'max_redirects' => 0,
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
            ]),
        );
    }
}
