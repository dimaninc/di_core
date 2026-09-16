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
    public function testInitReturnsFalseInsteadOfThrowing(): void
    {
        $api = new CurlInitFailureApiStub('terminal', 'secret');

        $this->assertFalse($api->init($this->buildArgs()));
    }

    public function testErrorMatchesTheCurlExecPrefix(): void
    {
        $api = new CurlInitFailureApiStub('terminal', 'secret');
        $api->init($this->buildArgs());

        $this->assertStringStartsWith('cURL error:', $api->getError());
    }

    /**
     * $args несёт Token и чек с email/телефоном покупателя – это уходит в
     * логи и Sentry через getError(), так нельзя. Token не равен $secretKey
     * буквально (это sha256 от отсортированных args + Password), поэтому
     * проверяется отдельно, вычисленный тем же алгоритмом, что и сам класс.
     */
    public function testErrorDoesNotLeakTokenOrReceipt(): void
    {
        $api = new CurlInitFailureApiStub('terminal', 'secret');
        $args = $this->buildArgs();
        // Mirror buildQuery(): TerminalKey is merged in before Token is
        // derived, so the token must be computed the same way to match what
        // would actually have gone into the (never-sent) request body.
        $token = $this->genToken($api, ['TerminalKey' => 'terminal'] + $args);

        $api->init($args);

        $error = $api->getError();

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
        $api = new CurlInitFailureApiStub('terminal', 'secret');
        $this->primeWithPriorSuccessfulResponse($api);

        $api->init($this->buildArgs());

        $this->assertNull($api->paymentId);
        $this->assertNull($api->status);
        $this->assertNull($api->getPaymentUrl());
        $this->assertFalse($this->getRawResponse($api));
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
    protected function openCurl()
    {
        return false;
    }
}
