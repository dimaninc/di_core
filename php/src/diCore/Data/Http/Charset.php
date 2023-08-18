<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.05.2018
 * Time: 17:36
 */

namespace diCore\Data\Http;

use diCore\Tool\SimpleContainer;

class Charset extends SimpleContainer
{
    const UTF8 = 1;
    const CP1251 = 2;

    public static $names = [
        self::CP1251 => 'CP1251',
        self::UTF8 => 'UTF8',
    ];

    public static $titles = [
        self::CP1251 => 'windows-1251',
        self::UTF8 => 'utf-8',
    ];
}
