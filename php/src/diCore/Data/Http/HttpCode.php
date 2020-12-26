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

	const INTERNAL_SERVER_ERROR = 500;

	public static $titles = [
		self::OK => 'OK',

		self::MOVED_PERMANENTLY => 'Moved Permanently',

        self::BAD_REQUEST => 'Bad request',
		self::UNAUTHORIZED => 'Unauthorized',
		self::FORBIDDEN => 'Forbidden',
		self::NOT_FOUND => 'Not Found',

		self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
	];

	public static $names = [
		self::OK => 'ok',

		self::MOVED_PERMANENTLY => 'moved_permanently',

        self::BAD_REQUEST => 'bad_request',
		self::UNAUTHORIZED => 'unauthorized',
		self::FORBIDDEN => 'forbidden',
		self::NOT_FOUND => 'not_found',

		self::INTERNAL_SERVER_ERROR => 'internal_server_error',
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