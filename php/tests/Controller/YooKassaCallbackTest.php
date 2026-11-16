<?php

namespace diCore\Tests\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Controller\Payment;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Entity\PaymentReceipt\Model as Receipt;
use diCore\Payment\System;
use diCore\Payment\Yandex\Vendor;
use diCore\Payment\YooKassa\Helper;
use PHPUnit\Framework\TestCase;

/**
 * Уведомление ЮKassa не подписано: решает только ответ API. Тест держит, что
 * исход берётся из объекта платежа, а не из тела уведомления, и что каждый
 * исход отвечает ЮKassa так, чтобы она повторила ровно то, что стоит повторить.
 */
class YooKassaCallbackTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '185.71.76.5';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = [];
    }

    private function payment(array $over = []): array
    {
        return array_replace_recursive(
            [
                'id' => '2d5c0001-000f-5000-8000-18db351245c7',
                'status' => 'succeeded',
                'paid' => true,
                'test' => false,
                'amount' => ['value' => '100.00', 'currency' => 'RUB'],
                'metadata' => ['draft_id' => '7'],
                'payment_method' => [
                    'type' => 'bank_card',
                    'card' => ['source' => 'mir_pay'],
                ],
            ],
            $over
        );
    }

    private function controller(?array $payment, int $httpCode = 200): YooKassaProbe
    {
        $c = new YooKassaProbe();
        $c->helper = new YooKassaHelperStub();
        $c->helper->payment = $payment;
        $c->helper->httpCode = $httpCode;
        $c->helper->body =
            '{"type":"notification","event":"payment.succeeded","object":{"id":"2d5c0001-000f-5000-8000-18db351245c7","status":"canceled"}}';

        return $c;
    }

    private function notify(YooKassaProbe $c)
    {
        $c->setParamsAr(['notification']);

        return $c->yookassaAction();
    }

    /** Тело говорит «canceled», API – «succeeded»: верим API */
    public function testPaidByApiIsRecordedWithTheRealMethod(): void
    {
        $c = $this->controller($this->payment());

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame(['2d5c0001-000f-5000-8000-18db351245c7'], $c->receipts);
        $this->assertSame(Vendor::MIR_PAY, $c->receiptVendor);
        $this->assertSame([], $c->failures);
    }

    public function testReceiptNotSavedAsksForARetry(): void
    {
        $c = $this->controller($this->payment());
        $c->receiptOk = false;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(503);

        $this->notify($c);
    }

    public function testCanceledGoesToFailureDiagnostics(): void
    {
        $c = $this->controller(
            $this->payment([
                'status' => 'canceled',
                'paid' => false,
                'cancellation_details' => [
                    'party' => 'payment_network',
                    'reason' => 'insufficient_funds',
                ],
            ])
        );

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame([], $c->receipts);
        $this->assertSame('insufficient_funds', $c->failures[0]['reason']);
        $this->assertSame('payment_network', $c->failures[0]['party']);
    }

    public function testHoldIsNeitherPaymentNorFailure(): void
    {
        $c = $this->controller(
            $this->payment(['status' => 'waiting_for_capture', 'paid' => true])
        );

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame([], $c->receipts);
        $this->assertSame([], $c->failures);
        $this->assertCount(1, $c->holds);
    }

    public function testTestPaymentIsRefusedUnlessAllowed(): void
    {
        $c = $this->controller($this->payment(['test' => true]));

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame([], $c->receipts);

        $c = $this->controller($this->payment(['test' => true]));
        $c->acceptTest = true;
        $this->notify($c);
        $this->assertCount(1, $c->receipts);
    }

    /** Платёж того же магазина, но черновик другого шлюза */
    public function testForeignDraftIsLeftAlone(): void
    {
        $c = $this->controller($this->payment());
        $c->draftSystem = System::tinkoff;

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame([], $c->receipts);
    }

    public function testApiFailureAsksForARetry(): void
    {
        $c = $this->controller(null, 503);

        try {
            $this->notify($c);
            $this->fail('Без статуса платежа уведомление нельзя подтверждать');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, $c->apiFailures);
    }

    /** Обрыв связи до API – не «платёж неизвестен»: ЮKassa должна повторить */
    public function testTransportFailureAsksForARetry(): void
    {
        $c = $this->controller(null, 0);

        try {
            $this->notify($c);
            $this->fail('Обрыв связи нельзя подтверждать');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, $c->apiFailures);
    }

    /** Выдуманный id: ЮKassa его не знает, повтор ничего не даст */
    public function testUnknownPaymentIsAcknowledged(): void
    {
        $c = $this->controller(null, 404);

        $this->assertSame('OK', $this->notify($c));
        $this->assertSame([], $c->apiFailures);
    }

    /** Чужой адрес – сигнал, но не отказ: решает ответ API */
    public function testUnlistedAddressIsReportedButProcessed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $c = $this->controller($this->payment());

        $this->notify($c);

        $this->assertSame(['8.8.8.8'], $c->foreignIps);
        $this->assertCount(1, $c->receipts);
    }

    public function testGetIsNotANotification(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->notify($this->controller($this->payment()));
    }

    public function testReturnRecordsAPaymentTheApiConfirms(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['draft'] = '7';
        $c = $this->controller($this->payment());
        $c->storedPaymentId = '2d5c0001-000f-5000-8000-18db351245c7';
        $c->setParamsAr(['return']);

        $c->yookassaAction();

        $this->assertCount(1, $c->receipts);
        $this->assertSame('target-' . Payment::STATUS_SUCCESS, $c->redirectedTo);
    }

    public function testReturnAfterCancelGoesToFailure(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['draft'] = '7';
        $c = $this->controller(
            $this->payment(['status' => 'canceled', 'paid' => false])
        );
        $c->storedPaymentId = '2d5c0001-000f-5000-8000-18db351245c7';
        $c->setParamsAr(['return']);

        $c->yookassaAction();

        $this->assertSame('target-' . Payment::STATUS_FAIL, $c->redirectedTo);
    }

    /** Платёж чужого черновика по подсунутому ?draft= не проводится */
    public function testReturnIgnoresAPaymentOfAnotherDraft(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['draft'] = '7';
        $c = $this->controller($this->payment(['metadata' => ['draft_id' => '8']]));
        $c->storedPaymentId = '2d5c0001-000f-5000-8000-18db351245c7';
        $c->setParamsAr(['return']);

        $c->yookassaAction();

        $this->assertSame([], $c->receipts);
        $this->assertSame('target-' . Payment::STATUS_SUCCESS, $c->redirectedTo);
    }

    /** id платежа не сохранён – ждём уведомление, API не трогаем */
    public function testReturnWithoutStoredIdJustRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['draft'] = '7';
        $c = $this->controller($this->payment());
        $c->setParamsAr(['return']);

        $c->yookassaAction();

        $this->assertSame(0, $c->helper->fetches);
        $this->assertSame('target-' . Payment::STATUS_SUCCESS, $c->redirectedTo);
    }
}

