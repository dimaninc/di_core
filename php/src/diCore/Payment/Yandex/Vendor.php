<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.07.2017
 * Time: 16:47
 */

namespace diCore\Payment\Yandex;

use diCore\Payment\VendorContainer;

class Vendor extends VendorContainer
{
    const YANDEX_MONEY = 1;
    const WEBMONEY = 2;
    const CARD = 3;
    const MOBILE = 4;
    const QIWI_WALLET = 5;
    const SBERBANK_ONLINE = 6;
    const ALPHA_CLICK = 7;
    const TERMINAL = 8;
    const MPOS = 9;
    const MASTER_PASS = 10;
    const PROM_SVYAZ_BANK = 11;
    const TINKOFF = 12;
    const QPPI = 13;
    const GOOGLE_PAY = 14;
    const APPLE_PAY = 15;
    const SBP = 16;
    const MIR_PAY = 17;

    public static $titles = [
        self::YANDEX_MONEY => 'ЮMoney', // Яндекс.Деньги
        self::WEBMONEY => 'WebMoney',
        self::CARD => 'Банковская карта',
        self::MOBILE => 'Баланс телефона',
        self::QIWI_WALLET => 'Qiwi',
        self::SBERBANK_ONLINE => 'Сбербанк-онлайн',
        self::ALPHA_CLICK => 'Альфа-клик',
        self::TERMINAL => 'Терминалы/кассы',
        self::MPOS => 'Моб.терминал MPos',
        self::MASTER_PASS => 'MasterPass',
        self::PROM_SVYAZ_BANK => 'ПромСвязьБанк',
        self::TINKOFF => 'КупиВКредит (Тинькофф Банк)',
        self::QPPI => 'Куппи.ру',
        self::GOOGLE_PAY => 'Google Pay',
        self::APPLE_PAY => 'Apple Pay',
        self::SBP => 'Система быстрых платежей',
        self::MIR_PAY => 'МИР Pay',
    ];

    public static $names = [
        self::YANDEX_MONEY => 'yandex-money',
        self::WEBMONEY => 'webmoney',
        self::CARD => 'card',
        self::MOBILE => 'mobile',
        self::QIWI_WALLET => 'qiwi',
        self::SBERBANK_ONLINE => 'sberbank-online',
        self::ALPHA_CLICK => 'alpha-click',
        self::TERMINAL => 'terminal',
        self::MPOS => 'mpos',
        self::MASTER_PASS => 'master-pass',
        self::PROM_SVYAZ_BANK => 'prom-svyaz-bank',
        self::TINKOFF => 'tinkoff',
        self::QPPI => 'qppi',
        self::GOOGLE_PAY => 'google-pay',
        self::APPLE_PAY => 'apple-pay',
        self::SBP => 'sbp',
        self::MIR_PAY => 'mir-pay',
    ];

    public static $codes = [
        self::YANDEX_MONEY => 'PC',
        self::WEBMONEY => 'WM',
        self::CARD => 'AC',
        self::MOBILE => 'MC',
        self::QIWI_WALLET => 'QW',
        self::SBERBANK_ONLINE => 'SB',
        self::ALPHA_CLICK => 'AB',
        self::TERMINAL => 'GP',
        self::MPOS => 'MP',
        self::MASTER_PASS => 'MA',
        self::PROM_SVYAZ_BANK => 'PB',
        self::TINKOFF => 'KV',
        self::QPPI => 'QP',
        self::GOOGLE_PAY => 'AC',
        self::APPLE_PAY => 'AC',
        self::SBP => 'CP',
        self::MIR_PAY => 'AC',
    ];

    public static $minLimits = [
        self::YANDEX_MONEY => null,
        self::WEBMONEY => null,
        self::CARD => null,
        self::MOBILE => 10,
        self::QIWI_WALLET => null,
        self::SBERBANK_ONLINE => 10,
        self::ALPHA_CLICK => null,
        self::TERMINAL => null,
        self::MPOS => null,
        self::MASTER_PASS => null,
        self::PROM_SVYAZ_BANK => null,
        self::TINKOFF => 3000,
        self::QPPI => 100,
        self::GOOGLE_PAY => null,
        self::APPLE_PAY => null,
        self::SBP => null,
        self::MIR_PAY => null,
    ];
}
