<?php

namespace diCore\Tool\Auth;

use diCore\Data\Configuration;
use diCore\Tool\SimpleContainer;

/**
 * The site's primary sign-in method – configuration key `auth.primary_method`.
 * Sign-in by code (`Controller\Auth::_postPin*Action()`) is open unless it is
 * `password_only`.
 *
 * A missing key, an unknown or invalid value all mean DEFAULT_METHOD, which in the
 * core is `password_only`: a project must not get sign-in by code by surprise.
 * A project that ships the feature overrides DEFAULT_METHOD.
 */
class LoginMethod extends SimpleContainer
{
    const password_only = 1;
    const password_primary = 2;
    const code_primary = 3;

    const CONFIG_KEY = 'auth.primary_method';
    const DEFAULT_METHOD = self::password_only;

    public static $names = [
        self::password_only => 'password_only',
        self::password_primary => 'password_primary',
        self::code_primary => 'code_primary',
    ];

    public static $titles = [
        self::password_only => 'Только пароль',
        self::password_primary => 'Пароль, код запасной',
        self::code_primary => 'Код, пароль запасной',
    ];

    public static function current(): int
    {
        try {
            // get(), not safeGet(): the latter also returns the default for a stored empty value.
            $value = Configuration::get(static::CONFIG_KEY);
        } catch (\Exception $e) {
            // The key is absent from the project's configuration.
            return static::DEFAULT_METHOD;
        }

        return static::normalize($value);
    }

    public static function normalize($value): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return static::DEFAULT_METHOD;
        }

        $id = (int) $value;

        return isset(static::names()[$id]) ? $id : static::DEFAULT_METHOD;
    }

    public static function isCodeEnabled(): bool
    {
        return static::current() !== static::password_only;
    }

    public static function isCodePrimary(): bool
    {
        return static::current() === static::code_primary;
    }
}
