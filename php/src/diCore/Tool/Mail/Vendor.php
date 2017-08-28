<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:10
 */

namespace diCore\Tool\Mail;


use diCore\Tool\SimpleContainer;

class Vendor extends SimpleContainer
{
	const google = 1;
	const yandex = 2;

	public static $titles = [
		self::google => 'Google',
		self::yandex => 'Yandex',
	];

	public static $names = [
		self::google => 'google',
		self::yandex => 'yandex',
	];

	protected static $smtpHosts = [
		self::google => 'smtp.gmail.com',
		self::yandex => 'smtp.yandex.ru',
	];

	protected static $smtpPorts = [
		self::google => [
			true => 587,
			false => 25,
		],
		self::yandex => [
			true => 25,
			false => 25,
		],
	];

	public static function smtpHost($id)
	{
		return isset(static::$smtpHosts[$id])
			? static::$smtpHosts[$id]
			: null;
	}

	public static function smtpPort($id, $secure)
	{
		return isset(static::$smtpPorts[$id][$secure])
			? static::$smtpPorts[$id][$secure]
			: null;
	}
}