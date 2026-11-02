<?php

namespace diCore\Push;

use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Encryption;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as Library;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Web Push (RFC 8030/8291) с ключами VAPID (RFC 8292) – браузер на компьютере.
 * Шифрование и подпись – minishlink/web-push ^11, PHP 8.2+ (в suggest; проект ставит его сам вместе
 * с symfony/http-client и nyholm/psr7). Смена пары ключей обнуляет все подписки: сервисы отвечают на
 * старые 403, а это TransportError, не Gone (иначе опечатка в ключе снесла бы всё) – их проект удаляет сам.
 */
final class WebPush
{
    public const TIMEOUT = 10;

    private Library $push;

    /**
     * @param string $publicKey ключи в base64url – в этом виде их выдаёт createKeys() и берёт браузер
     * @param string $subject контакт для сервиса push: mailto: с адресом или https: – без него Apple отвечает 403
     * @param ClientInterface|null $client PSR-18; по умолчанию symfony/http-client без редиректов
     */
    public function __construct(
        private readonly string $publicKey,
        string $privateKey,
        string $subject,
        ?ClientInterface $client = null,
    ) {
        $client ??= self::defaultClient();
        $vapid = ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey];
        if (!preg_match('~^(mailto:[^@\s]+@[^@\s]+|https://\S+)$~D', $subject)) {
            throw new TransportError('Web push VAPID subject invalid');
        }
        // Библиотека проверяет только длину ключей: битая пара всплыла бы лишь при отправке, на каждой подписке.
        try {
            if (!WebPushSubscription::isP256Point($publicKey)) {
                throw new \InvalidArgumentException();
            }
            $decoded = VAPID::validate($vapid);
            VAPID::getVapidHeaders(
                'https://fcm.googleapis.com',
                $subject,
                $decoded['publicKey'],
                $decoded['privateKey'],
                ContentEncoding::aes128gcm,
            );
        } catch (\Throwable) {
            throw new TransportError('Web push VAPID keys invalid');
        }
        try {
            $this->push = new Library(
                ['VAPID' => $vapid],
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
        // Хост не из списка – без запроса (SSRF), но не Gone: ошибка в самом списке иначе молча удалила бы
        // все подписки этого браузера; при сохранении такую подписку уже отвергли.
        if (!WebPushSubscription::isAllowedEndpoint($endpoint)) {
            throw new TransportError('Web push endpoint not allowed');
        }
        // Обычный base64 с «=» библиотека тоже понимает – подписку из старой базы не удалять из-за записи.
        [$p256dh, $auth] = array_map(static fn(string $key) => rtrim(strtr($key, '+/', '-_'), '='), [$p256dh, $auth]);
        if (!WebPushSubscription::hasValidKeys($p256dh, $auth)) {
            // На такие ключи не зашифровать никогда.
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
        } catch (\Throwable $e) {
            // Не Gone: подписка уже проверена выше, а те же исключения бросает и своя сторона (подпись VAPID,
            // OpenSSL, предупреждение, поднятое обработчиком ошибок) – Gone снёс бы все подписки разом.
            // Класс исключения – чтобы в мониторинге отличить сбой подписи от несовместимой версии библиотеки.
            throw new TransportError('Web push encryption failed (' . $e::class . ')');
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
