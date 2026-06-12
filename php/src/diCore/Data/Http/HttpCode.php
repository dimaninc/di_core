<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.08.2017
 * Time: 21:25
 */

namespace diCore\Data\Http;

use diCore\Tool\SimpleContainer;

class HttpCode extends SimpleContainer
{
    const OK = 200;

    const MOVED_PERMANENTLY = 301;

    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const CONFLICT = 409;
    const GONE = 410;
    const TOO_MANY_REQUESTS = 429;

    const INTERNAL_SERVER_ERROR = 500;
    const SERVICE_UNAVAILABLE = 503;

    public static $titles = [
        self::OK => 'OK',

        self::MOVED_PERMANENTLY => 'Moved Permanently',

        self::BAD_REQUEST => 'Bad request',
        self::UNAUTHORIZED => 'Unauthorized',
        self::FORBIDDEN => 'Forbidden',
        self::NOT_FOUND => 'Not Found',
        self::CONFLICT => 'Conflict',
        self::GONE => 'Gone',
        self::TOO_MANY_REQUESTS => 'Too Many Requests',

        self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        self::SERVICE_UNAVAILABLE => 'Service Unavailable',
    ];

    public static $names = [
        self::OK => 'OK',

        self::MOVED_PERMANENTLY => 'MOVED_PERMANENTLY',

        self::BAD_REQUEST => 'BAD_REQUEST',
        self::UNAUTHORIZED => 'UNAUTHORIZED',
        self::FORBIDDEN => 'FORBIDDEN',
        self::NOT_FOUND => 'NOT_FOUND',
        self::CONFLICT => 'CONFLICT',
        self::GONE => 'GONE',
        self::TOO_MANY_REQUESTS => 'TOO_MANY_REQUESTS',

        self::INTERNAL_SERVER_ERROR => 'INTERNAL_SERVER_ERROR',
        self::SERVICE_UNAVAILABLE => 'SERVICE_UNAVAILABLE',
    ];

    public static function headerContent($code)
    {
        return sprintf('HTTP/1.1 %d %s', $code, self::title($code));
    }

    public static function header($code)
    {
        header(self::headerContent($code));
    }
}
