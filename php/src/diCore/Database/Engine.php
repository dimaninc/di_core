<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.05.2017
 * Time: 15:05
 */

namespace diCore\Database;

use diCore\Tool\SimpleContainer;

class Engine extends SimpleContainer
{
    const MYSQL = 1;
    const MYSQL_OLD = 4;
    const SQLITE = 2;
    const POSTGRESQL = 3;
    const MONGO = 5;
    const REDIS = 6;

    public static $names = [
        self::MYSQL => 'mysql',
        self::MYSQL_OLD => 'mysql_old',
        self::SQLITE => 'sqlite',
        self::POSTGRESQL => 'postgresql',
        self::MONGO => 'mongo',
        self::REDIS => 'redis',
    ];

    public static $titles = [
        self::MYSQL => 'MySQL',
        self::MYSQL_OLD => 'MySQL OLD',
        self::SQLITE => 'SQLite',
        self::POSTGRESQL => 'PostgreSQL',
        self::MONGO => 'MongoDB',
        self::REDIS => 'Redis',
    ];

    public static function isNoSql($engine)
    {
        return in_array($engine, [self::MONGO]);
    }

    public static function isKeyValue($engine)
    {
        return in_array($engine, [self::REDIS]);
    }

    public static function isMySql($engine)
    {
        return in_array($engine, [self::MYSQL, self::MYSQL_OLD]);
    }

    public static function isRelational($engine)
    {
        return static::isMySql($engine) ||
            in_array($engine, [self::SQLITE, self::POSTGRESQL]);
    }
}
