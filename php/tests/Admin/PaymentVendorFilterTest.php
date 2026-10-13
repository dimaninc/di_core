<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Page\PaymentDrafts;
use diCore\Payment\CloudPayments\Vendor as CloudPaymentsVendor;
use diCore\Payment\Mixplat\MobileVendors;
use diCore\Payment\Payment;
use diCore\Payment\Robokassa\Vendor as RobokassaVendor;
use diCore\Payment\System;
use diCore\Payment\Tinkoff\Vendor as TinkoffVendor;
use PHPUnit\Framework\TestCase;

/**
 * Фильтр «способ» в списках платежей зависит от системы: id способов разных систем
 * пересекаются (карта Т-Банка, Робокассы и CloudPayments – все 10).
 */
class PaymentVendorFilterTest extends TestCase
{
    public function testListsEveryVendorOfTheSystemNotOnlyUsedOnes(): void
    {
        $this->assertSame(
            TinkoffVendor::titles(),
            PaymentVendorFilterProbe::getSystemVendors(System::tinkoff)
        );
    }

    // `getSystemClass(mixplat)` без vendorId отвечает `System::class` – операторы терялись
    public function testListsMixplatOperators(): void
    {
        $this->assertSame(
            MobileVendors::titles(),
            PaymentVendorFilterProbe::getSystemVendors(System::mixplat)
        );
    }

    public function testNoVendorsMeansNoVendorFilter(): void
    {
        foreach (
            [
                0, // система не выбрана
                System::paypal, // нет своих способов
                System::sberbank, // нет в `getSystemClass()`
                System::yandex_kassa, // нет в `getCurrentSystems()`
            ]
            as $systemId
        ) {
            $this->assertSame(
                [],
                PaymentVendorFilterProbe::getSystemVendors($systemId),
                "system $systemId"
            );
        }
    }

    public function testSystemIdIsReadOnlyFromAPlainId(): void
    {
        $this->assertSame(7, PaymentDrafts::filterSystemId('7'));
        $this->assertSame(7, PaymentDrafts::filterSystemId(7));

        foreach (['', null, '!7', 'abc', '7 OR 1=1', ['7']] as $value) {
            $this->assertSame(0, PaymentDrafts::filterSystemId($value));
        }
    }

    public function testWhereFiltersByVendorOfTheSelectedSystem(): void
    {
        $vendors = RobokassaVendor::titles();

        $this->assertSame(
            'vendor = 10',
            PaymentDrafts::vendorFilterWhere('vendor', 10, $vendors, false)
        );
        $this->assertSame(
            'd.vendor != 10',
            PaymentDrafts::vendorFilterWhere('vendor', 10, $vendors, true, 'd.')
        );
    }

    // Остался в cookie после смены системы: в селекте его нет, отбор был бы невидим
    public function testVendorOfAnotherSystemDoesNotFilter(): void
    {
        $this->assertArrayNotHasKey(
            TinkoffVendor::TPAY,
            CloudPaymentsVendor::titles()
        );

        $this->assertSame(
            '1=1',
            PaymentDrafts::vendorFilterWhere(
                'vendor',
                TinkoffVendor::TPAY,
                CloudPaymentsVendor::titles(),
                false
            )
        );
    }
}

class PaymentVendorFilterProbe extends Payment
{
    public static function getCurrentSystems()
    {
        return array_intersect_key(
            System::$titles,
            array_flip([
                System::tinkoff,
                System::robokassa,
                System::cloud_payments,
                System::mixplat,
                System::paypal,
                System::sberbank,
            ])
        );
    }
}
