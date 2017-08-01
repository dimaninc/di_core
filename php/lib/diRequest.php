<?php
/*
	// dimaninc

	// 2015/05/08
		* birthday

*/

use diCore\Data\Config;
use diCore\Helper\ArrayHelper;

/**
 * @method static mixed get($name, $defaultValue = null, $type = null)
 * @method static mixed post($name, $defaultValue = null, $type = null)
 * @method static mixed cookie($name, $defaultValue = null, $type = null)
 * @method static mixed env($name, $defaultValue = null, $type = null)
 * @method static mixed server($name, $defaultValue = null, $type = null)
 * @method static mixed session($name, $defaultValue = null, $type = null)
 */
class diRequest
{
	public static $possibleMethodsAr = [
		"get",
		"post",
		"cookie",
		"env",
		"server",
		"session",
	];

	private static $postRawData = null;
	private static $postRawParsed = null;

	public static function convertFromCommandLine()
	{
		$queryString = join("&", array_slice($_SERVER["argv"], 1));

		parse_str($queryString, $outAr);

		return $outAr;
	}

	public static function createFromCommandLine($name = "get")
	{
		$GLOBALS["_" . strtoupper($name)] = self::convertFromCommandLine();
	}

	public static function protocol()
	{
		return static::server("SERVER_PORT") == 443 ? "https" : "http";
	}

	public static function domain()
	{
		return static::server("HTTP_HOST") ?: Config::getMainDomain();
	}

	public static function urlBase($slash = false)
	{
		return static::protocol() . '://' . static::domain() . ($slash ? '/' : '');
	}

	public static function referrer($default = '')
	{
		return static::server('HTTP_REFERER') ?: $default;
	}

	public static function requestUri()
	{
		return static::server('REQUEST_URI');
	}

	public static function requestPath()
	{
		$uri = static::requestUri();
		$x = strpos($uri, '?');

		return $x !== false
			? substr($uri, 0, $x)
			: $uri;
	}

	public static function requestQueryString()
	{
		return static::server('QUERY_STRING');
	}

	public static function request($name, $defaultValue = null, $type = null)
	{
		return self::post($name, self::get($name, $defaultValue, $type), $type);
	}

    public static function single($method, $name, $defaultValue = null, $type = null)
    {
		$scope = self::all($method);

	    return ArrayHelper::getValue($scope, $name, $defaultValue, $type);
    }

	public static function all($method)
	{
		$varName = "_" . strtoupper($method);
		global $$varName;

		/*
		if ($method == "session")
		{
			diSession::start();
		}

		if (!isset($$varName))
		{
			throw new Exception("Undefined variable \${$varName}");
		}
		*/

		$scope = $$varName;

		return $scope;
	}

	public static function __callStatic($method, $arguments)
	{
	    $mode = "single";

		if (substr(underscore($method), 0, 4) == "all_")
		{
			$mode = "all";

			$method = substr(underscore($method), 4);
		}

		if (!in_array($method, self::$possibleMethodsAr))
	    {
	    	throw new Exception("Undefined method '$method' called");
	    }

		switch ($mode)
		{
			case "all":
				return self::all($method);

			case "single":
				list($name, $defaultValue, $type) = array_merge($arguments, array(null, null, null));
				return self::single($method, $name, $defaultValue, $type);

			default:
				throw new Exception("Undefined mode '$mode'");
		}
	}

	public static function rawPost($name = null, $defaultValue = null, $type = null)
	{
		if (self::$postRawData === null)
		{
			self::$postRawData = file_get_contents('php://input');
		}

		if (self::$postRawParsed === null)
		{
			self::$postRawParsed = (array)json_decode(self::$postRawData);
		}

		return $name === null
			? self::$postRawData
			: ArrayHelper::getValue(self::$postRawParsed, $name, $defaultValue, $type);
	}

	public static function isHttps()
	{
		return
			(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| self::server('SERVER_PORT') == 443;
	}

	public static function isCli()
	{
		return php_sapi_name() == 'cli';
	}

	public static function isGet()
	{
		return self::getMethodStr() == 'GET';
	}

	public static function isPost()
	{
		return self::getMethodStr() == 'POST';
	}

	public static function isPut()
	{
		return self::getMethodStr() == 'PUT';
	}

	public static function isDelete()
	{
		return self::getMethodStr() == 'DELETE';
	}

	public static function getMethodStr()
	{
		return self::server('REQUEST_METHOD');
	}
}