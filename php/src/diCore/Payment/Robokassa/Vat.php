<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.06.2018
 * Time: 19:08
 */

namespace diCore\Payment\Robokassa;

use diCore\Tool\SimpleContainer;

class Vat extends SimpleContainer
{
    const none = 1;
    const vat0 = 2;
    const vat10 = 3;
    const vat18 = 4;
    const vat110 = 5;
    const vat118 = 6;

    public static $names = [
        self::none => 'none',
        self::vat0 => 'vat0',
        self::vat10 => 'vat10',
        self::vat18 => 'vat18',
        self::vat110 => 'vat110',
        self::vat118 => 'vat118',
    ];

    public static $titles = [
        self::none => 'без НДС',
        self::vat0 => 'НДС по ставке 0%',
        self::vat10 => 'НДС чека по ставке 10%',
        self::vat18 => 'НДС чека по ставке 18%',
        self::vat110 => 'НДС чека по расчетной ставке 10/110',
        self::vat118 => 'НДС чека по расчетной ставке 18/118',
    ];
}