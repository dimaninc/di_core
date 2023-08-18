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
    const mainPort = null;
    const useModuleCache = null;
    const initiating = null;

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

    final public static function getInitiating()
    {
        /** @var Environment $class */
        $class = self::getClass();

        return $class::initiating;
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
