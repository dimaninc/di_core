<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.03.2016
 * Time: 15:12
 */

use diCore\Base\CMS;
use diCore\Tool\Logger;

class diCookie
{
    const DEBUG = false;

    protected static bool $noneOverHttpLogged = false;

    protected static function defaultSetOptions()
    {
        return [
            'asOptions' => false,
            'expire' => null,
            'secure' => null,
            'httpOnly' => null,
            'sameSite' => false,
        ];
    }

    /**
     * `true` was cast to bool and went out as `SameSite=1`, an invalid value
     * browsers ignore; `true` (and 1) now means Lax. A string is matched ignoring case
     * (the attribute is case-insensitive) and sent in its canonical form; an
     * unknown value omits the attribute and is reported, never sent as is.
     */
    public static function sameSiteValue($sameSite): string
    {
        // 1 / '1' went out exactly like true before (`SameSite=1`)
        if (in_array($sameSite, [true, 1, '1'], true)) {
            return 'Lax';
        }

        // 0 / '0' meant "no attribute" before (cast to false), so they still do
        if (in_array($sameSite, [false, null, '', 0, '0'], true)) {
            return '';
        }

        foreach (['Lax', 'Strict', 'None'] as $canonical) {
            if (is_string($sameSite) && strcasecmp($sameSite, $canonical) === 0) {
                return $canonical;
            }
        }

        static::onInvalidSameSite($sameSite);

        return '';
    }

    /**
     * Options for setcookie(). `SameSite=None` without `Secure` makes Chrome
     * drop the whole cookie, so on https (X-Forwarded-Proto counts) None forces
     * Secure. Over plain http a Secure cookie is not stored at all (localhost
     * aside), so there None is omitted instead (logged): the cookie is kept,
     * with the browser's default.
     */
    public static function setcookieOptions(array $options): array
    {
        $sameSite = static::sameSiteValue($options['sameSite'] ?? false);
        $secure = !empty($options['secure']);

        if ($sameSite === 'None' && !$secure) {
            if (static::isHttpsRequest()) {
                $secure = true;
            } else {
                // once per process (an FPM request, or a whole long-lived worker): a dev
                // domain on http sets such cookies often
                if (!static::$noneOverHttpLogged) {
                    static::$noneOverHttpLogged = true;
                    static::log('SameSite=None omitted: it needs Secure, and the request is not https');
                }

                $sameSite = '';
            }
        }

        return [
            'expires' => (int) ($options['expire'] ?? 0),
            'path' => ($options['path'] ?? '') ?: '',
            'domain' => ($options['domain'] ?? '') ?: '',
            'secure' => $secure,
            'httponly' => !empty($options['httpOnly']),
            'samesite' => $sameSite,
        ];
    }

    /**
     * isHttps() plus X-Forwarded-Proto: behind a TLS-terminating proxy the
     * request reaches PHP over http, and None would be dropped for good. A
     * forged header costs nothing here: the browser then just doesn't store
     * the Secure cookie for whoever forged it.
     */
    protected static function isHttpsRequest(): bool
    {
        // a proxy chain sends a list ("https, http"): the first is the client's
        $proto = explode(',', (string) \diRequest::header('X-Forwarded-Proto'))[0];

        return \diRequest::isHttps() || strtolower(trim($proto)) === 'https';
    }

    /** The one place a cookie leaves; setcookie() can't be read back in CLI. */
    protected static function sendCookie($name, $value, array $options): void
    {
        setcookie($name, $value, $options);
    }

    protected static function onInvalidSameSite($sameSite): void
    {
        static::log('Unknown SameSite value omitted: ' . var_export($sameSite, true));
    }

    /**
     * Sets the cookie
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $optionsOrDate Options array or expire date
     * @param string|null $path
     * @param string|null $domain
     */
    public static function set(
        $name,
        $value = null,
        $optionsOrDate = null,
        $path = null,
        $domain = null
    ) {
        if (is_array($optionsOrDate)) {
            $options = $optionsOrDate;
        } else {
            $options = [
                'expire' => $optionsOrDate,
                'path' => $path,
                'domain' => $domain,
            ];
        }

        $options = extend(
            static::defaultSetOptions(),
            [
                'path' => $path,
                'domain' => $domain,
            ],
            $options
        );

        if ($options['expire'] !== null) {
            $options['expire'] = \diDateTime::timestamp($options['expire']);
        }

        if ($options['domain'] === true) {
            $options['domain'] = static::getDomainForAll();
        }

        // always the options form: the positional setcookie() has no place for
        // sameSite and dropped it silently. asOptions is kept for old callers
        static::sendCookie($name, $value, static::setcookieOptions($options));

        if (static::DEBUG) {
            if ($options['expire']) {
                $options['expire'] = \diDateTime::format(
                    'd.m.Y H:i:s',
                    $options['expire']
                );
            }

            static::log(
                "Cookie set: '$name' = '$value', " . var_export($options, true)
            );
        }
    }

    /**
     * Reads the cookie
     *
     * @param string $name
     * @return mixed
     */
    public static function get($name)
    {
        if (static::DEBUG) {
            static::log("Cookie get: '$name'");
        }

        return \diRequest::cookie($name);
    }

    /**
     * Removes the cookie
     *
     * @param $name
     * @param null $path
     * @param null $domain
     * @param array $options
     */
    public static function remove($name, $path = null, $domain = null, $options = [])
    {
        if (static::DEBUG) {
            static::log("Cookie remove: '$name'");
        }

        static::set($name, '', $options, $path, $domain);
    }

    /**
     * @return string
     */
    public static function getDomainForAll()
    {
        $host = \diRequest::domain();

        if (CMS::isDev()) {
            return $host;
        }

        return substr($host, 0, 4) == 'www.' ? substr($host, 3) : '.' . $host;
    }

    protected static function log($message)
    {
        Logger::getInstance()->log($message, '', '-cookie');
    }
}
