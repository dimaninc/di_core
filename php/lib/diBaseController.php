<?php

use diCore\Data\Environment;
use diCore\Data\Http\HttpCode;
use diCore\Base\Exception\HttpException;
use diCore\Database\Connection;
use diCore\Helper\StringHelper;
use diCore\Helper\ArrayHelper;
use diCore\Base\CMS;
use diCore\Data\Config;
use diCore\Data\Http\Response;
use diCore\Tool\Logger;

class diBaseController
{
    /**
     * Turn this to true for tiny rest controller without action name
     * methods should be named like _postAction, _putAction, etc.
     * e.g. /api/name/[params]
     */
    const TINY_ACTIONS = false;

    /**
     * Is REST API supported for the controller
     */
    const REST_API_SUPPORTED = false;

    const RESULT_KEY = 'ok';
    const MESSAGE_KEY = 'message';

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var \FastTemplate
     * @deprecated
     */
    private $tpl;

    /** @var \diTwig */
    private $Twig;

    /** @var \diAdminUser */
    protected $admin;

    protected $action;
    protected $paramsAr;

    protected $twigCreateOptions = [];

    protected static $baseLanguage = [
        'en' => [
            'auth.sign_in_first' => 'Sign in first',
        ],
        'ru' => [
            'auth.sign_in_first' => 'Авторизуйтесь для работы',
        ],
    ];
    protected static $language = [
        'en' => [],
        'ru' => [],
    ];
    protected static $customLanguage = [
        'en' => [],
        'ru' => [],
    ];

    public function __construct($params = [])
    {
        if (Environment::shouldLogSpeed()) {
            Logger::getInstance()->speed('constructor', static::class);
        }

        \diSession::start();

        $this->action = \diRequest::request('action');
        $this->paramsAr = $params;
    }

    protected static function isRestApiSupported()
    {
        return Config::isRestApiSupported() || static::REST_API_SUPPORTED;
    }

    protected static function isHttpCodeErrorResponseSupported()
    {
        return true;
    }

    protected static function isEqualHyphenAndUnderscoreInApiPath()
    {
        return Config::isEqualHyphenAndUnderscoreInApiPath();
    }

    protected static function logErrorsToFile()
    {
        return true;
    }

    /**
     * @return \diAdminUser
     */
    protected function getAdmin()
    {
        return $this->admin;
    }

    protected function getAdminModel()
    {
        return $this->getAdmin()->getModel();
    }

    protected function initAdmin()
    {
        if ($this->admin === null) {
            $this->admin = \diAdminUser::create();
        }

        return $this;
    }

    protected function isAdminAuthorized()
    {
        return ($this->admin && $this->admin->authorized()) || $this->isCli();
    }

    protected function adminRightsHardCheck()
    {
        if (!$this->isAdminAuthorized()) {
            throw new \Exception('You have no access to this controller/action');
        }

        return $this;
    }

    protected function getDb()
    {
        return Connection::get(Config::getMainDatabase())->getDb();
    }

    /**
     * @deprecated
     * @return \FastTemplate
     * @throws \Exception
     */
    protected function getTpl()
    {
        if ($this->tpl === null) {
            throw new \Exception('Template not initialized');
        }

        return $this->tpl;
    }

    /**
     * @return \diTwig
     */
    protected function getTwig()
    {
        if ($this->Twig === null) {
            $this->setupTwig();
        }

        return $this->Twig;
    }

    protected function setupTwig()
    {
        $this->Twig = \diTwig::create($this->twigCreateOptions);

        $this->getTwig()->assign([
            'asset_locations' => \diLib::getAssetLocations(),
            'url_base' => \diRequest::urlBase(),
        ]);

        return $this;
    }

    public function setParamsAr($ar)
    {
        $this->paramsAr = $ar;
    }

    public function param($idx, $defaultValue = null, $type = null)
    {
        return ArrayHelper::get($this->paramsAr, $idx, $defaultValue, $type);
    }

    /**
     * creates an instance of defined class
     */
    public static function create($params = [])
    {
        $c = new static($params);
        $c->act();

        if ($c->getResponse()->hasReturnData()) {
            $c->defaultResponse();
        }

        return $c;
    }

    public static function createAttempt($pathBeginning = 'api', $die = true)
    {
        $pathBeginning = StringHelper::slash(
            StringHelper::slash($pathBeginning, true),
            false
        );
        $route = static::getFullQueryRoute();

        if (strpos($route, $pathBeginning) === 0) {
            try {
                static::autoCreate([
                    'pathBeginning' => $pathBeginning,
                ]);
            } catch (\Exception $e) {
                static::autoError($e);
            }

            if ($die) {
                if (Environment::shouldLogSpeed()) {
                    Logger::getInstance()->speedFinish(
                        'createAttempt/die',
                        static::class
                    );
                }

                die();
            }

            return true;
        }

        return false;
    }

