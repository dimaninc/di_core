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
	const SQLITE = 2;
	// engines below not implemented yet
	const POSTGRESQL = 3;
	const ORACLE = 4;
	const MONGO = 5;

	public static $names = [
		self::MYSQL => 'mysql',
		self::SQLITE => 'sqlite',
	];

	public static $titles = [
		self::MYSQL => 'MySQL',
		self::SQLITE => 'SQLite',
	];
}