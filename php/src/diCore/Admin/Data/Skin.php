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
    const local = 10; // local, implemented in a single project

    public static $names = [
        self::classic => 'classic',
        self::entrine => 'entrine',
        self::local => 'local',
    ];

    public static $titles = [
        self::classic => 'Classic',
        self::entrine => 'Entrine',
        self::local => 'Local',
    ];
}
