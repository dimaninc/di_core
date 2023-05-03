<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 20:02
 */

namespace diCore\Data;

use diCore\Admin\Data\Skin;
use diCore\Helper\StringHelper;

/**
 * Class Config
 * @package diCore\Data
 *
 * Child class will keep global config of website in its class
 */
class Config
{
	const siteTitle = null;
    const siteLogo = null;
	const apiQueryPrefix = '/api/';
	const restApiSupported = false;
	const folderForAssets = '';
	const folderForUserAssets = 'uploads/';
	const mainDomain = null;
	const mainPort = 80;
	const mainLanguage = 'ru'; // used to determine localization field names
    const mainDatabase = null; // 'main' by default
	const searchEngine = 'db';
    const adminSkin = Skin::classic;
    const cmsName = 'diCMS';
    const cmsSupportEmail = 'dicms.support@gmail.com';
	const initiating = false; // if true, then DB is auto-created and admin works w/o password

    const dbEncoding = 'utf8';
    const dbCollation = 'utf8_general_ci';

    protected static $location = \diLib::LOCATION_VENDOR_BEYOND;
	protected static $useModuleCache = false;

	private static $databaseDumpPaths = [
		\diLib::LOCATION_SUBMODULE_HTDOCS => '_admin/db/dump/',
		\diLib::LOCATION_VENDOR_BEYOND => 'db/dump/',
        \diLib::LOCATION_VENDOR_HTDOCS => '_admin/db/dump/',
	];

    private static $fileDumpPaths = [
        \diLib::LOCATION_SUBMODULE_HTDOCS => '_admin/db/files/',
        \diLib::LOCATION_VENDOR_BEYOND => 'db/files/',
        \diLib::LOCATION_VENDOR_HTDOCS => '_admin/db/files/',
    ];

    private static $class;

	protected static function getClass()
	{
		if (!self::$class) {
			self::$class = \diLib::getChildClass(self::class);
		}

		return self::$class;
	}

	final public static function resetClass()
	{
		self::$class = null;
	}

	final public static function getLocation()
	{
		/** @var Config $class */
		$class = self::getClass();

		if ($class == self::class) {
			$class::$location = \diLib::getLocation();
		}

		return $class::$location;
	}

	final public static function getSiteTitle()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::siteTitle;
	}

    final public static function getSiteLogo()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::siteLogo;
    }

	final public static function getSearchEngine()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::searchEngine;
	}

    final public static function getAdminSkin()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::adminSkin;
    }

    final public static function getCmsName()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::cmsName;
    }

    final public static function getCmsSupportEmail()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::cmsSupportEmail;
    }

	final public static function isInitiating()
	{
		/** @var Config $class */
		$class = self::getClass();

		return Environment::getInitiating() ?? $class::initiating;
	}

	final public static function getApiQueryPrefix()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::apiQueryPrefix;
	}

    final public static function isRestApiSupported()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::restApiSupported;
    }

	final public static function getMainDomain()
	{
		/** @var Config $class */
		$class = self::getClass();

		return Environment::getMainDomain() ?? $class::mainDomain;
	}

	final public static function getMainPort()
	{
		/** @var Config $class */
		$class = self::getClass();

		return Environment::getMainPort() ?? $class::mainPort;
	}

	final public static function getMainProtocol()
	{
		switch (static::getMainPort()) {
			case 443:
				return 'https://';

			default:
				return 'http://';
		}
	}

	final public static function getMainLanguage()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::mainLanguage;
	}

    final public static function getMainDatabase()
    {
        /** @var Config $class */
        $class = self::getClass();

        return $class::mainDatabase;
    }

	final public static function useModuleCache()
	{
		/** @var Config $class */
		$class = self::getClass();

		$val = Environment::getUseModuleCache();
		if ($val === null) {
			$val = $class::$useModuleCache;
		}

		return $val;
	}

    final public static function getDbEncoding()
    {
        /** @var Config $class */
        $class = self::getClass();

        return Environment::getDbEncoding() ?? $class::dbEncoding;
    }

    final public static function getDbCollation()
    {
        /** @var Config $class */
        $class = self::getClass();

        return Environment::getDbCollation() ?? $class::dbCollation;
    }

    final public static function getSourcesFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getPhpFolder();
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

    final public static function getFileDumpPath()
    {
        return static::getDatabaseDumpFolder() . static::$fileDumpPaths[static::getLocation()];
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

	final public static function getAssetSourcesFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getAssetSourcesFolder();
	}

	final public static function getUserAssetsFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getUserAssetsFolder();
	}

	final public static function getTwigCorePath()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getTwigCorePath();
	}

	final public static function getPublicFolder()
	{
		/** @var Config $class */
		$class = self::getClass();

		return $class::__getPublicFolder();
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

	public static function __getAssetSourcesFolder()
	{
		return static::__getPhpFolder() . static::folderForAssets;
	}

	public static function __getUserAssetsFolder()
	{
		return static::folderForUserAssets;
	}

	public static function __getTwigCorePath()
	{
		switch (static::getLocation()) {
			case \diLib::LOCATION_VENDOR_BEYOND:
            case \diLib::LOCATION_VENDOR_HTDOCS:
				return '../vendor/dimaninc/di_core/templates';

            default:
			case \diLib::LOCATION_SUBMODULE_HTDOCS:
				return '../_core/templates';
		}
	}

	public static function __getPhpFolder()
	{
		switch (static::getLocation()) {
			case \diLib::LOCATION_VENDOR_BEYOND:
				return StringHelper::slash(dirname(Paths::fileSystem()));

			default:
			case \diLib::LOCATION_SUBMODULE_HTDOCS:
            case \diLib::LOCATION_VENDOR_HTDOCS:
				return Paths::fileSystem();
		}
	}

	public static function __getPublicFolder()
	{
		return Paths::fileSystem();
	}
}
