<?php

namespace diCore\Tests\Push;

use diCore\Push\Delivery;
use diCore\Push\Fcm;
use diCore\Push\Message;
use diCore\Push\RuStore;
use diCore\Push\TransportError;
use PHPUnit\Framework\TestCase;

/** Токен устройства и ответ сервиса не должны попадать в текст ошибки – он уходит в журналы. */
class FcmRuStoreTest extends TestCase
{
    private string $file;
    private int $time = 10000;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $this->file = tempnam(sys_get_temp_dir(), 'push-key');
        file_put_contents(
            $this->file,
            json_encode(['project_id' => 'test-project', 'client_email' => 'test@example.test', 'private_key' => $pem]),
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    private static function message(?int $ttl = null): Message
    {
        return new Message('Заголовок', 'Текст', ['project_id' => 1, 'thread_id' => '2'], 'app://push?thread=2', $ttl);
    }

    private function fcm(\Closure $http): Fcm
    {
        return new Fcm($this->file, $http, fn() => $this->time);
    }

    private static function oauthOr(array $reply): \Closure
    {
        return fn($url) => str_contains($url, 'oauth2.googleapis.com')
            ? [200, ['access_token' => 'oauth', 'expires_in' => 3600]]
            : $reply;
    }

    public function testFcmSendsV1MessageWithStringData(): void
    {
        $request = null;
        $fcm = $this->fcm(function ($url, $body, $headers) use (&$request) {
            if (str_contains($url, 'oauth2.googleapis.com')) {
                return [200, ['access_token' => 'oauth', 'expires_in' => 3600]];
            }
            $request = [$url, json_decode($body, true), $headers];

            return [200, []];
        });

        $this->assertSame(Delivery::Delivered, $fcm->send('device', self::message(86400)));
        $this->assertSame('https://fcm.googleapis.com/v1/projects/test-project/messages:send', $request[0]);
        $this->assertSame(
            [
                'token' => 'device',
                'notification' => ['title' => 'Заголовок', 'body' => 'Текст'],
                'data' => ['project_id' => '1', 'thread_id' => '2'],
                'android' => ['ttl' => '86400s'],
            ],
            $request[1]['message'],
        );
        $this->assertContains('Authorization: Bearer oauth', $request[2]);
    }

    public function testFcmConfigurationFailuresKeepSafeDiagnostics(): void
    {
        $cases = [
            ['', null, 'FCM not configured'],
            [$this->file . '-missing', null, 'FCM service account unreadable'],
            [$this->file, 'private-token invalid json', 'FCM service account invalid JSON'],
            [$this->file, 'null', 'FCM service account missing required fields'],
            [$this->file, '{"project_id":[]}', 'FCM service account missing required fields'],
            [
                $this->file,
                json_encode(['project_id' => 'test', 'client_email' => 'test@example.test', 'private_key' => 'private-token']),
                'FCM signing failed',
            ],
        ];
        foreach ($cases as [$path, $contents, $expected]) {
            if ($contents !== null) {
                file_put_contents($this->file, $contents);
            }
            try {
                (new Fcm($path, fn() => $this->fail('Invalid credentials must not reach the provider')))->send(
                    'private-token',
                    self::message(),
                );
                $this->fail('expected failure');
            } catch (TransportError $e) {
                $this->assertSame($expected, $e->getMessage());
                $this->assertNull($e->getPrevious());
            }
        }
    }

    public function testFcmHttpFailuresKeepStatusWithoutProviderBody(): void
    {
        foreach ([401, 500] as $status) {
            foreach ([true, false] as $oauthFails) {
                $fcm = $this->fcm(
                    fn($url) => str_contains($url, 'oauth2.googleapis.com') && !$oauthFails
                        ? [200, ['access_token' => 'private-oauth', 'expires_in' => 3600]]
                        : [$status, ['error' => ['message' => 'private-token']]],
                );
                try {
                    $fcm->send('private-token', self::message());
                    $this->fail('expected failure');
                } catch (TransportError $e) {
                    $this->assertSame(
                        $oauthFails ? "FCM authorization failed (HTTP $status)" : "FCM HTTP $status",
                        $e->getMessage(),
                    );
                }
            }
        }
    }

    public function testFcmOAuthTokenIsReusedAndRefreshedBeforeExpiration(): void
    {
        $oauth = 0;
        $sent = [];
        $fcm = $this->fcm(function ($url, $body, $headers) use (&$oauth, &$sent) {
            if (str_contains($url, 'oauth2.googleapis.com')) {
                $oauth++;

                return [200, ['access_token' => "oauth-$oauth", 'expires_in' => 3600]];
            }
            $sent[] = $headers;

            return [200, []];
        });
        $fcm->send('device', self::message());
        // Пока токен жив, ключ повторно не читается.
        $key = file_get_contents($this->file);
        file_put_contents($this->file, 'broken');
        $fcm->send('device', self::message());
        $this->assertSame(1, $oauth);
        file_put_contents($this->file, $key);
        $this->time += 3541;
        $fcm->send('device', self::message());
        $this->assertSame(2, $oauth);
        $this->assertContains('Authorization: Bearer oauth-1', $sent[1]);
        $this->assertContains('Authorization: Bearer oauth-2', $sent[2]);
    }

    public function testFcm401DropsCachedToken(): void
    {
        $oauth = 0;
        $fcm = $this->fcm(function ($url) use (&$oauth) {
            if (str_contains($url, 'oauth2.googleapis.com')) {
                $oauth++;

                return [200, ['access_token' => 'oauth', 'expires_in' => 3600]];
            }

            return [401, []];
        });
        foreach ([1, 2] as $_) {
            try {
                $fcm->send('device', self::message());
            } catch (TransportError) {
            }
        }
        $this->assertSame(2, $oauth);
    }

    public function testFcmInvalidTokenIsDistinguishedFromBadPayload(): void
    {
        $fcmError = 'type.googleapis.com/google.firebase.fcm.v1.FcmError';
        $invalid = ['@type' => $fcmError, 'errorCode' => 'INVALID_ARGUMENT'];
        $bad = ['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => [['field' => 'message.data']]];
        $reply = fn(array $details) => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'details' => $details]]];

