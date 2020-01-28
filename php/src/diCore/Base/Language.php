<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.01.2020
 * Time: 11:33
 */

namespace diCore\Base;

use diCore\Tool\SimpleContainer;

class Language extends SimpleContainer
{
    const URL = 1; // domain.com/en/page/address/
    const DOMAIN = 2; // domain.ru/page/address/

    public static $names = [
        self::URL => 'URL',
        self::DOMAIN => 'DOMAIN',
    ];

    public static $titles = [
        self::URL => 'Url',
        self::DOMAIN => 'Domain',
    ];
}