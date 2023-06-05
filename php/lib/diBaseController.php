<?php

use diCore\Data\Http\HttpCode;
use diCore\Base\Exception\HttpException;
use diCore\Database\Connection;
use diCore\Helper\StringHelper;
use diCore\Helper\ArrayHelper;
use diCore\Base\CMS;
use diCore\Data\Config;
use diCore\Data\Http\Response;

class diBaseController
{
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

    protected static $language = [
        'en' => [],
        'ru' => [],
    ];

    public function __construct($params = [])
    {
        \diSession::start();

        $this->action = \diRequest::request('action');
        $this->paramsAr = $params;
    }

    protected static function isRestApiSupported()
    {
        return Config::isRestApiSupported();
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
                substr(static::getFullQueryRoute(), strlen($path) + 1),
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

        if (!$classBaseName || !$action) {
            $classBaseName = isset($paramsAr[0]) ? $paramsAr[0] : '';
            $action = isset($paramsAr[1]) ? $paramsAr[1] : '';
            $params = array_slice($paramsAr, 2);

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
        $c->act($action, $params);

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

        if (static::isRestApiSupported()) {
            HttpCode::header(HttpCode::INTERNAL_SERVER_ERROR);
        }

        StringHelper::printJson([
            'ok' => false,
            'message' => $e->getMessage(),
        ]);
    }

    public function act($action = '', $paramsAr = [])
    {
        if (!$action) {
            $action = $this->action;
        }

        if (!$this->action) {
            $this->action = $action;
        }

        if ($action) {
            $methodName =
                '_' .
                camelize(
                    strtolower(\diRequest::getMethodStr()) .
                        '_' .
                        $action .
                        '_action'
                );

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

        throw new \Exception(
            "There is not action method for '$action' in " . get_class($this)
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
            $data = $data->getReturnData();
        }

        if ($data instanceof HttpException) {
            if (static::isRestApiSupported()) {
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
        return $this->standardResponse(
            HttpCode::OK,
            extend(
                [
                    'ok' => true,
                ],
                $returnData
            )
        );
    }

    protected function notFound($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::NOT_FOUND,
            extend(
                [
                    'ok' => false,
                ],
                $returnData
            )
        );
    }

    protected function unauthorized($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::UNAUTHORIZED,
            extend(
                [
                    'ok' => false,
                ],
                $returnData
            )
        );
    }

    protected function forbidden($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::FORBIDDEN,
            extend(
                [
                    'ok' => false,
                ],
                $returnData
            )
        );
    }

    protected function badRequest($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::BAD_REQUEST,
            extend(
                [
                    'ok' => false,
                ],
                $returnData
            )
        );
    }

    protected function internalServerError($returnData = [])
    {
        return $this->standardResponse(
            HttpCode::INTERNAL_SERVER_ERROR,
            extend(
                [
                    'ok' => false,
                ],
                $returnData
            )
        );
    }

    public static function L($key, $lang = null)
    {
        if ($lang === null) {
            $lang = Config::getMainLanguage();
        }

        return static::$language[$lang][$key] ?? $key;
    }
}
