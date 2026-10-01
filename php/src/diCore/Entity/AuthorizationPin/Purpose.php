<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Tool\SimpleContainer;

/**
 * What a code confirms. `authentication` is reserved by the core (sign-in by code,
 * `Controller\Auth::_postPinLoginAction()`). A project extends this class, keeps 1
 * as is and declares its own purposes with values > 1, repeating `authentication`
 * in its `$names`/`$titles` (or adding its own through `$customNames`/`$customTitles`).
 */
class Purpose extends SimpleContainer
{
    const authentication = 1;

    public static $names = [
        self::authentication => 'authentication',
    ];

    public static $titles = [
        self::authentication => 'Вход по коду',
    ];
}
