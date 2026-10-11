<?php

namespace diCore\Tests\Payment;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\Tinkoff\Helper;
use diCore\Payment\Tinkoff\Vendor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The vendor of a receipt comes from the notification's `Data.Source`. A key
 * the gateway never sends is not an error anywhere – the receipt just keeps
 * the draft's vendor, so a typo there stays invisible.
 */
class TinkoffTuneVendorTest extends TestCase
{
    /** Spelled as they arrive in notifications (prod log, 41 days). */
    public static function sources(): array
    {
        return [
            'cards' => ['cards', Vendor::CARD],
            'SberPay' => ['SberPay', Vendor::SBERPAY],
            'qrsbp' => ['qrsbp', Vendor::SBP],
            'TinkoffPay' => ['TinkoffPay', Vendor::TPAY],
        ];
    }

    #[DataProvider('sources')]
    public function testKnownSourceSetsVendor(string $source, int $vendor): void
    {
        $draft = $this->draftWithVendor(Vendor::SBP + 100);

        (new SilentTinkoffHelper())->tuneVendor($draft, $source);

        $this->assertSame($vendor, (int) $draft->getVendor());
    }

    public function testUnknownOrEmptySourceKeepsTheDraftVendor(): void
    {
        foreach (['AlfaPay', '', null] as $source) {
            $draft = $this->draftWithVendor(Vendor::SBP);

            (new SilentTinkoffHelper())->tuneVendor($draft, $source);

            $this->assertSame(Vendor::SBP, (int) $draft->getVendor());
        }
    }

    /** A homoglyph in a key (Cyrillic «с») is a key nothing ever matches. */
    public function testKeysArePlainLowercaseAscii(): void
    {
        foreach (array_keys(Helper::SOURCE_VENDORS) as $key) {
            $this->assertMatchesRegularExpression('/^[a-z]+$/', $key);
        }
    }

    private function draftWithVendor(int $vendor): Draft
    {
        $draft = new Draft();
        $draft->setVendor($vendor);

        return $draft;
    }
}

class SilentTinkoffHelper extends Helper
{
    public static function log($message)
    {
    }
}
