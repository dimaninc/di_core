<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Robokassa\Helper;
use PHPUnit\Framework\TestCase;

/** Адрес, не дошедший до формы, Робокасса требует у покупателя сама. */
class RobokassaCustomerEmailTest extends TestCase
{
    public function testEmailGoesToTheForm(): void
    {
        $this->assertSame(
            ['Email' => 'buyer@example.com'],
            Helper::customerParams(['customerEmail' => ' buyer@example.com '])
        );
    }

    public function testEmptyEmailIsNotSent(): void
    {
        $this->assertSame([], Helper::customerParams(['customerEmail' => '']));
        $this->assertSame([], Helper::customerParams(['customerEmail' => null]));
        $this->assertSame([], Helper::customerParams([]));
    }
}
