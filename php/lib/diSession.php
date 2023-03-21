<?php

use diCore\Traits\BasicCreate;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.01.16
 * Time: 10:40
 */

class diSession
{
    use BasicCreate;

	const SHORT_ID_LENGTH = 12;
	const VALUES_SESSION_KEY = 'diSession';
	const COOKIE_LIFE_TIME = SECS_PER_DAY * 30;

	protected static function beforeCreate()
    {
        session_set_cookie_params(static::COOKIE_LIFE_TIME);
    }

    protected static function afterCreate()
    {
    }

	public static function start()
	{
	    $class = static::getClass();

		if (!$class::exists()) {
            $class::beforeCreate();

			session_start();

            $class::afterCreate();
		}
	}

	public static function finish()
	{
        $class = static::getClass();

		if ($class::exists()) {
			session_write_close();
		}
	}

	public static function exists()
	{
		return !!static::id();
	}

	public static function id()
	{
		return session_id();
	}

	public static function shortId()
	{
		return substr(static::id(), 0, static::SHORT_ID_LENGTH);
	}

	public static function shortIntId()
	{
	    $stringId = substr(static::id(), 0, 8);

        $arr = str_split($stringId, 2);
        $dec = [];

        foreach ($arr as $grp) {
            $dec[] = str_pad(base_convert($grp, 34, 10), 4, '0', STR_PAD_LEFT);
        }

        return implode('', $dec);
	}

	public static function set($name, $value)
	{
		static::start();
        $class = static::getClass();

		if (!isset($_SESSION[$class::VALUES_SESSION_KEY])) {
			$_SESSION[$class::VALUES_SESSION_KEY] = [];
		}

		$_SESSION[$class::VALUES_SESSION_KEY][$name] = $value;
	}

	public static function get($name)
	{
		static::start();
        $class = static::getClass();

		return $_SESSION[$class::VALUES_SESSION_KEY][$name] ?? null;
	}

	public static function getAll()
	{
		static::start();
        $class = static::getClass();

		return $_SESSION[$class::VALUES_SESSION_KEY] ?? [];
	}

	public static function getAndKill($name)
	{
		$value = static::get($name);

		static::kill($name);

		return $value;
	}

	public static function kill($name)
	{
		static::start();
        $class = static::getClass();

		if (isset($_SESSION[$class::VALUES_SESSION_KEY])) {
			unset($_SESSION[$class::VALUES_SESSION_KEY][$name]);
		}
	}
}