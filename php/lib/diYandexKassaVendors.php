<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.02.16
 * Time: 18:19
 */
class diYandexKassaVendors extends diSimpleContainer
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

	const MIXPLAT = 50;
	const PAYPAL = 51;
	const SMS_ONLINE = 52;

	public static $titles = [
		self::YANDEX_MONEY => "Яндекс.Деньги",
		self::WEBMONEY => "Webmoney",
		self::CARD => "Банковская карта",
		self::MOBILE => "С баланса мобильного телефона",
		self::QIWI_WALLET => "Qiwi",
		self::SBERBANK_ONLINE => "Сбербанк-онлайн",
		self::ALPHA_CLICK => "Альфа-клик",
		self::TERMINAL => "Терминалы/кассы",
		self::MPOS => "Моб.терминал MPos",
		self::MASTER_PASS => "MasterPass",
		self::PROM_SVYAZ_BANK => "ПромСвязьБанк",
		self::TINKOFF => "КупиВКредит (Тинькофф Банк)",
		self::QPPI => "Куппи.ру",

		self::MIXPLAT => "Mixplat",
		self::PAYPAL => "PayPal",
		self::SMS_ONLINE => "SMS-online",
	];

	public static $names = [
		self::YANDEX_MONEY => "yandex-money",
		self::WEBMONEY => "webmoney",
		self::CARD => "card",
		self::MOBILE => "mobile",
		self::QIWI_WALLET => "qiwi",
		self::SBERBANK_ONLINE => "sberbank-online",
		self::ALPHA_CLICK => "alpha-click",
		self::TERMINAL => "terminal",
		self::MPOS => "mpos",
		self::MASTER_PASS => "master-pass",
		self::PROM_SVYAZ_BANK => "prom-svyaz-bank",
		self::TINKOFF => "tinkoff",
		self::QPPI => "qppi",

		self::MIXPLAT => "mixplat",
		self::PAYPAL => "paypal",
		self::SMS_ONLINE => "sms_online",
	];

	public static $codes = [
		self::YANDEX_MONEY => "PC",
		self::WEBMONEY => "WM",
		self::CARD => "AC",
		self::MOBILE => "MC",
		self::QIWI_WALLET => "QW",
		self::SBERBANK_ONLINE => "SB",
		self::ALPHA_CLICK => "AB",
		self::TERMINAL => "GP",
		self::MPOS => "MP",
		self::MASTER_PASS => "MA",
		self::PROM_SVYAZ_BANK => "PB",
		self::TINKOFF => "KV",
		self::QPPI => "QP",

		self::MIXPLAT => 'MIXPLAT',
		self::PAYPAL => 'PAYPAL',
		self::SMS_ONLINE => 'SMS_ONLINE',
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

		self::MIXPLAT => null,
		self::PAYPAL => null,
		self::SMS_ONLINE => null,
	];

	public static function code($id)
	{
		return isset(static::$codes[$id])
			? static::$codes[$id]
			: null;
	}

	public static function codeByName($name)
	{
		$id = static::id($name);

		return $id ? static::code($id) : null;
	}

	public static function minLimit($id)
	{
		return isset(static::$minLimits[$id])
			? static::$minLimits[$id]
			: null;
	}

	public static function minLimitByName($name)
	{
		$id = static::id($name);

		return $id ? static::minLimit($id) : null;
	}

	public static function id($name)
	{
		$id = parent::id($name);

		if ($id === null)
		{
			$id = array_search($name, static::$codes, true) ?: null;
		}

		return $id;
	}
}