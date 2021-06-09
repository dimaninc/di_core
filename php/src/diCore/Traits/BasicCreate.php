<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 16:26
 */

namespace diCore\Traits;

trait BasicCreate
{
	private static $class;

	/**
	 * @return $this
	 */
	public static function basicCreate(...$args)
	{
		$class = \diLib::getChildClass(static::class);

		return new $class(...$args);
	}

	public static function getClass()
	{
		if (!self::$class) {
			self::$class = \diLib::getChildClass(static::class);
		}

		return self::$class;
	}

	final public static function resetClass()
	{
		self::$class = null;
	}
}