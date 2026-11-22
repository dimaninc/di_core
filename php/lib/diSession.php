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
        return static::shortIntIdOf(static::id());
    }

    /**
     * Derived from the id, so it changes with regenerate(): rows keyed on it
     * are moved in Tool\Auth::onSessionIdChanged() from shortIntIdOf($oldId).
     * Never kept across the rotation – whoever planted the old id computes it.
     */
    public static function shortIntIdOf(string $id): string
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
     * Whether the client brought an id (session cookie or X-Session header):
     * only such an id can be planted. An id PHP generated for this request
     * needs no rotation – a token client without cookies would otherwise
     * rotate on every request. A project passing the id some other way
     * overrides this, or its sign-ins are never rotated.
     */
    public static function idFromClient(): bool
    {
        return isset($_COOKIE[session_name()]) ||
            trim((string) \diRequest::header(static::HEADER_NAME)) !== '';
    }

    /**
     * A new id for the same session data, the old one destroyed: sign-in under
     * an id the client chose or someone planted must not leave that id signed in.
     */
    public static function regenerate(): bool
    {
        // no PHP session in use: nothing to rotate, and nothing to report
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        // the new id can't reach the client: the old one stays signed in
        if (headers_sent($file, $line)) {
            \diCore\Tool\Logger::getInstance()->log(
                "Session id NOT rotated: headers already sent at $file:$line",
                'diSession'
            );

            return false;
        }

        if (!session_regenerate_id(true)) {
            // e.g. the save handler failed to destroy the old record: PHP has
            // then closed the session, so the sign-in stored next is not saved
            \diCore\Tool\Logger::getInstance()->log(
                'Session id NOT rotated: session_regenerate_id() failed, session closed',
                'diSession'
            );

            return false;
        }

        return true;
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
