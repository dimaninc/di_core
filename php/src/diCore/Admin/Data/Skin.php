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
    const v2024 = 3;
    const local = 10; // local, implemented in a single project

    public static $names = [
        self::classic => 'classic',
        self::entrine => 'entrine',
        self::v2024 => 'v2024',
        self::local => 'local',
    ];

    public static $titles = [
        self::classic => 'Classic',
        self::entrine => 'Entrine',
        self::v2024 => 'v2024',
        self::local => 'Local',
    ];
}
