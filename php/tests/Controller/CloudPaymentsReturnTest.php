<?php

namespace diCore\Tests\Controller;

use diCore\Controller\Payment;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\CloudPayments\Helper;
use diCore\Payment\ReturnSignature;
use diCore\Payment\System;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Возврат плательщика CloudPayments пускается к цели черновика только с подписью */
class CloudPaymentsReturnTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function open(string $subAction, array $query): CloudPaymentsReturnProbe
    {
        $_GET = $query;
        $c = new CloudPaymentsReturnProbe();
        $c->setParamsAr([$subAction]);
        $c->cloudPaymentsAction();

        return $c;
    }

    private static function sig(string $subAction, int $draftId): string
    {
        return ReturnSignature::sign(
            CloudPaymentsHelperStub::password,
            'cloud_payments',
            $subAction,
            $draftId
        );
    }

    public function testSignedSuccessGoesToTheTarget(): void
    {
        $c = $this->open('success', [
            'InvoiceId' => '7',
            'sig' => self::sig('success', 7),
        ]);

        $this->assertSame('target-' . Payment::STATUS_SUCCESS, $c->redirectedTo);
    }

    public function testSignedFailRecordsAFailure(): void
    {
        $c = $this->open('fail', [
            'InvoiceId' => '7',
            'sig' => self::sig('fail', 7),
        ]);

        $this->assertSame('target-' . Payment::STATUS_FAIL, $c->redirectedTo);
        $this->assertCount(1, $c->failures);
    }

    public static function forged(): array
    {
        return [
            'без подписи' => ['success', ['InvoiceId' => '7']],
            'подпись другого черновика' => [
                'success',
                ['InvoiceId' => '7', 'sig' => self::sig('success', 8)],
            ],
            // подпись успеха не открывает запись отказа
            'подпись успеха на отказе' => [
                'fail',
                ['InvoiceId' => '7', 'sig' => self::sig('success', 7)],
            ],
        ];
    }

    #[DataProvider('forged')]
    public function testUnsignedReturnGoesHomeAndTouchesNothing(
        string $subAction,
        array $query
    ): void {
        $c = $this->open($subAction, $query);

        $this->assertSame('home', $c->redirectedTo);
        $this->assertSame([], $c->failures);
        $this->assertFalse($c->draftLoaded);
    }
}

class CloudPaymentsReturnProbe extends Payment
{
    public ?string $redirectedTo = null;
    public array $failures = [];
    public bool $draftLoaded = false;

    public function __construct()
    {
        // Без конструктора родителя: он читает настройки оплаты проекта
    }

    protected function createCloudPayments(): Helper
    {
        return new CloudPaymentsHelperStub();
    }

    protected function initDraftOnly($draftId)
    {
        $this->draftLoaded = true;
        $draft = Draft::create();

        if ((int) $draftId === 7) {
            $draft->setId(7)->setPaySystem(System::cloud_payments);
        }

        $this->draft = $draft;

        return $this;
    }

    protected function onGatewayFailure($system, array $payload)
    {
        $this->failures[] = $payload;

        return $this;
    }

    protected function getTargetHref($status)
    {
        return 'target-' . $status;
    }

    protected function getUnsignedReturnHref(): string
    {
        return 'home';
    }

    protected function redirectTo($url)
    {
        $this->redirectedTo = $url;

        return $this;
    }
}

class CloudPaymentsHelperStub extends Helper
{
    const password = 'cp-secret';

    public static function log($message)
    {
    }
}
