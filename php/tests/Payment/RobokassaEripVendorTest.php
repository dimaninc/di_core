<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Robokassa\Vendor as Robokassa;
use diCore\Payment\System;
use PHPUnit\Framework\TestCase;

/** ЕРИП: код – Alias из GetCurrencies, иначе форма Робокассы откроет список способов. */
class RobokassaEripVendorTest extends TestCase
{
    public function testEripHasAliasCodeAndResolvesByName(): void
    {
        $this->assertSame('ERIP', Robokassa::code(Robokassa::ERIP));
        $this->assertSame('erip', Robokassa::name(Robokassa::ERIP));
        $this->assertSame(
            Robokassa::ERIP,
            System::vendorIdByName(System::robokassa, 'erip')
        );
        $this->assertSame(
            'ЕРИП',
            System::vendorTitle(System::robokassa, Robokassa::ERIP)
        );
    }
}
