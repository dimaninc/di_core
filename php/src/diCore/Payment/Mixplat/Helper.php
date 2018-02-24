<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.02.2018
 * Time: 15:42
 */

namespace diCore\Payment\Mixplat;

class Helper
{
    const serviceId = null;
    const secretKey = null;
    const testMode = false;
    const logFolder = 'log/mixplat/';

    private static $class;
    
    /**
     * @return Helper|string
     */
    private static function getClass()
    {
        if (!self::$class)
        {
            self::$class = \diLib::getChildClass(self::class, 'Settings');
        }

        return self::$class;
    }

    final public static function resetClass()
    {
        self::$class = null;
    }

    public static function logFolder()
    {
        return \diPaths::fileSystem() . static::logFolder;
    }

    /**
     * @return Mixplat
     */
    public static function create()
    {
        $class = self::getClass();

        return new Mixplat($class::serviceId, $class::secretKey, $class::testMode, $class::logFolder());
    }
}