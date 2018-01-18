<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.01.2018
 * Time: 0:01
 */

namespace diCore\Database;

use diCore\Tool\SimpleContainer;

class FieldType extends SimpleContainer
{
	const string = 1;

	const int = 11;
	const float = 12;
	const double = 13;

	const date = 21;
	const time = 22;
	const datetime = 23;
	const timestamp = 24;

	public static $names = [
		self::string => 'string',
		self::int => 'int',
		self::float => 'float',
		self::double => 'double',
		self::date => 'date',
		self::time => 'time',
		self::datetime => 'datetime',
		self::timestamp => 'timestamp',
	];

	public static $titles = [
		self::string => 'String',
		self::int => 'Int',
		self::float => 'Float',
		self::double => 'Double',
		self::date => 'Date',
		self::time => 'Time',
		self::datetime => 'Datetime',
		self::timestamp => 'Timestamp',
	];
}