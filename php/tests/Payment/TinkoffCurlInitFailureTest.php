<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Tinkoff\MerchantApi;
use PHPUnit\Framework\TestCase;

/**
 * До этого теста неудачный curl_init() шёл в `else`-ветку, бросавшую
 * `HttpException` – глобальный класс из pecl_http v1, отсутствующий в PHP 8
 * (`class_exists('HttpException') === false`). PHP не проверяет `use`, так
 * файл грузился, а до этой ветки долетал `Error: Class "HttpException" not
 * found`, а не исключение, которое вызывающий код умеет ловить.
 *
 * curl_init() в реальности не сломать, поэтому дескриптор открывается через
 * MerchantApi::openCurl(), переопределённый здесь на `false`.
 */
class TinkoffCurlInitFailureTest extends TestCase
{
    /**
     * Ловит удаление шва ДО запроса: счётчик в assertSeamWasUsed() отчитается
     * уже после того, как настоящий curl_init() сходит на боевой шлюз.
     */
    protected function setUp(): void
    {
        $this->assertTrue(
            method_exists(MerchantApi::class, 'openCurl'),
            'MerchantApi::openCurl() is gone – the transport seam must be restored'
        );
    }

    public function testInitReturnsFalseInsteadOfThrowing(): void
    {
        $api = $this->newApi();

        $this->assertFalse($api->init($this->buildArgs()));
        $this->assertSeamWasUsed($api);
    }

    public function testErrorMatchesTheCurlExecPrefix(): void
    {
        $api = $this->newApi();
        $api->init($this->buildArgs());

        $this->assertSeamWasUsed($api);
        $this->assertStringStartsWith('cURL error:', $api->getError());
    }

    /**
     * $args несёт Token, а через публичный buildQuery() потребитель может
     * положить туда что угодно вплоть до чека с email/телефоном покупателя –
     * это уходит в логи и Sentry через getError(), так нельзя. Token не равен
     * $secretKey буквально (это sha256 от отсортированных args + Password),
     * поэтому проверяется отдельно, вычисленный тем же алгоритмом, что и сам
     * класс.
     */
    public function testErrorDoesNotLeakTokenOrReceipt(): void
    {
        $api = $this->newApi();
        $args = $this->buildArgs();
        // Mirror buildQuery(): TerminalKey is merged in before Token is
        // derived, so the token must be computed the same way to match what
        // would actually have gone into the (never-sent) request body.
        $token = $this->genToken($api, ['TerminalKey' => 'terminal'] + $args);

        $api->init($args);

        $error = $api->getError();

        $this->assertSeamWasUsed($api);
        $this->assertStringNotContainsString('secret', $error);
        $this->assertStringNotContainsString($token, $error);
        $this->assertStringNotContainsString('buyer@example.com', $error);
        $this->assertStringNotContainsString('+79991234567', $error);
    }

    /**
     * Как и после сбоя curl_exec(), состояние прошлого ответа не должно
     * пережить сбой. Экземпляр предварительно заполнен данными успешного
     * ответа (через reflection – handleResponse() отдельно не пишет в
     * $response, это делает _sendRequest() до вызова), иначе assertNull
     * прошёл бы и без сброса: у свежего объекта эти поля и так null.
     */
    public function testResponseStateIsResetAfterFailure(): void
    {
        $api = $this->newApi();
        $this->primeWithPriorSuccessfulResponse($api);

        $api->init($this->buildArgs());

        $this->assertSeamWasUsed($api);
        $this->assertNull($api->paymentId);
        $this->assertNull($api->status);
        $this->assertNull($api->getPaymentUrl());
        $this->assertFalse($this->getRawResponse($api));
    }

    private function newApi(): CurlInitFailureApiStub
    {
        return new CurlInitFailureApiStub('terminal', 'secret');
    }

    /**
     * Если openCurl() останется на месте, но перестанет вызываться из
     * _sendRequest(), тест дойдёт до настоящего curl_init() и уйдёт живым
     * POST'ом на боевой securepay.tinkoff.ru – проверено: 2.8 с и ответ шлюза
     * «Терминал не найден» вместо ассерта.
     */
    private function assertSeamWasUsed(CurlInitFailureApiStub $api): void
    {
        $this->assertSame(
            1,
            $api->openCurlCalls,
            'openCurl() override was not used – the call went to the live gateway'
        );
    }

    private function buildArgs(): array
    {
        return [
            'Amount' => 10000,
            'OrderId' => 'order-1',
            'Receipt' => [
                'Email' => 'buyer@example.com',
                'Phone' => '+79991234567',
                'Taxation' => 'usn_income',
                'Items' => [],
            ],
        ];
    }

    private function genToken(MerchantApi $api, array $args): string
    {
        $method = (new \ReflectionClass(MerchantApi::class))->getMethod('_genToken');
        $method->setAccessible(true);

        return $method->invoke($api, $args);
    }

    /** Fills the private response/paymentId/status/paymentUrl fields as a prior successful call would. */
    private function primeWithPriorSuccessfulResponse(MerchantApi $api): void
    {
        $reflection = new \ReflectionClass(MerchantApi::class);

        $values = [
            'response' => json_encode([
                'Success' => true,
                'ErrorCode' => '0',
                'PaymentId' => '111222333',
                'Status' => 'NEW',
                'PaymentURL' => 'https://securepay.tinkoff.ru/rest/Pay/111222333',
            ]),
            'paymentId' => '111222333',
            'status' => 'NEW',
            'paymentUrl' => 'https://securepay.tinkoff.ru/rest/Pay/111222333',
        ];

        foreach ($values as $property => $value) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($api, $value);
        }
    }

    private function getRawResponse(MerchantApi $api)
    {
        $prop = (new \ReflectionClass(MerchantApi::class))->getProperty('response');
        $prop->setAccessible(true);

        return $prop->getValue($api);
    }
}

class CurlInitFailureApiStub extends MerchantApi
{
    /** @var int доказательство, что шов сработал – см. assertSeamWasUsed() */
    public $openCurlCalls = 0;

    protected function openCurl()
    {
        $this->openCurlCalls++;

        return false;
    }
}
