<?php

namespace diCore\Entity\Admin;

use diCore\Tool\SimpleContainer;

class Level extends SimpleContainer
{
    const root = 'root';

    public static $names = [
        self::root => 'root',
    ];

    public static $titles = [
        self::root => [
            'ru' => 'Главный админ',
            'en' => 'Root admin',
        ],
    ];
}
