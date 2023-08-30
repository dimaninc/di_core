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
    const ip_string = 4;

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
        self::ip_string => 'ip_string',
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
        self::ip_string => 'String IP-address',
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
                if ($connection::getEngine() === Engine::MYSQL) {
                    return 'tinyint';
                }
                break;
        }

        return static::name($id);
    }
}
