<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.12.2017
 * Time: 17:11
 */

namespace diCore\Payment\Tinkoff;

use diCore\Payment\VendorContainer;

class Vendor extends VendorContainer
{
    const BEELINE = 1;
    const MEGAFON = 2;
    const MTS = 3;
    const TELE2 = 4;

    const CARD = 10;
    const WEBMONEY = 11;
    const BANKING = 12;
    const SBP = 13;
    const SBERPAY = 14;

    const GOOGLE_PAY = 21;
    const APPLE_PAY = 22;
    const MIR_PAY = 23;

    public static $titles = [
        self::BEELINE => 'Билайн',
        self::MEGAFON => 'Мегафон',
        self::MTS => 'МТС',
        self::TELE2 => 'Теле2',

        self::CARD => 'Банковская карта',
        self::WEBMONEY => 'Webmoney',
        self::BANKING => 'Tinkoff',
        self::SBP => 'Система быстрых платежей',
        self::SBERPAY => 'SberPay',

        self::GOOGLE_PAY => 'Google Pay',
        self::APPLE_PAY => 'Apple Pay',
        self::MIR_PAY => 'Mir Pay',
    ];

    public static $names = [
        self::BEELINE => 'beeline',
        self::MEGAFON => 'megafon',
        self::MTS => 'mts',
        self::TELE2 => 'tele2',

        self::CARD => 'card',
        self::WEBMONEY => 'webmoney',
        self::BANKING => 'banking',
        self::SBP => 'sbp',
        self::SBERPAY => 'sberpay',

        self::GOOGLE_PAY => 'google-pay',
        self::APPLE_PAY => 'apple-pay',
        self::MIR_PAY => 'mir',
    ];

    public static $codes = [
        self::WEBMONEY => 'WMR',
    ];

    public static $minLimits = [
        self::BEELINE => null,
        self::WEBMONEY => null,
    ];
}
