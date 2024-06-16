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
    const unset = -1;

    const string = 1;
    const bool = 2;
    const bool_int = 3; // boolean in model, integer in database
    const ip_string = 4;
    const json = 5;

    const int = 11;
    const float = 12;
    const double = 13;
    const ip_int = 14;

    const date = 21;
    const time = 22;
    const datetime = 23;
    const timestamp = 24;

    const mongo_id = 32;

    public static $names = [
        self::unset => 'unset',
        self::string => 'string',
        self::bool => 'bool',
        self::bool_int => 'bool_int',
        self::ip_string => 'ip_string',
        self::json => 'json',
        self::int => 'int',
        self::float => 'float',
        self::double => 'double',
        self::ip_int => 'ip_int',
        self::date => 'date',
        self::time => 'time',
        self::datetime => 'datetime',
        self::timestamp => 'timestamp',
        self::mongo_id => 'mongo_id',
    ];

    public static $titles = [
        self::unset => 'Unset',
        self::string => 'String',
        self::bool => 'Bool',
        self::bool_int => 'Bool (integer)',
        self::ip_string => 'String IP-address',
        self::json => 'JSON',
        self::int => 'Int',
        self::float => 'Float',
        self::double => 'Double',
        self::ip_int => 'Integer IP-address',
        self::date => 'Date',
        self::time => 'Time',
        self::datetime => 'Datetime',
        self::timestamp => 'Timestamp',
        self::mongo_id => 'Mongo id',
    ];

    public static function type($id, Connection $connection)
    {
        switch ($id) {
            case self::bool:
            case self::bool_int:
                if ($connection::getEngine() !== Engine::POSTGRESQL) {
                    return 'tinyint';
                }
                break;

            case self::ip_string:
                if ($connection::getEngine() === Engine::POSTGRESQL) {
                    return 'cidr';
                }
                return 'varchar(32)';

            case self::ip_int:
                return 'bigint';
        }

        return static::name($id);
    }

    public static function integerTypes()
    {
        return [self::int, self::ip_int, self::bool_int];
    }

    public static function floatTypes()
    {
        return [self::float, self::double];
    }

    public static function numberTypes()
    {
        return array_merge(self::integerTypes(), self::floatTypes());
    }

    public static function stringTypes()
    {
        return [self::string, self::ip_string];
    }

    public static function isInteger($type)
    {
        return in_array($type, self::integerTypes());
    }

    public static function isFloat($type)
    {
        return in_array($type, self::floatTypes());
    }

    public static function isNumber($type)
    {
        return in_array($type, self::numberTypes());
    }
}
