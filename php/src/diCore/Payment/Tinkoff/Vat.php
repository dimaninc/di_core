<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.05.2019
 * Time: 18:33
 */

namespace diCore\Payment\Tinkoff;

use diCore\Tool\SimpleContainer;

class Vat extends SimpleContainer
{
    const none = 1;
    const vat0 = 2;
    const vat10 = 3;
    const vat20 = 4;

    public static $names = [
        self::none => 'none',
        self::vat0 => 'vat0',
        self::vat10 => 'vat10',
        self::vat20 => 'vat20',
    ];

    public static $titles = [
        self::none => 'Без НДС',
        self::vat0 => 'НДС 0%',
        self::vat10 => 'НДС 10%',
        self::vat20 => 'НДС 20%',
    ];
}