    public static function isCli()
    {
        return \diRequest::isCli();
    }

    protected static function getCurrentFolder()
    {
        return dirname($_SERVER['SCRIPT_NAME']);
    }

    protected static function getFullQueryRoute()
    {
        return \diRequest::requestUri();
    }

    protected static function getQueryRouteAr($pathBeginning = null)
    {
        //$pathBeginning = $pathBeginning ?: Config::getApiQueryPrefix();

        if ($pathBeginning) {
            $paramsStr = rtrim(static::getFullQueryRoute(), '/');
        } else {
            $path = static::getCurrentFolder();

            $paramsStr = trim(
                substr(static::getFullQueryRoute() ?: '', strlen($path) + 1),
                '/'
            );
        }

        $paramsStr = preg_replace('/[?#].*$/', '', $paramsStr);

        if (
            $pathBeginning &&
            substr($paramsStr, 0, strlen($pathBeginning)) == $pathBeginning
        ) {
            $paramsStr = substr($paramsStr, strlen($pathBeginning));
        }

        $paramsStr = trim($paramsStr, '/');

        /*
		if ($subFolder = \diLib::getSubFolder()) {
			if (StringHelper::startsWith($paramsStr, $subFolder)) {
				$paramsStr = substr($paramsStr, mb_strlen($subFolder));
				$paramsStr = trim($paramsStr, '/');
			}
		}
		*/

        return explode('/', $paramsStr);
    }

    public static function getActionName()
    {
        $pathBeginning = StringHelper::slash(
            StringHelper::slash(Config::getApiQueryPrefix(), true),
            false
        );
        $paramsAr = static::getQueryRouteAr($pathBeginning);

        return $paramsAr[1] ?? null;
    }

    /*
		creates an instance of class from request
	*/
    public static function autoCreate(
        $classBaseName = null,
        $action = null,
        $params = [],
        $silent = false
    ) {
        if (
            is_array($classBaseName) &&
            $classBaseName &&
            $action === null &&
            !$params
        ) {
            $options = extend(
                [
                    'pathBeginning' => null,
                ],
                $classBaseName
            );

            $classBaseName = null;

            $paramsAr = static::getQueryRouteAr($options['pathBeginning']);
        } else {
            $paramsAr = static::getQueryRouteAr();
        }

        $updateParams = false;

        if (!$classBaseName || !$action) {
            $classBaseName = $paramsAr[0] ?? '';
            $action = $paramsAr[1] ?? '';
            $updateParams = true;

            if (!$classBaseName) {
                throw new \Exception('Empty controller name passed');
            }
        }

        $className = \diLib::getClassNameFor($classBaseName, \diLib::CONTROLLER);

        if (!\diLib::exists($className)) {
            throw new \Exception("Controller class '$className' doesn't exist");
        }

        /** @var diBaseController $c */
        $c = new $className($params);
        if ($updateParams) {
            $params = array_slice($paramsAr, $c::TINY_ACTIONS ? 1 : 2);
        }

        if (Environment::shouldLogSpeed()) {
            Logger::getInstance()->speed(
                "Action=$action",
                'BaseController/autoCreate'
            );
        }

        $c->act($action, $params);

        if (Environment::shouldLogSpeed()) {
            Logger::getInstance()->speedFinish(
                'afterAct',
                'BaseController/autoCreate'
            );
        }

        if (!$silent && $c->getResponse()->hasReturnData()) {
            $c->defaultResponse();
        }

        return $c;
    }

    public static function autoError(\Exception $e)
    {
        if ($e instanceof HttpException) {
            static::makeResponse($e, true);
        }

        if (static::isCli()) {
            $info = json_encode(\diRequest::convertFromCommandLine());
        } else {
            $path = \diRequest::requestUri();
            $method = \diRequest::getMethodStr();
            $info = "$method $path";
        }

        $message = "Error in API ($info): {$e->getMessage()}";
        static::logMessage($message);

        if (static::isHttpCodeErrorResponseSupported() && !static::isCli()) {
            HttpCode::header(HttpCode::INTERNAL_SERVER_ERROR);
        }

        StringHelper::printJson(
            [
                static::RESULT_KEY => false,
                static::MESSAGE_KEY => $e->getMessage(),
            ],
            !static::isCli()
        );
    }

    protected static function logMessage($message)
    {
        if (!static::logErrorsToFile()) {
            return;
        }

        Logger::getInstance()->log($message);
    }

