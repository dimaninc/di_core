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
    // if admin and main website hosted on different domain
    const websiteDomain = null;
    const mainPort = null;
    const useModuleCache = null;
    const initiating = null;

    /**
     * Max dimensions in pixels which can be handled by GD, if bigger, use IMagick first
     * If not set, Config::maxGdArea used, then diImage::MAX_GD_WIDTH and diImage::MAX_GD_HEIGHT used
     */
    const maxGdWidth = null;
    const maxGdHeight = null;

    /**
     * true/'all' - log all api, admin page and cms module timings
     * false/null - log nothing
     * 'slow' - log only timings of slow processes
     */
    const logSpeed = null;
    /**
     * Slow speed value in seconds
     */
    const slowSpeedValue = 1;

    const dbEncoding = null;
    const dbCollation = null;

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

    final public static function getMainDomain()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::mainDomain;
    }

    final public static function getWebsiteDomain()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::websiteDomain;
    }

    final public static function getMainPort()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::mainPort;
    }

    final public static function getUseModuleCache()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::useModuleCache;
    }

    final public static function getMaxGdWidth()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::maxGdWidth;
    }

    final public static function getMaxGdHeight()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::maxGdHeight;
    }

    final public static function getInitiating()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::initiating;
    }

    final public static function shouldLogSpeed()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return !!$class::logSpeed;
    }

    final public static function shouldLogOnlySlowSpeed()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::logSpeed === 'slow';
    }

    final public static function getSlowSpeedValue()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::slowSpeedValue;
    }

    final public static function getDbEncoding()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::dbEncoding;
    }

    final public static function getDbCollation()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::dbCollation;
    }
}
