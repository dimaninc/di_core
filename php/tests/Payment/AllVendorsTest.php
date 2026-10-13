<?php

namespace diCore\Tests\Payment;

use diCore\Payment\CloudPayments\Vendor as CloudPaymentsVendor;
use diCore\Payment\Mixplat\MobileVendors;
use diCore\Payment\Payment;
use diCore\Payment\Robokassa\Vendor as RobokassaVendor;
use diCore\Payment\System;
use diCore\Payment\Tinkoff\Vendor as TinkoffVendor;
use PHPUnit\Framework\TestCase;

/**
 * Id способов разных систем пересекаются: ключ по одному id способа затирал
 * одни способы другими, и фильтр админки их терял.
 */
class AllVendorsTest extends TestCase
{
    public function testSameVendorIdOfDifferentSystemsIsKeptApart(): void
    {
        $this->assertSame(TinkoffVendor::CARD, RobokassaVendor::CARD);
        $this->assertSame(TinkoffVendor::CARD, CloudPaymentsVendor::FOREIGN_CARD);

        $vendors = AllVendorsProbePayment::getAllVendors();

        foreach (
            [
                [System::tinkoff, TinkoffVendor::CARD],
                [System::robokassa, RobokassaVendor::CARD],
                [System::cloud_payments, CloudPaymentsVendor::FOREIGN_CARD],
            ]
            as [$systemId, $vendorId]
        ) {
            $this->assertArrayHasKey(
                Payment::vendorFilterKey($systemId, $vendorId),
                $vendors
            );
        }
    }

    public function testListsEveryVendorNotOnlyUsedOnes(): void
    {
        $vendors = AllVendorsProbePayment::getAllVendors();

        $this->assertCount(
            count(TinkoffVendor::titles()) +
                count(RobokassaVendor::titles()) +
                count(CloudPaymentsVendor::titles()) +
                count(MobileVendors::titles()),
            $vendors
        );
    }

    public function testSkipsSystemsWithoutVendorContainer(): void
    {
        $vendors = AllVendorsProbePayment::getAllVendors();

        foreach (array_keys($vendors) as $key) {
            [$systemId] = Payment::parseVendorFilterKey($key);

            $this->assertNotContains($systemId, [System::paypal, System::sberbank]);
        }
    }

    // `getSystemClass(mixplat)` без vendorId отвечает `System::class` – операторы терялись
    public function testListsMixplatOperators(): void
    {
        $vendors = AllVendorsProbePayment::getAllVendors();

        foreach (array_keys(MobileVendors::titles()) as $vendorId) {
            $this->assertArrayHasKey(
                Payment::vendorFilterKey(System::mixplat, $vendorId),
                $vendors
            );
        }
    }

    public function testWhereSelectsSystemAndVendorTogether(): void
    {
        $this->assertSame(
            '(pay_system = 7 AND vendor = 10)',
            Payment::vendorFilterWhere('vendor', '7-10')
        );
        $this->assertSame(
            '(d.pay_system = 7 AND d.vendor = 10)',
            Payment::vendorFilterWhere('vendor', '7-10', 'd.')
        );
    }

    /**
     * Чужое значение не фильтрует: пункта для него в списке нет, и отбор был бы
     * невидим. Голый id – из cookie фильтра прежних версий, `!` – отрицание `int`-фильтра.
     */
    public function testForeignValueMeansNoFilter(): void
    {
        foreach (['10', '!10', '!7-10', 'abc', '0', '', '7-10 OR 1=1'] as $value) {
            $this->assertSame(
                '1=1',
                Payment::vendorFilterWhere('vendor', $value),
                $value
            );
        }
    }

    public function testParsesOnlyOwnKeys(): void
    {
        $this->assertSame([7, 10], Payment::parseVendorFilterKey('7-10'));
        $this->assertNull(Payment::parseVendorFilterKey('10'));
        $this->assertNull(Payment::parseVendorFilterKey(''));
        $this->assertNull(Payment::parseVendorFilterKey('7-10 OR 1=1'));
    }
}

class AllVendorsProbePayment extends Payment
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
