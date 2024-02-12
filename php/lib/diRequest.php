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
        'get',
        'post',
        'cookie',
        'env',
        'server',
        'session',
    ];

    private static $postRawData = null;
    private static $postRawParsed = null;

    public static function convertFromCommandLine()
    {
        $queryString = join('&', array_slice($_SERVER['argv'], 1));

        parse_str($queryString, $outAr);

        return $outAr;
    }

    public static function createFromCommandLine($name = 'get')
    {
        $GLOBALS['_' . strtoupper($name)] = self::convertFromCommandLine();
    }

    public static function protocol()
    {
        return static::isHttps() ? 'https' : 'http';
    }

    public static function domain()
    {
        return static::server('HTTP_HOST') ?: Config::getMainDomain();
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

        return $x !== false ? substr($uri, 0, $x) : $uri;
    }

    public static function requestQueryString()
    {
        return static::server('QUERY_STRING');
    }

    public static function userAgent()
    {
        return static::server('HTTP_USER_AGENT');
    }

    public static function header($name)
    {
        $name = strtoupper($name);
        $name = str_replace('-', '_', $name);

        return self::server('HTTP_' . $name);
    }

    public static function allHeaders()
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $newName = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
                );
                $headers[$newName] = $value;
            }
        }

        return $headers;
    }

    public static function request($name, $defaultValue = null, $type = null)
    {
        return self::post(
            $name,
            self::get($name, self::rawPost($name, $defaultValue, $type), $type),
            $type
        );
    }

    public static function postExt($name, $defaultValue = null, $type = null)
    {
        return self::post($name, self::rawPost($name, $defaultValue, $type), $type);
    }

    public static function single($method, $name, $defaultValue = null, $type = null)
    {
        $scope = self::all($method);

        return ArrayHelper::get($scope, $name, $defaultValue, $type);
    }

    public static function all($method)
    {
        $varName = '_' . strtoupper($method);
        global $$varName;

        /*
		if ($method == 'session') {
			diSession::start();
		}

		if (!isset($$varName)) {
			throw new Exception("Undefined variable \${$varName}");
		}
		*/

        $scope = $$varName;

        return $scope;
    }

    public static function __callStatic($method, $arguments)
    {
        $mode = 'single';

        if (substr(underscore($method), 0, 4) == 'all_') {
            $mode = 'all';

            $method = substr(underscore($method), 4);
        }

        if (!in_array($method, self::$possibleMethodsAr)) {
            throw new Exception("Undefined method '$method' called");
        }

        switch ($mode) {
            case 'all':
                return self::all($method);

            case 'single':
                list($name, $defaultValue, $type) = array_merge($arguments, [
                    null,
                    null,
                    null,
                ]);
                return self::single($method, $name, $defaultValue, $type);

            default:
                throw new Exception("Undefined mode '$mode'");
        }
    }

    private static function processRawPost()
    {
        if (self::$postRawData === null) {
            self::$postRawData = file_get_contents('php://input');
        }

        if (self::$postRawParsed === null) {
            self::$postRawParsed = self::$postRawData
                ? (array) json_decode(self::$postRawData)
                : null;
        }
    }

    public static function rawPost($name = null, $defaultValue = null, $type = null)
    {
        self::processRawPost();

        return $name === null
            ? self::$postRawData
            : ArrayHelper::get(self::$postRawParsed, $name, $defaultValue, $type);
    }

    public static function rawPostParsed()
    {
        self::processRawPost();

        return self::$postRawParsed;
    }

    public static function isHttps()
    {
        return static::server('SERVER_PORT') == 443 ||
            static::server('HTTPS') === 'on' ||
            static::server('REQUEST_SCHEME') === 'https' ||
            static::server('SSL_PROTOCOL');
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

    public static function isOptions()
    {
        return self::getMethodStr() == 'OPTIONS';
    }

    public static function getMethodStr()
    {
        return self::server('REQUEST_METHOD');
    }

    public static function getRemoteIp()
    {
        $ip =
            self::server('HTTP_CLIENT_IP') ?:
            self::server('HTTP_X_FORWARDED_FOR') ?:
            self::server('REMOTE_ADDR');

        if ($x = strpos($ip ?? '', ',')) {
            $ip = substr($ip, 0, $x);
        }

        return $ip;
    }

    public static function enableCors(
        $whitelistDomains = [],
        callable $onOptionsRequest = null
    ) {
        $origin = self::server('HTTP_ORIGIN');

        if ($whitelistDomains && in_array($origin, $whitelistDomains)) {
            $domain = $origin;
        } else {
            $domain = $whitelistDomains ? null : '*';
        }

        if ($domain) {
            header('Access-Control-Allow-Origin: ' . $domain);
            header(
                'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, HEAD'
            );
            header(
                'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, Auth-Token'
            );
            header(
                'Access-Control-Expose-Headers: Content-Length, Date, X-Request-Id'
            );
        }

        if (self::isOptions()) {
            header('Allow: POST, GET, OPTIONS, PUT, DELETE, HEAD');

            if (is_callable($onOptionsRequest)) {
                $onOptionsRequest();
            }

            die();
        }
    }
}
