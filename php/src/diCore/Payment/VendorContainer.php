<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.07.2017
 * Time: 12:16
 */

namespace diCore\Payment;

use diCore\Tool\SimpleContainer;

class VendorContainer extends SimpleContainer
{
	public static $codes = [];
	public static $minLimits = [];
	public static $maxLimits = [];

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

	public static function maxLimit($id)
	{
		return isset(static::$maxLimits[$id])
			? static::$maxLimits[$id]
			: null;
	}

	public static function maxLimitByName($name)
	{
		$id = static::id($name);

		return $id ? static::maxLimit($id) : null;
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