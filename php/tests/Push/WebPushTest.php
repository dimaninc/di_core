<?php

namespace diCore\Tests\Push;

use diCore\Push\Delivery;
use diCore\Push\Message;
use diCore\Push\TransportError;
use diCore\Push\WebPush;
use diCore\Push\WebPushSubscription;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WebPushTest extends TestCase
{
    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc';

    protected function setUp(): void
    {
        if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(Response::class)) {
            $this->markTestSkipped('minishlink/web-push and nyholm/psr7 are not installed');
        }
    }

    /** Ключи подписки, как их выдаёт браузер: точка P-256 и 16 байт auth. */
    private static function browserKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];
        $point = "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
        $b64 = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return [$b64($point), $b64(random_bytes(16))];
    }

    /** @param int|\Throwable $answer */
    private static function client(int|\Throwable $answer, ?array &$requests = null): ClientInterface
    {
        $requests = [];

        return new class ($answer, $requests) implements ClientInterface {
            public function __construct(private int|\Throwable $answer, private array &$requests)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                if ($this->answer instanceof \Throwable) {
                    throw $this->answer;
                }

                return new Response($this->answer);
            }
        };
    }

    private static function webPush(ClientInterface $client): WebPush
    {
        $keys = WebPush::createKeys();

        return new WebPush($keys['publicKey'], $keys['privateKey'], 'mailto:test@example.test', $client);
    }

    public function testSendsEncryptedPayloadWithVapidAndTtl(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        $push = self::webPush(self::client(201, $requests));

        $result = $push->send(self::ENDPOINT, $p256dh, $auth, new Message('T', 'B', ['id' => 5], null, 86400));

        $this->assertSame(Delivery::Delivered, $result);
        $this->assertCount(1, $requests);
        $request = $requests[0];
        $this->assertSame(self::ENDPOINT, (string) $request->getUri());
        $this->assertSame('86400', $request->getHeaderLine('TTL'));
        $this->assertSame('high', $request->getHeaderLine('Urgency'));
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        // Тело зашифровано: открытого текста в нём нет.
        $this->assertStringNotContainsString('"title"', (string) $request->getBody());
    }

    public function testGoneAndRetryableStatuses(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        foreach ([404, 410] as $status) {
            $this->assertSame(
                Delivery::Gone,
                self::webPush(self::client($status))->send(self::ENDPOINT, $p256dh, $auth, new Message('T', 'B')),
            );
        }
        foreach ([400, 413, 429, 500] as $status) {
            try {
                self::webPush(self::client($status))->send(self::ENDPOINT, $p256dh, $auth, new Message('T', 'B'));
                $this->fail('expected failure');
            } catch (TransportError $e) {
                $this->assertSame("Web push HTTP $status", $e->getMessage());
            }
        }
    }

    public function testNetworkFailureIsRetryable(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        $error = new class ('down') extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                throw new \LogicException();
            }
        };

        $this->expectExceptionObject(new TransportError('Web push service unavailable'));
        self::webPush(self::client($error))->send(self::ENDPOINT, $p256dh, $auth, new Message('T', 'B'));
    }

    public function testForeignEndpointFailsWithoutRequestAndIsKept(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        $push = self::webPush(self::client(201, $requests));

        try {
            $push->send('https://10.0.0.1/hook', $p256dh, $auth, new Message('T', 'B'));
            $this->fail('expected failure');
        } catch (TransportError $e) {
            $this->assertSame('Web push endpoint not allowed', $e->getMessage());
        }
        $this->assertSame([], $requests);
    }

    public function testKeysInPlainBase64AreSentNotDropped(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        $plain = static fn(string $key) => base64_encode(base64_decode(strtr($key, '-_', '+/')));
        $push = self::webPush(self::client(201, $requests));

        $this->assertSame(Delivery::Delivered, $push->send(self::ENDPOINT, $plain($p256dh), $plain($auth), new Message('T', 'B')));
        $this->assertCount(1, $requests);
        $this->assertSame(Delivery::Gone, $push->send(self::ENDPOINT, str_repeat('A', 87), $auth, new Message('T', 'B')));
    }

    public function testPayloadForServiceWorker(): void
    {
        $this->assertSame(
            '{"title":"Новое","body":"Текст","data":{"thread_id":"7"},"link":"/threads/7"}',
            (new Message('Новое', 'Текст', ['thread_id' => 7], '/threads/7'))->toWebPush(),
        );
        $this->assertSame('{"title":"T","body":"B","data":{}}', (new Message('T', 'B'))->toWebPush());
    }

    public function testSubscriptionPolicy(): void
    {
        foreach (
            [
                'https://fcm.googleapis.com/fcm/send/x',
                'https://updates.push.services.mozilla.com/wpush/v2/x',
                'https://web.push.apple.com/x',
                'https://wns2-par02p.notify.windows.com/w/?token=x',
                'https://push.yandex.ru/x',
            ]
            as $endpoint
        ) {
            $this->assertTrue(WebPushSubscription::isAllowedEndpoint($endpoint), $endpoint);
        }
        foreach (
            [
                'http://fcm.googleapis.com/x',
                'https://fcm.googleapis.com:8443/x',
                'https://user:pass@fcm.googleapis.com/x',
                'https://fcm.googleapis.com.evil.test/x',
                'https://evilfcm.googleapis.com.test/x',
                'https://127.0.0.1/x',
                "https://fcm.googleapis.com/x\n",
                'https://fcm.googleapis.com/' . str_repeat('a', 1024),
                'https://127.0.0.1%2f.fcm.googleapis.com/x',
                'https://127.0.0.1\\.fcm.googleapis.com/x',
            ]
            as $endpoint
        ) {
            $this->assertFalse(WebPushSubscription::isAllowedEndpoint($endpoint), $endpoint);
        }
        [$p256dh, $auth] = self::browserKeys();
        $this->assertTrue(WebPushSubscription::hasValidKeys($p256dh, $auth));
        $this->assertFalse(WebPushSubscription::hasValidKeys($p256dh . '=', $auth));
        $this->assertFalse(WebPushSubscription::hasValidKeys($p256dh, substr($auth, 1)));
        // Длина и алфавит верны, но это не несжатая точка – библиотека не зашифровала бы на неё никогда.
        $this->assertFalse(WebPushSubscription::hasValidKeys(str_repeat('A', 87), $auth));
        // Префикс 0x04 есть, но точка не на кривой.
        $this->assertFalse(WebPushSubscription::hasValidKeys('BA' . str_repeat('A', 85), $auth));
    }

    public function testBadVapidSettingsFailInConstructor(): void
    {
        $keys = WebPush::createKeys();
        $b64 = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $raw = base64_decode(strtr($keys['publicKey'], '-_', '+/'));
        $cases = [
            ['short', $keys['privateKey'], 'mailto:a@example.test', 'Web push VAPID keys invalid'],
            // 65 байт, но префикс не 0x04: библиотека проверяет только длину и возвращала бы Gone на каждой отправке.
            [$b64("\x05" . substr($raw, 1)), $keys['privateKey'], 'mailto:a@example.test', 'Web push VAPID keys invalid'],
            [$b64("\x04" . str_repeat("\x01", 64)), $keys['privateKey'], 'mailto:a@example.test', 'Web push VAPID keys invalid'],
            [$keys['publicKey'], 'short', 'mailto:a@example.test', 'Web push VAPID keys invalid'],
            [$keys['publicKey'], $keys['privateKey'], 'mailto:', 'Web push VAPID subject invalid'],
            [$keys['publicKey'], $keys['privateKey'], '', 'Web push VAPID subject invalid'],
            [$keys['publicKey'], $keys['privateKey'], 'not-a-url', 'Web push VAPID subject invalid'],
        ];
        foreach ($cases as $i => [$public, $private, $subject, $expected]) {
            try {
                new WebPush($public, $private, $subject, self::client(201));
                $this->fail("case $i must be refused");
            } catch (TransportError $e) {
                $this->assertSame($expected, $e->getMessage(), "case $i");
            }
        }
        $this->assertInstanceOf(WebPush::class, new WebPush($keys['publicKey'], $keys['privateKey'], 'https://example.test', self::client(201)));
    }

    public function testOversizedPayloadIsRefusedBeforeRequest(): void
    {
        [$p256dh, $auth] = self::browserKeys();
        $push = self::webPush(self::client(201, $requests));
        try {
            $push->send(self::ENDPOINT, $p256dh, $auth, new Message('T', str_repeat('x', 5000)));
            $this->fail('expected failure');
        } catch (TransportError $e) {
            $this->assertSame('Web push payload too large', $e->getMessage());
        }
        $this->assertSame([], $requests);
    }
}
