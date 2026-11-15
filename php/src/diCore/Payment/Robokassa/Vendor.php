<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.07.2017
 * Time: 11:54
 */

namespace diCore\Payment\Robokassa;

use diCore\Payment\VendorContainer;

class Vendor extends VendorContainer
{
    const YANDEX_MONEY = 1;
    const WEBMONEY = 2;
    const QIWI = 3;
    const ELECSNET = 4;
    const W1 = 5;

    const CARD = 10;
    const APPLE_PAY = 11;
    const SAMSUNG_PAY = 12;
    const YANDEX_PAY = 13;
    const SBP = 14;
    const SBERPAY = 15;
    const TPAY = 16;

    //const SBERBANK_ONLINE = 20;
    const ALPHA_CLICK = 21;

    const MOBILE_BEELINE = 31;
    const MOBILE_MEGAFON = 32;
    const MOBILE_MTS = 33;
    const MOBILE_TELE2 = 34;

    public static $titles = [
        self::YANDEX_MONEY => 'ЮMoney',
        self::WEBMONEY => 'Webmoney',
        self::QIWI => 'Qiwi',
        self::ELECSNET => 'Кошелек Элекснет',
        self::W1 => 'Единый кошелек',

        self::CARD => 'Банковская карта',
        self::APPLE_PAY => 'Apple Pay',
        self::SAMSUNG_PAY => 'Samsung Pay',
        self::YANDEX_PAY => 'Яндекс Pay',
        self::SBP => 'СБП',
        self::SBERPAY => 'SberPay',
        self::TPAY => 'T-Pay',

        //self::SBERBANK_ONLINE => 'Сбербанк-онлайн',
        self::ALPHA_CLICK => 'Альфа-клик',

        self::MOBILE_BEELINE => 'Билайн',
        self::MOBILE_MEGAFON => 'Мегафон',
        self::MOBILE_MTS => 'МТС',
        self::MOBILE_TELE2 => 'Теле2',
    ];

    public static $names = [
        self::YANDEX_MONEY => 'yandex-money',
        self::WEBMONEY => 'webmoney',
        self::QIWI => 'qiwi',
        self::ELECSNET => 'elecsnet',
        self::W1 => 'w1',

        self::CARD => 'card',
        self::APPLE_PAY => 'apple-pay',
        self::SAMSUNG_PAY => 'samsung-pay',
        self::YANDEX_PAY => 'yandex-pay',
        // Те же имена, что у Tinkoff\Vendor: по имени выбирается иконка кнопки.
        self::SBP => 'sbp',
        self::SBERPAY => 'sberpay',
        self::TPAY => 't-pay',

        //self::SBERBANK_ONLINE => 'sberbank-online',
        self::ALPHA_CLICK => 'alpha-click',

        self::MOBILE_BEELINE => 'mobile-beeline',
        self::MOBILE_MEGAFON => 'mobile-megafon',
        self::MOBILE_MTS => 'mobile-mts',
        self::MOBILE_TELE2 => 'mobile-tele2',
    ];

    public static $codes = [
        self::YANDEX_MONEY => 'YandexMerchantPS5R',
        self::WEBMONEY => 'WMR20PM',
        self::QIWI => 'Qiwi40PS',
        self::ELECSNET => 'ElecsnetWallet',
        self::W1 => 'W1RIBR',

        self::CARD => 'BankCardPSR',
        self::APPLE_PAY => 'ApplePayPSR',
        self::SAMSUNG_PAY => 'SamsungPayPSR',
        self::YANDEX_PAY => 'YandexPayPSR',
        // Метки – из ответа GetCurrencies магазина в конце файла.
        self::SBP => 'SBPPSR',
        self::SBERPAY => 'SberPayPSR',
        self::TPAY => 'TinkoffPayPSR',

        //self::SBERBANK_ONLINE => 'BankSberBank',
        self::ALPHA_CLICK => 'AlfaBank',

        self::MOBILE_BEELINE => 'RuRuBeelinePSR',
        self::MOBILE_MEGAFON => 'MixplatMegafonRIBR',
        self::MOBILE_MTS => 'MixplatMTSRIBR',
        self::MOBILE_TELE2 => 'MixplatTele2RIBR',
    ];

    public static $minLimits = [];
}

/**
 * https://auth.robokassa.ru/Merchant/WebService/Service.asmx/GetCurrencies?MerchantLogin=romantic2
 * Ответ на 15.11.2026:
<CurrenciesList xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://merchant.roboxchange.com/WebService/">
<Result>
<Code>0</Code>
</Result>
<Groups>
<Group Code="BankCard" Description="Банковской картой">
<Items>
<Currency Label="AlfaPayPSR" Alias="AlfaPay" Name="AlfaPay" MaxValue="1000000" />
<Currency Label="BankCardPSBR" Alias="BankCard" Name="Банковская карта" />
<Currency Label="BankCardPSR" Alias="BankCard" Name="Банковская карта" />
<Currency Label="Podeli30R" Alias="Podeli" Name="Подели" MinValue="300" MaxValue="35000" />
<Currency Label="SberPayPSR" Alias="SberPay" Name="SberPay" MinValue="1" MaxValue="20000000" />
<Currency Label="SBPPSBR" Alias="SBP" Name="Банковская карта" MaxValue="1000000" />
<Currency Label="TinkoffPayPSR" Alias="TinkoffPay" Name="TinkoffPay" MinValue="1" MaxValue="600000" />
<Currency Label="YandexPayPNR" Alias="YandexPay" Name="Яндекс.Пэй до 1 000 000 RUB" MinValue="10" MaxValue="1000000" />
<Currency Label="Card120DaysPSBR" Alias="Card120Days" Name="Карта 120 дней без %" />
<Currency Label="Card120DaysPSR" Alias="Card120Days" Name="Карта 120 дней без %" />
<Currency Label="CardHalvaPSBR" Alias="BankCardHalva" Name="Карта Халва" />
<Currency Label="CardHalvaPSR" Alias="BankCardHalva" Name="Карта Халва" />
<Currency Label="ERIPPSR" Alias="ERIP" Name="ЕРИП" MinValue="25" MaxValue="457000" />
<Currency Label="MtsPayPSBR" Alias="MTSPay" Name="МТС Pay" MaxValue="100000" />
<Currency Label="MtsPayPSR" Alias="MTSPay" Name="МТС Pay" MaxValue="100000" />
<Currency Label="SBPPSR" Alias="SBP" Name="Банковская карта" MaxValue="1000000" />
</Items>
</Group>
</Groups>
</CurrenciesList>
*/