        foreach ([[$invalid], [['@type' => $fcmError, 'errorCode' => 'UNREGISTERED']]] as $details) {
            $this->assertSame(Delivery::Gone, $this->fcm(self::oauthOr($reply($details)))->send('device', self::message()));
        }
        foreach ([[], [$bad], [$bad, $invalid]] as $details) {
            try {
                $this->fcm(self::oauthOr($reply($details)))->send('device', self::message());
                $this->fail('Payload error must keep the device');
            } catch (TransportError $e) {
                $this->assertSame('FCM HTTP 400', $e->getMessage());
            }
        }
    }

    public function testRuStoreSendsDeepLinkAndTtl(): void
    {
        $request = null;
        $ruStore = new RuStore('test-project', 'service-token', function ($url, $body, $headers) use (&$request) {
            $request = [$url, json_decode($body, true), $headers];

            return [200, []];
        });

        $this->assertSame(Delivery::Delivered, $ruStore->send('device', self::message(3600)));
        $this->assertSame('https://vkpns.rustore.ru/v1/projects/test-project/messages:send', $request[0]);
        $this->assertSame(
            [
                'ttl' => '3600s',
                'notification' => ['click_action_type' => 1, 'click_action' => 'app://push?thread=2'],
            ],
            $request[1]['message']['android'],
        );
        $this->assertSame(['title' => 'Заголовок', 'body' => 'Текст'], $request[1]['message']['notification']);
        $this->assertContains('Authorization: Bearer service-token', $request[2]);
    }

    public function testRuStoreGoneAndRetryableAnswers(): void
    {
        $gone = [
            [404, ['error' => ['status' => 'NOT_FOUND']]],
            [404, []],
            [400, ['error' => ['status' => 'UNREGISTERED']]],
            [400, ['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]]],
            [400, ['error' => ['message' => 'The registration token is not a valid FCM registration token']]],
        ];
        foreach ($gone as $reply) {
            $this->assertSame(Delivery::Gone, (new RuStore('p', 't', fn() => $reply))->send('device', self::message()));
        }
        foreach ([[400, ['error' => ['message' => 'bad payload']]], [500, []], [401, []]] as [$status, $body]) {
            try {
                (new RuStore('p', 't', fn() => [$status, $body]))->send('device', self::message());
                $this->fail('expected failure');
            } catch (TransportError $e) {
                $this->assertSame("RuStore HTTP $status", $e->getMessage());
            }
        }
    }

    public function testRuStoreWithoutSettingsFailsBeforeRequest(): void
    {
        $this->expectExceptionObject(new TransportError('RuStore not configured'));
        (new RuStore('', 't', fn() => $this->fail('must not send')))->send('device', self::message());
    }
}
