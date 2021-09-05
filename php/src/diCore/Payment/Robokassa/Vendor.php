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

		//self::SBERBANK_ONLINE => 'sberbank-online',
		self::ALPHA_CLICK => 'alpha-click',

        self::MOBILE_BEELINE => 'mobile-beeline',
        self::MOBILE_MEGAFON => 'mobile-megafon',
        self::MOBILE_MTS => 'mobile-mts',
        self::MOBILE_TELE2 => 'mobile-tele2',
	];

	public static $codes = [
		/*
		<Currency Label="Qiwi50RIBRM" Alias="QiwiWallet" Name="QIWI Кошелек" MinValue="1" MaxValue="14999"/>
		<Currency Label="YandexMerchantRIBR" Alias="YandexMoney" Name="Яндекс.Деньги" MinValue="1" MaxValue="14999"/>
		<Currency Label="WMR30RM" Alias="WMR" Name="WMR"/>
		<Currency Label="ElecsnetWalletRIBR" Alias="ElecsnetWallet" Name="Кошелек Элекснет" MaxValue="14999"/>
		<Currency Label="W1RIBR" Alias="W1" Name="RUR " MaxValue="14999"/>
		*/
		self::YANDEX_MONEY => 'YandexMerchantRIBR',
		self::WEBMONEY => 'WMR20RM',
		self::QIWI => 'Qiwi50RIBRM',
		self::ELECSNET => 'ElecsnetWallet',
		self::W1 => 'W1RIBR',

		/*
		<Currency Label="BANKOCEAN3R" Alias="BankCard" Name="Банковская карта"/>
		<Currency Label="ApplePayRIBR" Alias="ApplePay" Name="Apple Pay"/>
		<Currency Label="SamsungPayRIBR" Alias="SamsungPay" Name="Samsung Pay"/>
		 */
		self::CARD => 'BankCard',
		self::APPLE_PAY => 'ApplePay',
		self::SAMSUNG_PAY => 'SamsungPay',

		//self::SBERBANK_ONLINE => 'BankSberBank',
		self::ALPHA_CLICK => 'AlfaBank',

        self::MOBILE_BEELINE => 'MixplatBeelineRIBR',
        self::MOBILE_MEGAFON => 'MixplatMegafonRIBR',
        self::MOBILE_MTS => 'MixplatMTSRIBR',
        self::MOBILE_TELE2 => 'MixplatTele2RIBR',
	];

	public static $minLimits = [];

}
/**
<Group Code="Bank" Description="Через интернет-банк">
<Items>
<Currency Label="BankSberBankRIB" Alias="BankSberBank" Name="Банковская карта"/>
<Currency Label="AlfaBankRIBR" Alias="AlfaBank" Name="Альфа-Клик"/>
<Currency Label="RussianStandardBankRIBR" Alias="BankRSB" Name="Банк Русский Стандарт"/>
<Currency Label="VTB24RIBR" Alias="VTB24" Name="ВТБ24" MinValue="1000"/>
<Currency Label="W1RIBPSBR" Alias="W1" Name="RUR Единый кошелек" MaxValue="14999"/>
<Currency Label="MINBankRIBR" Alias="BankMIN" Name="Московский Индустриальный Банк"/>
<Currency Label="BSSIntezaRIBR" Alias="BankInteza" Name="Банк Интеза" MaxValue="15000"/>
<Currency Label="BSSAvtovazbankR" Alias="BankAVB" Name="Банк АВБ"/>
<Currency Label="FacturaBinBank" Alias="BankBin" Name="БИНБАНК"/>
<Currency Label="BSSFederalBankForInnovationAndDevelopmentR" Alias="BankFBID" Name="ФБ Инноваций и Развития"/>
<Currency Label="BSSMezhtopenergobankR" Alias="BankMTEB" Name="Межтопэнергобанк"/>
<Currency Label="FacturaSovCom" Alias="BankSovCom" Name="Совкомбанк"/>
<Currency Label="BSSNationalBankTRUSTR" Alias="BankTrust" Name="Национальный банк ТРАСТ"/>
<Currency Label="HandyBankRIBR" Alias="HandyBank" Name="HandyBank"/>
<Currency Label="HandyBankBO" Alias="HandyBankBO" Name="Банк Образование"/>
<Currency Label="HandyBankFB" Alias="HandyBankFB" Name="ФлексБанк"/>
<Currency Label="HandyBankFU" Alias="HandyBankFU" Name="ФьючерБанк"/>
<Currency Label="HandyBankKB" Alias="HandyBankKB" Name="КранБанк"/>
<Currency Label="HandyBankKSB" Alias="HandyBankKSB" Name="Костромаселькомбанк"/>
<Currency Label="HandyBankNSB" Alias="HandyBankNSB" Name="Независимый строительный банк"/>
<Currency Label="HandyBankVIB" Alias="HandyBankVIB" Name="ВестИнтерБанк"/>
<Currency Label="KUBankR" Alias="KUBank" Name="Кредит Урал Банк"/>
</Items>
</Group>
<Group Code="Terminals" Description="В терминале">
<Items>
<Currency Label="Qiwi50RIBRM" Alias="QiwiWallet" Name="QIWI Кошелек" MinValue="1" MaxValue="14999"/>
<Currency Label="TerminalsElecsnetRIBR" Alias="TerminalsElecsnet" Name="Терминал Элекснет" MaxValue="15000"/>
</Items>
</Group>
<Group Code="Mobile" Description="Сотовые операторы">
<Items>
<Currency Label="MixplatMTSRIBR" Alias="PhoneMTS" Name="МТС" MinValue="10" MaxValue="15000"/>
<Currency Label="MixplatTele2RIBR" Alias="PhoneTele2" Name="Tele2" MinValue="10" MaxValue="15000"/>
<Currency Label="MixplatBeelineRIBR" Alias="PhoneBeeline" Name="Билайн" MinValue="10" MaxValue="15000"/>
<Currency Label="MixplatMegafonRIBR" Alias="PhoneMegafon" Name="Мегафон" MinValue="10" MaxValue="15000"/>
<Currency Label="CypixTatTelecomRIB" Alias="PhoneTatTelecom" Name="Таттелеком" MinValue="10" MaxValue="15000"/>
</Items>
</Group>
<Group Code="Other" Description="Другие способы">
<Items>
<Currency Label="RapidaRIBEurosetR" Alias="StoreEuroset" Name="Евросеть" MaxValue="15000"/>
<Currency Label="RapidaRIBSvyaznoyR" Alias="StoreSvyaznoy" Name="Связной" MaxValue="15000"/>
</Items>
</Group>
*/