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
     * логи и Sentry через getError(), так нельзя.
     */
    public function testErrorDoesNotLeakTokenOrReceipt(): void
    {
        $api = new CurlInitFailureApiStub('terminal', 'secret');
        $api->init($this->buildArgs());

        $error = $api->getError();

        $this->assertStringNotContainsString('secret', $error);
        $this->assertStringNotContainsString('buyer@example.com', $error);
        $this->assertStringNotContainsString('+79991234567', $error);
    }

    /** Как и после сбоя curl_exec(), состояние прошлого ответа не должно пережить сбой. */
    public function testResponseStateIsResetAfterFailure(): void
    {
        $api = new CurlInitFailureApiStub('terminal', 'secret');
        $api->init($this->buildArgs());

        $this->assertNull($api->paymentId);
        $this->assertNull($api->status);
        $this->assertNull($api->getPaymentUrl());
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
}

class CurlInitFailureApiStub extends MerchantApi
{
    protected function openCurl()
    {
        return false;
    }
}