    public function act($action = '', $paramsAr = [])
    {
        if (!static::TINY_ACTIONS) {
            if (!$action) {
                $action = $this->action;
            }

            if (!$this->action) {
                $this->action = $action;
            }
        }

        if ($action || static::TINY_ACTIONS) {
            if (static::isEqualHyphenAndUnderscoreInApiPath()) {
                $action = str_replace('-', '_', $action);
            }

            $actionPart = static::TINY_ACTIONS ? '' : '_' . $action;
            $requestMethod = self::isCli()
                ? 'cli'
                : strtolower(\diRequest::getMethodStr());
            $source = $requestMethod . $actionPart . '_action';
            $methodName = '_' . camelize($source);

            // first looking for REST API methods like _putSomeAction
            if (!method_exists($this, $methodName)) {
                $methodName = camelize($action . '_action');
            }

            // then for basic method like someAction
            if (method_exists($this, $methodName)) {
                $this->setParamsAr($paramsAr);

                $this->getResponse()->setReturnData($this->$methodName());

                return;
            }
        }

        throw new HttpException(
            HttpCode::NOT_FOUND,
            "There is no action method for '$action' in " . get_class($this)
        );
    }

    protected function defaultResponse($data = null, $die = false)
    {
        static::makeResponse($data ?? $this->getResponse(), $die);

        return $this;
    }

    public static function makeResponse($data, $die = false)
    {
        if ($data instanceof Response) {
            if (static::isRestApiSupported()) {
                HttpCode::header($data->getResponseCode());
            }

            $data->headers();

            $data = $data->getReturnData();
        }

        if ($data instanceof HttpException) {
            if (static::isHttpCodeErrorResponseSupported()) {
                HttpCode::header($data->getCode());
            }

            $data = $data->getBody();
        }

        if (is_scalar($data)) {
            echo $data;
        } else {
            StringHelper::printJson($data, !static::isCli());

            if (static::isCli()) {
                echo "\n";
            }
        }

        if ($die) {
            die();
        }
    }

    protected function getRawPostData()
    {
        return \diRequest::rawPost();
    }

    protected function getIncomingXml()
    {
        return simplexml_load_string($this->getRawPostData());
    }

    protected function initAdminTpl()
    {
        $this->tpl = \diCore\Admin\Base::getAdminTpl();

        $this->setupTpl();

        return $this;
    }

    protected function initWebTpl()
    {
        $this->tpl = new \FastTemplate(
            Config::getOldTplFolder() . CMS::TPL_DIR,
            Config::getCacheFolder() . CMS::TPL_CACHE_PHP
        );
        $this->tpl->no_strict()->load_cache();

        $this->setupTpl();

        return $this;
    }

    protected function setupTpl()
    {
        $this->getTpl()
            ->setupBasicAssignees()
            ->assign(\diLib::getAssetLocations(), 'ASSET_LOCATIONS.');

        return $this;
    }

    protected function redirectTo($url)
    {
        header("Location: $url");

        return $this;
    }

    protected function redirect()
    {
        $back = \diRequest::get('back', \diRequest::referrer('/'));

        $this->redirectTo($back);

        return $this;
    }

    public function getResponse()
    {
        if (!$this->response) {
            $this->response = new Response();

            $sessionId = \diSession::id();
            if ($sessionId) {
                $this->response->addHeader(\diSession::HEADER_NAME, $sessionId);
            }
        }

        return $this->response;
    }

    protected function standardResponse($statusCode, $returnData = [])
    {
        if (static::isRestApiSupported()) {
            $this->getResponse()->setResponseCode($statusCode);
        }

        return $returnData;
    }

    protected function ok($returnData = '')
    {
        return $this->standardResponse(HttpCode::OK, $returnData);
    }

    protected function okay($returnData = [])
    {
        return $this->standardResponse(HttpCode::OK, static::e(true, $returnData));
    }

    protected function notFound($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::NOT_FOUND,
            static::e(false, $returnData)
        );
    }

    protected function unauthorized($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::UNAUTHORIZED,
            static::e(false, $returnData)
        );
    }

    protected function forbidden($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::FORBIDDEN,
            static::e(false, $returnData)
        );
    }

    protected function badRequest($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::BAD_REQUEST,
            static::e(false, $returnData)
        );
    }

    protected function internalServerError($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::INTERNAL_SERVER_ERROR,
            static::e(false, $returnData)
        );
    }

    protected static function e(...$args)
    {
        if (count($args)) {
            foreach ($args as &$x) {
                if (is_string($x)) {
                    $x = [
                        static::MESSAGE_KEY => $x,
                    ];
                } elseif (is_bool($x)) {
                    $x = [
                        static::RESULT_KEY => $x,
                    ];
                }
            }
        }

        return extend(...$args);
    }

    protected static function localLanguageStrings($lang)
    {
        return [];
    }

    public static function allLanguageStrings($lang)
    {
        return extend(
            static::$baseLanguage[$lang],
            static::$language[$lang],
            static::$customLanguage[$lang],
            static::localLanguageStrings($lang)
        );
    }

    public static function L($key, $lang = null)
    {
        if ($lang === null) {
            $lang = Config::getMainLanguage();
        }

        return self::allLanguageStrings($lang)[$key] ?? $key;
    }
}
