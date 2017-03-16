<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.01.16
 * Time: 10:40
 */

class diSession
{
	const SHORT_ID_LENGTH = 12;
	const VALUES_SESSION_KEY = "diSession";

	public static function start()
	{
		if (!self::exists())
		{
			session_set_cookie_params(SECS_PER_DAY * 30);

			session_start();
		}
	}

	public static function finish()
	{
		if (self::exists())
		{
			session_write_close();
		}
	}

	public static function exists()
	{
		return !!self::id();
	}

	public static function id()
	{
		return session_id();
	}

	public static function shortId()
	{
		return substr(self::id(), 0, self::SHORT_ID_LENGTH);
	}

	public static function shortIntId()
	{
		return diStringHelper::hexToDec(self::shortId());
	}

	public static function set($name, $value)
	{
		self::start();

		if (!isset($_SESSION[self::VALUES_SESSION_KEY]))
		{
			$_SESSION[self::VALUES_SESSION_KEY] = array();
		}

		$_SESSION[self::VALUES_SESSION_KEY][$name] = $value;
	}

	public static function get($name)
	{
		self::start();

		return isset($_SESSION[self::VALUES_SESSION_KEY][$name])
			? $_SESSION[self::VALUES_SESSION_KEY][$name]
			: null;
	}

	public static function getAll()
	{
		self::start();

		return isset($_SESSION[self::VALUES_SESSION_KEY])
			? $_SESSION[self::VALUES_SESSION_KEY]
			: [];
	}

	public static function getAndKill($name)
	{
		$value = self::get($name);

		self::kill($name);

		return $value;
	}

	public static function kill($name)
	{
		self::start();

		if (isset($_SESSION[self::VALUES_SESSION_KEY]))
		{
			unset($_SESSION[self::VALUES_SESSION_KEY][$name]);
		}
	}
}