<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.12.2017
 * Time: 11:33
 */

namespace diCore\Entity\Ad;

use diCore\Tool\SimpleContainer;

class ShowOnHolidays extends SimpleContainer
{
    const always = 0;
    const only = 1;
    const except = 2;

    public static $titles = [
        self::always => 'Показывать всегда',
        self::only => 'Показывать только по праздникам',
        self::except => 'Показывать в дни кроме праздников',
    ];

    public static $names = [
        self::always => 'always',
        self::only => 'only',
        self::except => 'except',
    ];
}
