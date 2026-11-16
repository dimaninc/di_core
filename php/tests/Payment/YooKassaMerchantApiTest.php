<?php

namespace diCore\Tests\Payment;

use diCore\Payment\YooKassa\MerchantApi;
use PHPUnit\Framework\TestCase;

class YooKassaMerchantApiTest extends TestCase
{
    public function testPaymentIsReturnedAndRequestIsWellFormed(): void
    {
        $api = new StubYooKassaApi('100500', 'test_secret');
        $api->answers[] = [200, '{"id":"2d5c-0001","status":"pending"}', ''];

        $payment = $api->createPayment(['amount' => ['value' => '1.00']], 'draft-5');

        $this->assertSame('2d5c-0001', $payment['id']);
        $this->assertSame('POST', $api->sent[0]['method']);
        $this->assertSame(
            'https://api.yookassa.ru/v3/payments',
            $api->sent[0]['url']
        );
        $this->assertContains('Idempotence-Key: draft-5', $api->sent[0]['headers']);
        $this->assertSame('', $api->getError());
    }

    public function testGatewayErrorIsReported(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [
            400,
            '{"type":"error","code":"invalid_request","description":"Bad amount","parameter":"amount"}',
            '',
        ];

        $this->assertNull($api->createPayment([], 'draft-1'));
        $this->assertStringContainsString(
            'invalid_request Bad amount [amount]',
            $api->getError()
        );
        $this->assertSame(400, $api->getHttpCode());
    }

    /** 500 у ЮKassa – «исход неизвестен», не успех */
    public function testServerErrorIsNotASuccess(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [
            500,
            '{"type":"error","code":"internal_server_error"}',
            '',
        ];

        $this->assertNull($api->getPayment('2d5c0001-000f-5000-8000-18db351245c7'));
        $this->assertNotSame('', $api->getError());
        $this->assertFalse($api->isUnknownPayment());
    }

    public function testTransportFailure(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [0, false, 'Could not resolve host'];

        $this->assertNull($api->getPayment('2d5c0001-000f-5000-8000-18db351245c7'));
        $this->assertStringContainsString(
            'Could not resolve host',
            $api->getError()
        );
        $this->assertFalse($api->isUnknownPayment(), 'обрыв связи надо повторить');
    }

    public function testNotFoundIsAnUnknownPayment(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [404, '{"type":"error","code":"not_found"}', ''];

        $this->assertNull($api->getPayment('2d5c0001-000f-5000-8000-18db351245c7'));
        $this->assertTrue($api->isUnknownPayment());
    }

    /** Клиент кешируется: прошлый ответ не должен достаться следующему платежу */
    public function testStateIsResetBetweenCalls(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [200, '{"id":"aaaa-1111","status":"succeeded"}', ''];
        $api->answers[] = [0, false, 'timeout'];

        $api->getPayment('aaaa-1111');
        $this->assertSame(200, $api->getHttpCode());

        $api->getPayment('bbbb-2222');
        $this->assertSame(0, $api->getHttpCode());
        $this->assertFalse($api->getResponse());
        $this->assertStringContainsString('timeout', $api->getError());
    }

    /** Короткий таймаут возврата действует на один запрос и не прилипает */
    public function testFetchTimeoutIsRestored(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [200, '{"id":"aaaa-1111","status":"succeeded"}', ''];
        $h = new TimeoutHelper($api);

        $h->fetchPayment('aaaa-1111', 5);

        $this->assertSame([5], $api->timeouts);
        $this->assertSame(MerchantApi::TIMEOUT_SEC, $api->getTimeout());
    }

    /** id уходит в путь: «../» и прочее до сети не доходит */
    public function testMalformedPaymentIdNeverReachesTheNetwork(): void
    {
        $api = new StubYooKassaApi('1', 'k');

        $this->assertNull($api->getPayment('../refunds'));
        $this->assertSame([], $api->sent);
        $this->assertNull($api->getHttpCode());
        $this->assertTrue($api->isUnknownPayment());
    }

    /** Повтор уведомления после того, как ключи вписаны, проведёт оплату */
    public function testMissingCredentialsNeverReachTheNetwork(): void
    {
        foreach ([['', 'k'], ['1', '']] as [$shopId, $key]) {
            $api = new StubYooKassaApi($shopId, $key);

            $this->assertNull($api->getPayment('aaaa-1111'));
            $this->assertSame([], $api->sent);
            $this->assertSame(MerchantApi::ERROR_NO_CREDENTIALS, $api->getError());
            $this->assertFalse($api->isUnknownPayment());
        }
    }

    public function testOverlongIdempotenceKeyIsRefused(): void
    {
        $api = new StubYooKassaApi('1', 'k');

        $this->assertNull($api->createPayment([], str_repeat('k', 65)));
        $this->assertSame([], $api->sent);
    }

    public function testSuccessWithoutIdIsAnError(): void
    {
        $api = new StubYooKassaApi('1', 'k');
        $api->answers[] = [200, '{"status":"pending"}', ''];

        $this->assertNull($api->createPayment([], 'draft-1'));
    }
}

class StubYooKassaApi extends MerchantApi
{
    public array $answers = [];
    public array $sent = [];
    public array $timeouts = [];

    protected function send(
        string $method,
        string $url,
        ?string $body,
        array $headers
    ): array {
        $this->sent[] = compact('method', 'url', 'body', 'headers');
        $this->timeouts[] = $this->getTimeout();

        return array_shift($this->answers) ?? [0, false, 'no stubbed answer'];
    }
}

class TimeoutHelper extends \diCore\Payment\YooKassa\Helper
{
    public function __construct(MerchantApi $api)
    {
        $this->api = $api;
    }

    public static function log($message)
    {
    }
}
