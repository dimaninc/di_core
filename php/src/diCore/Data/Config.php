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

    // Deliberately still mb3: raising the default here would put an mb4
    // connection over the mb3 schema of every existing consumer on a mere
    // `composer update`, mixing collations on joins. NEW projects should set
    // utf8mb4 in their own Data\Config (the shipped dumps are utf8mb4), old ones
    // convert first — see the charset note in README.
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

    /**
     * `DEFAULT CHARSET = … COLLATE = …` for a generated CREATE TABLE.
     *
     * A blank charset falls back to utf8mb3, but a blank COLLATE is simply
     * omitted rather than guessed at as "<charset>_general_ci" — that name need
     * not exist for every charset, and inventing one only swaps a clear error
     * for an obscure one. Omitting it is not silent, though: see
     * warnAboutBlankCollation().
     */
    final public static function getDbCharsetClause(): string
    {
        return self::configuredCharsetClause('DEFAULT CHARSET = ', ' COLLATE = ');
    }

    /**
     * `CHARACTER SET … COLLATE …` for a single column of a generated CREATE
     * TABLE. Same rules as getDbCharsetClause().
     */
    final public static function getDbColumnCharsetClause(): string
    {
        return self::configuredCharsetClause('CHARACTER SET ', ' COLLATE ');
    }

    /** Pure, so the blank-collation case is testable without swapping Config. */
    public static function buildCharsetClause(
        string $prefix,
        string $collateKeyword,
        string $charset,
        string $collation
    ): string {
        return $prefix .
            "'" .
            ($charset ?: 'utf8') .
            "'" .
            ($collation ? $collateKeyword . "'$collation'" : '');
    }

    private static function configuredCharsetClause(
        string $prefix,
        string $collateKeyword
    ): string {
        $charset = static::getDbEncoding() ?: 'utf8';
        $collation = static::getDbCollation();

        if (!$collation) {
            self::warnAboutBlankCollation($charset);
        }

        return self::buildCharsetClause(
            $prefix,
            $collateKeyword,
            $charset,
            (string) $collation
        );
    }

    /**
     * A charset with no collation is not an error the DDL can express, but it is
     * a misconfiguration: MySQL 8 then hands the table its own default
     * (utf8mb4_0900_ai_ci), which collides with every table that named one on a
     * join. Failing here would be worse — this runs while the migrations log
     * table is being created, before any migration can fix anything — so it is
     * logged instead, once per process.
     */
    private static function warnAboutBlankCollation(string $charset): void
    {
        static $warned = false;

        if ($warned) {
            return;
        }

        $warned = true;

        try {
            \diCore\Tool\Logger::getInstance()->log(
                "dbCollation is empty while dbEncoding is '$charset':" .
                    ' generated tables will take the SERVER default collation.' .
                    ' Set dbCollation in Data\Config.',
                'database'
            );
        } catch (\Throwable $e) {
            // a warning must never be the reason a table is not created
        }
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
