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
    const SUB_DOMAIN = 3; // ru.domain.com/page/address/

    public static $names = [
        self::URL => 'URL',
        self::DOMAIN => 'DOMAIN',
        self::SUB_DOMAIN => 'SUB_DOMAIN',
    ];

    public static $titles = [
        self::URL => 'Url',
        self::DOMAIN => 'Domain',
        self::SUB_DOMAIN => 'Sub-domain',
    ];
}