<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.12.2016
 * Time: 18:54
 */

namespace diCore\Payment\Mixplat;

use diCore\Payment\VendorContainer;

class MobileVendors extends VendorContainer
{
	const BEELINE = 1;
	const MEGAFON = 2;
	const MTS = 3;
	const TELE2 = 4;
    const YOTA = 5;

	public static $names = [
		self::BEELINE => 'beeline',
		self::MEGAFON => 'mf',
		self::MTS => 'mts',
		self::TELE2 => 'tele2',
        self::YOTA => 'yota',
	];

	public static $titles = [
		self::BEELINE => 'Билайн',
		self::MEGAFON => 'Мегафон',
		self::MTS => 'МТС',
		self::TELE2 => 'Теле2',
        self::YOTA => 'Йота',
	];

    public static $codes = [
        self::BEELINE => 'mobile_ru_beeline',
        self::MEGAFON => 'mobile_ru_mf',
        self::MTS => 'mobile_ru_mts',
        self::TELE2 => 'mobile_ru_tele2',
        self::YOTA => 'mobile_ru_yota',
    ];

    public static $codes2 = [
        self::YOTA => 'ru_yota',
    ];

    public static function id($name)
    {
        $id = parent::id($name);

        if ($id === null) {
            $id = array_search($name, static::$codes2, true) ?: null;
        }

        return $id;
    }
}