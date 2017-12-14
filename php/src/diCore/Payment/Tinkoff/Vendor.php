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

	public static $titles = [
		self::BEELINE => 'Билайн',
		self::MEGAFON => 'Мегафон',
		self::MTS => 'МТС',
		self::TELE2 => 'Теле2',

		self::CARD => 'Банковская карта',
		self::WEBMONEY => 'Webmoney',
	];

	public static $names = [
		self::BEELINE => 'beeline',
		self::MEGAFON => 'mf',
		self::MTS => 'mts',
		self::TELE2 => 'tele2',

		self::CARD => 'card',
		self::WEBMONEY => 'webmoney',
	];

	public static $codes = [
		self::WEBMONEY => 'WMR',
	];

	public static $minLimits = [
		self::BEELINE => null,
		self::WEBMONEY => null,
	];
}