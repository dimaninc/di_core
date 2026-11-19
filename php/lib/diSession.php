<?php

use diCore\Traits\BasicCreate;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.01.16
 * Time: 10:40
 */

class diSession
{
    use BasicCreate;

    const SHORT_ID_LENGTH = 12;
    const VALUES_SESSION_KEY = 'diSession';
    const COOKIE_LIFE_TIME = SECS_PER_DAY * 30;
    const HEADER_NAME = 'X-Session';
    // shortIntId() kept across regenerate(): anonymous rows are keyed on it
    const INT_ID_SESSION_KEY = 'diSessionIntId';

    protected static function beforeCreate()
    {
        session_set_cookie_params(static::COOKIE_LIFE_TIME);
    }

    protected static function afterCreate()
    {
    }

    protected static function doCreate()
    {
        session_start();
    }

    public static function start()
    {
        $class = static::getClass();

        if (!$class::exists()) {
            $class::beforeCreate();

            $class::doCreate();

            $class::afterCreate();
        }
    }

    public static function finish()
    {
        $class = static::getClass();

        if ($class::exists()) {
            session_write_close();
        }
    }

    public static function exists()
    {
        return !!static::id();
    }

    public static function id()
    {
        return session_id();
    }

    public static function shortId()
    {
        return substr(static::id(), 0, static::SHORT_ID_LENGTH);
    }

    public static function shortIntId()
    {
        if (isset($_SESSION[static::INT_ID_SESSION_KEY])) {
            return $_SESSION[static::INT_ID_SESSION_KEY];
        }

        return static::shortIntIdOf(static::id());
    }

    protected static function shortIntIdOf(string $id): string
    {
        $stringId = substr($id, 0, 8);

        $arr = str_split($stringId, 2);
        $dec = [];

        foreach ($arr as $grp) {
            $dec[] = str_pad(base_convert($grp, 34, 10), 4, '0', STR_PAD_LEFT);
        }

        return implode('', $dec);
    }

    /**
     * A new id for the same session data, the old one dead: sign-in under an id
     * the client chose or someone planted must not leave that id signed in.
     * shortIntId() survives, so rows created before sign-in stay reachable.
     */
    public static function regenerate(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return false;
        }

        if (!isset($_SESSION[static::INT_ID_SESSION_KEY])) {
            $_SESSION[static::INT_ID_SESSION_KEY] = static::shortIntIdOf(
                static::id()
            );
        }

        return session_regenerate_id(true);
    }

    public static function set($name, $value)
    {
        static::start();
        $class = static::getClass();

        if (!isset($_SESSION[$class::VALUES_SESSION_KEY])) {
            $_SESSION[$class::VALUES_SESSION_KEY] = [];
        }

        $_SESSION[$class::VALUES_SESSION_KEY][$name] = $value;
    }

    public static function get($name)
    {
        static::start();
        $class = static::getClass();

        return $_SESSION[$class::VALUES_SESSION_KEY][$name] ?? null;
    }

    public static function getAll()
    {
        static::start();
        $class = static::getClass();

        return $_SESSION[$class::VALUES_SESSION_KEY] ?? [];
    }

    public static function getAndKill($name)
    {
        $value = static::get($name);

        static::kill($name);

        return $value;
    }

    public static function kill($name)
    {
        static::start();
        $class = static::getClass();

        if (isset($_SESSION[$class::VALUES_SESSION_KEY])) {
            unset($_SESSION[$class::VALUES_SESSION_KEY][$name]);
        }
    }
}
