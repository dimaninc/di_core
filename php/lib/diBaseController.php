<?php

use diCore\Helper\StringHelper;
use diCore\Helper\ArrayHelper;
use diCore\Base\CMS;
use diCore\Data\Config;

class diBaseController
{
	/** @var \FastTemplate */
	private $tpl;

	/** @var \diTwig */
	private $Twig;

	/** @var \diAdminUser */
	protected $admin;

	protected $action;
	protected $paramsAr;

	protected $twigCreateOptions = [];

	protected static $language = [
		'en' => [
		],
		'ru' => [
		],
	];

	public function __construct($params = [])
	{
		\diSession::start();

		$this->action = \diRequest::request('action');
		$this->paramsAr = $params;
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
		if ($this->admin === null)
		{
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
		if (!$this->isAdminAuthorized())
		{
			throw new \Exception('You have no access to this controller/action');
		}

		return $this;
	}

	protected function getDb()
	{
		return \diCore\Database\Connection::get()->getDb();
	}

	/**
	 * @deprecated
	 * @return \FastTemplate
	 * @throws \Exception
	 */
	protected function getTpl()
	{
		if ($this->tpl === null)
		{
			throw new \Exception('Template not initialized');
		}

		return $this->tpl;
	}

	/**
	 * @return \diTwig
	 */
	protected function getTwig()
	{
		if ($this->Twig === null)
		{
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
		return ArrayHelper::getValue($this->paramsAr, $idx, $defaultValue, $type);
	}

	/**
	 * creates an instance of defined class
	 */
	public static function create($params = [])
	{
		$c = new static($params);

		$result = $c->act();

		if ($result)
		{
			$c->defaultResponse($result);
		}

		return $c;
	}

	public static function createAttempt($pathBeginning = 'api', $die = true)
	{
		$pathBeginning = StringHelper::slash(StringHelper::slash($pathBeginning, true), false);
		$route = static::getFullQueryRoute();

		if (strpos($route, $pathBeginning) === 0)
		{
			try {
				static::autoCreate([
					'pathBeginning' => $pathBeginning,
				]);
			} catch (\Exception $e) {
				static::autoError($e);
			}

			if ($die)
			{
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

		if ($pathBeginning)
		{
			$paramsStr = rtrim(static::getFullQueryRoute(), '/');
		}
		else
		{
			$path = static::getCurrentFolder();

			$paramsStr = trim(substr(static::getFullQueryRoute(), strlen($path) + 1), '/');
		}

		$paramsStr = preg_replace('/[?#].*$/', '', $paramsStr);

		if ($pathBeginning && substr($paramsStr, 0, strlen($pathBeginning)) == $pathBeginning)
		{
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

	/*
		creates an instance of class from request
	*/
	public static function autoCreate($classBaseName = null, $action = null, $params = [])
	{
		if (is_array($classBaseName) && $classBaseName && $action === null && !$params)
		{
			$options = extend([
				'pathBeginning' => null,
			], $classBaseName);

			$classBaseName = null;

			$paramsAr = static::getQueryRouteAr($options['pathBeginning']);
		}
		else
		{
			$paramsAr = static::getQueryRouteAr();
		}

		if (!$classBaseName || !$action)
		{
			$classBaseName = isset($paramsAr[0]) ? $paramsAr[0] : '';
			$action = isset($paramsAr[1]) ? $paramsAr[1] : '';
			$params = array_slice($paramsAr, 2);

			if (!$classBaseName)
			{
				throw new \Exception('Empty controller name passed');
			}
		}

		$className = \diLib::getClassNameFor($classBaseName, \diLib::CONTROLLER);

		if (!\diLib::exists($className))
		{
			throw new \Exception("Controller class '$className' doesn't exist");
		}

		/** @var diBaseController $c */
		$c = new $className($params);

		$result = $c->act($action, $params);

		if ($result)
		{
			$c->defaultResponse($result);
		}

		return $c;
	}

	public static function autoError(\Exception $e)
	{
		print_json([
			'ok' => false,
			'message' => $e->getMessage(),
		]);
	}

	public function act($action = '', $paramsAr = [])
	{
		if (!$action)
	    {
	    	$action = $this->action;
	    }

		if (!$this->action)
		{
			$this->action = $action;
		}

		if ($action)
		{
			$methodName = '_' . camelize(strtolower(\diRequest::getMethodStr()) . '_' . $action . '_action');

			// first looking for REST API methods like _putSomeAction
			if (!method_exists($this, $methodName))
			{
				$methodName = camelize($action . '_action');
			}

			// then for basic method like someAction
			if (method_exists($this, $methodName))
			{
			    $this->setParamsAr($paramsAr);

				return $this->$methodName();
			}
		}

		throw new \Exception("There is not action method for '$action' in " . get_class($this));
	}

	protected function defaultResponse($data, $die = false)
	{
		static::makeResponse($data, $die);

		return $this;
	}

	public static function makeResponse($data, $die = false)
	{
		if (is_scalar($data))
		{
			echo $data;
		}
		else
		{
			print_json($data, !static::isCli());
		}

		if ($die)
		{
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
		$this->tpl
			->no_strict()
			->load_cache();

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

	public static function L($key, $lang = null)
	{
		if ($lang === null) {
			$lang = Config::getMainLanguage();
		}

		return isset(static::$language[$lang][$key])
			? static::$language[$lang][$key]
			: $key;
	}
}