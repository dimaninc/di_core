<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.11.2017
 * Time: 17:03
 */

namespace diCore\Data;

/**
 * Class Environment
 * @package diCore\Data
 *
 * Child class file of this should be excluded from version control
 * It should be uploaded manually to the server
 * It is needed to set environment variables (dev/stage/prod/etc.)
 */
class Environment
{
	const mainDomain = null;
	const useModuleCache = null;

	private static $class;

	private static function getClass()
	{
		if (!self::$class)
		{
			self::$class = \diLib::getChildClass(self::class);
		}

		return self::$class;
	}

	final public static function resetClass()
	{
		self::$class = null;
	}

	final public static function getMainDomain()
	{
		/** @var Environment $class */
		$class = self::getClass();

		return $class::mainDomain;
	}

	final public static function getUseModuleCache()
	{
		/** @var Environment $class */
		$class = self::getClass();

		return $class::useModuleCache;
	}
}