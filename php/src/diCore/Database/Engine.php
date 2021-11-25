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

	public static $names = [
		self::MYSQL => 'mysql',
		self::MYSQL_OLD => 'mysql_old',
		self::SQLITE => 'sqlite',
		self::POSTGRESQL => 'postgresql',
		self::MONGO => 'mongo',
	];

	public static $titles = [
		self::MYSQL => 'MySQL',
		self::MYSQL_OLD => 'MySQL OLD',
		self::SQLITE => 'SQLite',
		self::POSTGRESQL => 'PostgreSQL',
		self::MONGO => 'MongoDB',
	];

	public static function isNoSql($engine)
    {
        return in_array($engine, [
            self::MONGO,
        ]);
    }

	public static function isRelational($engine)
    {
        return !static::isNoSql($engine);
    }
}