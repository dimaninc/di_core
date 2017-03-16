<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.12.2016
 * Time: 18:54
 */

namespace diCore\Payment\Mixplat;


use diCore\Tool\SimpleContainer;

class MobileVendors extends SimpleContainer
{
	const BEELINE = 1;
	const MEGAFON = 2;
	const MTS = 3;
	const TELE2 = 4;

	public static $names = [
		self::BEELINE => 'beeline',
		self::MEGAFON => 'mf',
		self::MTS => 'mts',
		self::TELE2 => 'tele2',
	];

	public static $titles = [
		self::BEELINE => 'Билайн',
		self::MEGAFON => 'Мегафон',
		self::MTS => 'МТС',
		self::TELE2 => 'Теле2',
	];
}