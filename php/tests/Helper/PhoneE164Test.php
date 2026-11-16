<?php

namespace diCore\Tests\Helper;

use diCore\Helper\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** E.164 для кассы и чека ЮKassa: невалидный номер – пустая строка, не догадка */
class PhoneE164Test extends TestCase
{
    public static function phones(): array
    {
        return [
            'российский через 8' => ['8 (900) 123-45-67', '+79001234567'],
            'без кода страны' => ['9001234567', '+79001234567'],
            'с плюсом' => ['+7 900 123-45-67', '+79001234567'],
            'через 00' => ['0079001234567', '+79001234567'],
            'лишний плюс у 8' => ['+8 900 123 45 67', '+79001234567'],
            'Япония' => ['+81 3 1234 5678', '+81312345678'],
            'Ливан' => ['+961 1 234 567', '+9611234567'],
            'добавочный' => ['+7 900 123 45 67 доб. 12', ''],
            'короткий' => ['12345', ''],
            'пусто' => ['', ''],
        ];
    }

    #[DataProvider('phones')]
    public function testE164(string $phone, string $expected): void
    {
        $this->assertSame($expected, Phone::e164($phone));
    }
}
