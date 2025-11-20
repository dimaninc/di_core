<?php

namespace diCore\Admin\Data;

use diCore\Tool\SimpleContainer;

class LoginLayout extends SimpleContainer
{
    const classic = 1;
    const pic_on_right = 2;

    public static $names = [
        self::classic => 'classic',
        self::pic_on_right => 'pic_on_right',
    ];

    public static $titles = [
        self::classic => 'Classic',
        self::pic_on_right => 'Pic on right',
    ];
}
