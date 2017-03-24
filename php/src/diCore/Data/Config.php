<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 20:02
 */

namespace diCore\Data;

use diCore\Helper\StringHelper;

class Config
{
	const apiQueryPrefix = null;

	protected static $location = \diLib::LOCATION_HTDOCS;

	private static $databaseDumpPaths = [
		\diLib::LOCATION_HTDOCS => '_admin/db/dump/',
		\diLib::LOCATION_BEYOND => 'db/dump/',
	];

	private static $class;

	private static function getClass()
	{
		if (!self::$class)
		{
			self::$class = \diLib::getChildClass(self::class);
		}

		return self::$class;
	}

	final public static function getLocation()
	{
		/** @var Config $class */
		$class = self::getClass();

		if ($class == self::class)
		{
			$class::$location = \diLib::getLocation();
		}

		return $class::$location;
	}

	final public static function getApiQueryPrefix()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::apiQueryPrefix;
	}

	final public static function getConfigurationFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getConfigurationFolder();
	}

	final public static function getDatabaseDumpFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getDatabaseDumpFolder();
	}

	final public static function getDatabaseDumpPath()
	{
		return static::getDatabaseDumpFolder() . static::$databaseDumpPaths[static::getLocation()];
	}

	final public static function getOldTplFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getOldTplFolder();
	}

	final public static function getTemplateFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getTemplateFolder();
	}

	final public static function getCacheFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getCacheFolder();
	}

	final public static function getLogFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getLogFolder();
	}

	final public static function getTwigCorePath()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getTwigCorePath();
	}

	public static function __getConfigurationFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getDatabaseDumpFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getOldTplFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getTemplateFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getCacheFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getLogFolder()
	{
		return static::__getPhpFolder();
	}

	public static function __getTwigCorePath()
	{
		switch (static::getLocation())
		{
			case \diLib::LOCATION_BEYOND:
				return '../vendor/dimaninc/di_core/templates';

			default:
			case \diLib::LOCATION_HTDOCS:
				return '../_core/templates';
		}
	}

	public static function __getPhpFolder()
	{
		switch (static::getLocation())
		{
			case \diLib::LOCATION_BEYOND:
				return StringHelper::slash(dirname(Paths::fileSystem()));

			default:
			case \diLib::LOCATION_HTDOCS:
				return Paths::fileSystem();
		}
	}

	public static function __getPublicFolder()
	{
		return Paths::fileSystem();
	}
}