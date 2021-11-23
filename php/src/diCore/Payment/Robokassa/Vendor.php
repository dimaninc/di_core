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
<CurrenciesList xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://merchant.roboxchange.com/WebService/">
<Result>
<Code>0</Code>
</Result>
<Groups>
<Group Code="EMoney" Description="Электронным кошельком">
<Items>
<Currency Label="Qiwi40PS" Alias="QiwiWallet" Name="QIWI Кошелек"/>
<Currency Label="YandexMerchantPS5R" Alias="YandexMoney" Name="Яндекс.Деньги"/>
<Currency Label="WMR20PM" Alias="WMR" Name="WMR"/>
</Items>
</Group>
<Group Code="BankCard" Description="Банковской картой">
<Items>
<Currency Label="BankCardPSR" Alias="BankCard" Name="Банковская карта"/>
<Currency Label="YandexPayPSR" Alias="YandexPay" Name="Яндекс Pay" MaxValue="40000"/>
<Currency Label="GooglePayPSR" Alias="GooglePay" Name="Google Pay" MaxValue="40000"/>
<Currency Label="CardHalvaPSR" Alias="BankCardHalva" Name="Карта Халва"/>
<Currency Label="CardHomeCreditPSR" Alias="BankCardHomeCredit" Name="Карта Свобода"/>
<Currency Label="CardSovestPSR" Alias="BankCardSovest" Name="Карта Совесть"/>
<Currency Label="ApplePayPSR" Alias="ApplePay" Name="Apple Pay" MaxValue="40000"/>
<Currency Label="SamsungPayPSR" Alias="SamsungPay" Name="Samsung Pay" MaxValue="40000"/>
</Items>
</Group>
<Group Code="Terminals" Description="В терминале">
<Items>
<Currency Label="Qiwi40PS" Alias="QiwiWallet" Name="QIWI Кошелек"/>
</Items>
</Group>
<Group Code="Mobile" Description="Сотовые операторы">
<Items>
<Currency Label="RuRuBeelinePSR" Alias="PhoneBeeline" Name="Билайн" MaxValue="14354"/>
</Items>
</Group>
</Groups>
</CurrenciesList>
*/