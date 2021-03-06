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
	const microsoft = 3;
	const own = 50;

	public static $titles = [
		self::google => 'Google',
		self::yandex => 'Yandex',
		self::microsoft => 'Microsoft',
		self::own => 'Own SMTP',
	];

	public static $names = [
		self::google => 'google',
		self::yandex => 'yandex',
		self::microsoft => 'microsoft',
		self::own => 'own',
	];

	protected static $smtpHosts = [
		self::google => 'smtp.gmail.com',
		self::yandex => 'smtp.yandex.ru',
		self::microsoft => 'smtp.office365.com',
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
		self::microsoft => [
			true => 587,
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