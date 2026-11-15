<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Payment;
use diCore\Payment\Robokassa\Vendor as Robokassa;
use diCore\Payment\System;
use diCore\Payment\Tinkoff\Vendor as Tinkoff;
use PHPUnit\Framework\TestCase;

/**
 * СБП, SberPay и T-Pay Робокассы подменяют одноимённые способы Т-Банка: проект
 * выбирает иконку по имени вендора, а список берёт у своего класса.
 */
class RobokassaTwinVendorsTest extends TestCase
{
    public static function twins(): array
    {
        return [
            'sbp' => [Tinkoff::SBP, Robokassa::SBP, 'SBP'],
            'sberpay' => [Tinkoff::SBERPAY, Robokassa::SBERPAY, 'SberPay'],
            't-pay' => [Tinkoff::TPAY, Robokassa::TPAY, 'TinkoffPay'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('twins')]
    public function testSameNameAsTinkoffAndOwnCode(
        int $tinkoffId,
        int $robokassaId,
        string $code
    ): void {
        $this->assertSame(Tinkoff::name($tinkoffId), Robokassa::name($robokassaId));
        $this->assertSame($code, Robokassa::code($robokassaId));
        $this->assertSame(
            $robokassaId,
            System::vendorIdByName(System::robokassa, Robokassa::name($robokassaId))
        );
    }

    /**
     * Вызов через базовый класс видит и список, и доступность проекта: T-Pay в
     * списке есть, но проект его прячет.
     */
    public function testBaseCallReadsProjectOverride(): void
    {
        $this->assertTrue(
            BaseStubPayment::isVendorUsed(System::robokassa, Robokassa::SBP)
        );
        $this->assertFalse(
            BaseStubPayment::isVendorUsed(System::tinkoff, Tinkoff::SBP)
        );

        $names = array_map(
            fn($row) => $row['vendor']['name'],
            array_values(
                array_filter(
                    BaseStubPayment::getTemplateData()['payment_vendor_rows']
                )
            )
        );
        $this->assertSame(['sbp'], $names);
    }
}

class BaseStubPayment extends Payment
{
    protected static $paymentVendorsUsed = [
        ['system' => System::tinkoff, 'vendors' => [Tinkoff::SBP]],
    ];

    public static function getClass()
    {
        return ProjectStubPayment::class;
    }

    public static function isPaymentMethodAvailable($systemId, $vendorId)
    {
        return true;
    }
}

class ProjectStubPayment extends BaseStubPayment
{
    public static function getPaymentVendorsUsed()
    {
        return [
            [
                'system' => System::robokassa,
                'vendors' => [Robokassa::SBP, Robokassa::TPAY],
            ],
        ];
    }

    /** Проект прячет способ: вызов через базовый класс обязан это увидеть. */
    public static function isPaymentMethodAvailable($systemId, $vendorId)
    {
        return $vendorId != Robokassa::TPAY;
    }
}
