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
    const CREATED = 201;
    const ACCEPTED = 202;
    const NO_CONTENT = 204;

    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const NOT_MODIFIED = 304;
    const TEMPORARY_REDIRECT = 307;
    const PERMANENT_REDIRECT = 308;

    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const UNPROCESSABLE_ENTITY = 422;
    const TOO_MANY_REQUESTS = 429;

    const INTERNAL_SERVER_ERROR = 500;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;

    public static $titles = [
        self::OK => 'OK',
        self::CREATED => 'Created',
        self::ACCEPTED => 'Accepted',
        self::NO_CONTENT => 'No Content',

        self::MOVED_PERMANENTLY => 'Moved Permanently',
        self::FOUND => 'Found',
        self::NOT_MODIFIED => 'Not Modified',
        self::TEMPORARY_REDIRECT => 'Temporary Redirect',
        self::PERMANENT_REDIRECT => 'Permanent Redirect',

        self::BAD_REQUEST => 'Bad request',
        self::UNAUTHORIZED => 'Unauthorized',
        self::FORBIDDEN => 'Forbidden',
        self::NOT_FOUND => 'Not Found',
        self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
        self::REQUEST_TIMEOUT => 'Request Timeout',
        self::CONFLICT => 'Conflict',
        self::GONE => 'Gone',
        self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        self::TOO_MANY_REQUESTS => 'Too Many Requests',

        self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        self::BAD_GATEWAY => 'Bad Gateway',
        self::SERVICE_UNAVAILABLE => 'Service Unavailable',
        self::GATEWAY_TIMEOUT => 'Gateway Timeout',
    ];

    public static $names = [
        self::OK => 'OK',
        self::CREATED => 'CREATED',
        self::ACCEPTED => 'ACCEPTED',
        self::NO_CONTENT => 'NO_CONTENT',

        self::MOVED_PERMANENTLY => 'MOVED_PERMANENTLY',
        self::FOUND => 'FOUND',
        self::NOT_MODIFIED => 'NOT_MODIFIED',
        self::TEMPORARY_REDIRECT => 'TEMPORARY_REDIRECT',
        self::PERMANENT_REDIRECT => 'PERMANENT_REDIRECT',

        self::BAD_REQUEST => 'BAD_REQUEST',
        self::UNAUTHORIZED => 'UNAUTHORIZED',
        self::FORBIDDEN => 'FORBIDDEN',
        self::NOT_FOUND => 'NOT_FOUND',
        self::METHOD_NOT_ALLOWED => 'METHOD_NOT_ALLOWED',
        self::REQUEST_TIMEOUT => 'REQUEST_TIMEOUT',
        self::CONFLICT => 'CONFLICT',
        self::GONE => 'GONE',
        self::UNPROCESSABLE_ENTITY => 'UNPROCESSABLE_ENTITY',
        self::TOO_MANY_REQUESTS => 'TOO_MANY_REQUESTS',

        self::INTERNAL_SERVER_ERROR => 'INTERNAL_SERVER_ERROR',
        self::BAD_GATEWAY => 'BAD_GATEWAY',
        self::SERVICE_UNAVAILABLE => 'SERVICE_UNAVAILABLE',
        self::GATEWAY_TIMEOUT => 'GATEWAY_TIMEOUT',
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
