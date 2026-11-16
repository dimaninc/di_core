<?php

namespace diCore\Tests\Payment;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\Robokassa\Helper;
use diCore\Payment\Robokassa\Vendor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Адрес, не дошедший до формы, Робокасса требует у покупателя сама. */
class RobokassaCustomerEmailTest extends TestCase
{
    public function testEmailGoesToTheForm(): void
    {
        $form = $this->form(' buyer@example.com ');

        $this->assertStringContainsString(
            'name="Email" value="buyer@example.com"',
            $form
        );
    }

    public static function unusableEmails(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'array' => [['buyer@example.com']],
            'zero' => ['0'],
            'inner space' => ['buy er@example.com'],
            'no domain' => ['buyer@'],
        ];
    }

    /** Некорректный адрес не передаётся: пусть форма спросит, чем откажет. */
    #[DataProvider('unusableEmails')]
    public function testUnusableEmailIsNotSent($email): void
    {
        $this->assertStringNotContainsString('name="Email"', $this->form($email));
    }

    private function form($email): string
    {
        $draft = new Draft();
        $draft
            ->setId(1)
            ->setAmount(299)
            ->setVendor(Vendor::SBP);

        return QuietRobokassaHelper::getForm($draft, ['customerEmail' => $email]);
    }
}

class QuietRobokassaHelper extends Helper
{
    const login = 'test';
    const password1 = 'test';

    public static function log($message) {}
}