class YooKassaProbe extends Payment
{
    public YooKassaHelperStub $helper;
    public int $draftSystem = System::yandex_kassa;
    public bool $receiptOk = true;
    public bool $acceptTest = false;
    public ?string $storedPaymentId = null;
    public array $receipts = [];
    public $receiptVendor = null;
    public array $failures = [];
    public array $holds = [];
    public array $foreignIps = [];
    public array $apiFailures = [];
    public ?string $redirectedTo = null;

    public function __construct()
    {
        // Без конструктора родителя: он читает настройки оплаты проекта
    }

    protected function createYooKassa(): Helper
    {
        return $this->helper;
    }

    protected function initDraftOnly($draftId)
    {
        $draft = Draft::create();

        if ((int) $draftId === 7) {
            $draft
                ->setId(7)
                ->setPaySystem($this->draftSystem)
                ->setVendor(Vendor::CARD)
                ->setAmount(100);
        }

        $this->draft = $draft;

        return $this;
    }

    protected function createReceipt($outerNumber, callable|null $beforeSave = null)
    {
        $this->receipts[] = $outerNumber;

        // Квитанция без БД: проверяется только способ, который ей выставят
        $receipt = Receipt::create();
        $receipt->setVendor(Vendor::CARD);

        if ($beforeSave) {
            $beforeSave($receipt);
        }

        $this->receiptVendor = (int) $receipt->getVendor();

        return ['ok' => $this->receiptOk];
    }

    protected function onGatewayFailure($system, array $payload)
    {
        $this->failures[] = $payload;

        return $this;
    }

    protected function onYooKassaHold(array $payment)
    {
        $this->holds[] = $payment;

        return $this;
    }

    protected function onYooKassaForeignIp(string $ip, string $event)
    {
        $this->foreignIps[] = $ip;

        return $this;
    }

    protected function onYooKassaApiFailure(string $paymentId, string $error)
    {
        $this->apiFailures[] = $paymentId;

        return $this;
    }

    protected function acceptsTestPayments()
    {
        return $this->acceptTest;
    }

    protected function getYooKassaPaymentId(Draft $draft): ?string
    {
        return $this->storedPaymentId;
    }

    protected function getTargetHref($status)
    {
        return 'target-' . $status;
    }

    protected function redirectTo($url)
    {
        $this->redirectedTo = $url;

        return $this;
    }
}

class YooKassaHelperStub extends Helper
{
    public ?array $payment = null;
    public int $httpCode = 200;
    public string $body = '';
    public int $fetches = 0;

    public function readNotification(?string $rawBody = null)
    {
        return parent::readNotification($rawBody ?? $this->body);
    }

    public function fetchPayment(string $paymentId): ?array
    {
        $this->fetches++;

        return $this->payment;
    }

    public function getApiHttpCode(): int
    {
        return $this->payment === null ? $this->httpCode : 200;
    }

    public function isUnknownPayment(): bool
    {
        return $this->payment === null && $this->httpCode === 404;
    }

    public function getApiError(): string
    {
        return $this->payment === null ? 'stubbed failure' : '';
    }

    public static function log($message)
    {
    }
}
