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
    const restApiInAdminSupported = false;
    const equalHyphenAndUnderscoreInApiPath = false;
    const addUrlBaseToPicFieldsInPublicData = false;
    const useUserSession = false;
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

    /**
     * Max dimensions in pixels which can be handled by GD, if bigger, use IMagick first
     * If not set, diImage::MAX_GD_WIDTH and diImage::MAX_GD_HEIGHT used
     */
    const maxGdWidth = null;
    const maxGdHeight = null;

    const initiating = false; // if true, then DB is auto-created and admin works w/o password

    const dbEncoding = 'utf8';
    const dbCollation = 'utf8_general_ci';

    protected static $location = \diLib::LOCATION_VENDOR_BEYOND;
    /**
     * If set, overrides $location in diLib::getAssetLocations
     * Can be useful, if $location == beyond, but virtual hosting blocks /vendor/ folder
     */
    const locationForAssets = null;
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

    /**
     * @return string|self
     */
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
        $class = self::getClass();

        if ($class == self::class) {
            $class::$location = \diLib::getLocation();
        }

        return $class::$location;
    }

    public static function getLocationForAssets()
    {
        $class = self::getClass();

        return $class::locationForAssets;
    }

    final public static function getSiteTitle()
    {
        $class = self::getClass();

        return $class::siteTitle;
    }

    final public static function getSiteLogo()
    {
        $class = self::getClass();

        return $class::siteLogo;
    }

    final public static function getSearchEngine()
    {
        $class = self::getClass();

        return $class::searchEngine;
    }

    final public static function getAdminSkin()
    {
        $class = self::getClass();

        return $class::adminSkin;
    }

    final public static function getCmsName()
    {
        $class = self::getClass();

        return $class::cmsName;
    }

    final public static function getCmsSupportEmail()
    {
        $class = self::getClass();

        return $class::cmsSupportEmail;
    }

    final public static function getMaxGdWidth()
    {
        $class = self::getClass();

        return Environment::getMaxGdWidth() ?? $class::maxGdWidth;
    }

    final public static function getMaxGdHeight()
    {
        $class = self::getClass();

        return Environment::getMaxGdHeight() ?? $class::maxGdHeight;
    }

    final public static function isInitiating()
    {
        $class = self::getClass();

        return Environment::getInitiating() ?? $class::initiating;
    }

    final public static function getApiQueryPrefix()
    {
        $class = self::getClass();

        return $class::apiQueryPrefix;
    }

    final public static function isRestApiSupported()
    {
        $class = self::getClass();

        return $class::restApiSupported;
    }

    final public static function isRestApiInAdminSupported()
    {
        $class = self::getClass();

        return $class::restApiInAdminSupported;
    }

    final public static function isEqualHyphenAndUnderscoreInApiPath()
    {
        $class = self::getClass();

        return $class::equalHyphenAndUnderscoreInApiPath;
    }

    final public static function shouldAddUrlBaseToPicFieldsInPublicData()
    {
        $class = self::getClass();

        return $class::addUrlBaseToPicFieldsInPublicData;
    }

    final public static function isUserSessionUsed()
    {
        $class = self::getClass();

        return $class::useUserSession;
    }

    final public static function getMainDomain()
    {
        $class = self::getClass();

        return Environment::getMainDomain() ?? $class::mainDomain;
    }

    final public static function getMainPort()
    {
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
        $class = self::getClass();

        return $class::mainLanguage;
    }

    final public static function getMainDatabase()
    {
        $class = self::getClass();

        return $class::mainDatabase;
    }

    final public static function useModuleCache()
    {
        $class = self::getClass();

        $val = Environment::getUseModuleCache();
        if ($val === null) {
            $val = $class::$useModuleCache;
        }

        return $val;
    }

    final public static function getDbEncoding()
    {
        $class = self::getClass();

        return Environment::getDbEncoding() ?? $class::dbEncoding;
    }

    final public static function getDbCollation()
    {
        $class = self::getClass();

        return Environment::getDbCollation() ?? $class::dbCollation;
    }

    final public static function getSourcesFolder()
    {
        $class = self::getClass();

        return $class::__getPhpFolder();
    }

    final public static function getConfigurationFolder()
    {
        $class = self::getClass();

        return $class::__getConfigurationFolder();
    }

    final public static function getDatabaseDumpFolder()
    {
        $class = self::getClass();

        return $class::__getDatabaseDumpFolder();
    }

    final public static function getDatabaseDumpPath()
    {
        return static::getDatabaseDumpFolder() .
            static::$databaseDumpPaths[static::getLocation()];
    }

    final public static function getFileDumpPath()
    {
        return static::getDatabaseDumpFolder() .
            static::$fileDumpPaths[static::getLocation()];
    }

    final public static function getOldTplFolder()
    {
        $class = self::getClass();

        return $class::__getOldTplFolder();
    }

    final public static function getTemplateFolder()
    {
        $class = self::getClass();

        return $class::__getTemplateFolder();
    }

    final public static function getCacheFolder()
    {
        $class = self::getClass();

        return $class::__getCacheFolder();
    }

    final public static function getLogFolder()
    {
        $class = self::getClass();

        return $class::__getLogFolder();
    }

    final public static function getAssetSourcesFolder()
    {
        $class = self::getClass();

        return $class::__getAssetSourcesFolder();
    }

    final public static function getUserAssetsFolder()
    {
        $class = self::getClass();

        return $class::__getUserAssetsFolder();
    }

    final public static function getTwigCorePath()
    {
        $class = self::getClass();

        return $class::__getTwigCorePath();
    }

    final public static function getPublicFolder()
    {
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

    public static function isMac()
    {
        if (stristr(php_uname(), 'Darwin')) {
            return true;
        }

        if (strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN') {
            return true;
        }

        return false;
    }
}
