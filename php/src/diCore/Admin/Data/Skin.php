<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.08.2018
 * Time: 17:10
 */

namespace diCore\Admin\Data;

use diCore\Tool\SimpleContainer;

class Skin extends SimpleContainer
{
    const classic = 1;
    const entrine = 2;

    public static $names = [
        self::classic => 'classic',
        self::entrine => 'entrine',
    ];

    public static $titles = [
        self::classic => 'Classic',
        self::entrine => 'Entrine',
    ];
}