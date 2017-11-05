<?php
/*
	// dimaninc

	// 2016/06/16
		* Namespaces support added

	// 2016/03/29
		* PHP 5.4+ now required
        * traits usage added

	// 2015/04/20
		* birthday

*/

class diLib
{
	const AUTOLOAD = true;

	const LOCATION_HTDOCS = 0;
	const LOCATION_BEYOND = 1;

	private static $location = null;
	private static $locationMarkers = [
		self::LOCATION_HTDOCS => ['_core', 'php', 'lib'],
		self::LOCATION_BEYOND => ['vendor', 'dimaninc', 'di_core', 'php', 'lib'],
	];

	const SIMPLE_CLASS = 0;
	const MODULE = 1;
	const MODEL = 2;
	const COLLECTION = 3;
	const ADMIN_PAGE = 4;
	const CONTROLLER = 5;

	public static $kindNames = [
		self::SIMPLE_CLASS => '',
		self::MODULE => 'module',
		self::MODEL => 'model',
		self::COLLECTION => 'collection',
		self::ADMIN_PAGE => 'page',
		self::CONTROLLER => 'controller',
	];

    const pathCoreLib = 1;
	const pathCoreModels = 2;
	const pathCoreControllers = 3;
	const pathCoreExceptions = 4;
	const pathCoreModules = 5;
	const pathCoreCollections = 6;
	const pathCoreVendor = 7;
	const pathCoreTests = 8;
	const pathCoreSources = 9;

	const pathCoreAdmin = 11;
	const pathCoreAdminControllers = 12;
	const pathCoreAdminPages = 13;
	const pathCoreAdminWorkers = 14;

    const pathProjectAdminLib = 21;
	const pathProjectAdminPages = 22;
	const pathProjectAdminControllers = 23;

	const pathProjectLib = 31;
	const pathProjectModels = 32;
	const pathProjectControllers = 33;
	const pathProjectExceptions = 34;
	const pathProjectModules = 35;
	const pathProjectCollections = 36;
	const pathProjectVendor = 37;
	const pathProjectTests = 38;
	const pathProjectSources = 39;

	const pathOldLib = 99;

	/**
	 * @var array
	 */
	public static $coreNamespaces = ['diCore'];

	/**
	 * @var array
	 */
	private static $projectNamespaces = [];

	public static $libPathsAr = [
		// first, project libs
		self::pathProjectAdminLib => "/_admin/_inc/lib/",
		self::pathProjectAdminPages => "/_admin/_inc/lib/pages/",
		self::pathProjectAdminControllers => "/_admin/_inc/lib/controllers/",

		self::pathProjectLib => "/_cfg/lib/",
		self::pathProjectModels => "/_cfg/models/",
		self::pathProjectControllers => "/_cfg/controllers/",
		self::pathProjectExceptions => "/_cfg/exceptions/",
		self::pathProjectModules => "/_cfg/modules/",
		self::pathProjectCollections => "/_cfg/collections/",
		self::pathProjectVendor => "/_cfg/vendor/",
		self::pathProjectTests => "/_cfg/tests/",
		self::pathProjectSources => "/src/",

		// then, core libs
		self::pathCoreLib => "/_core/php/lib/",
		self::pathCoreModels => "/_core/php/models/",
		self::pathCoreControllers => "/_core/php/controllers/",
		self::pathCoreExceptions => "/_core/php/exceptions/",
		self::pathCoreModules => "/_core/php/modules/",
		self::pathCoreCollections => "/_core/php/collections/",
		self::pathCoreVendor => "/_core/php/vendor/",
		self::pathCoreTests => "/_core/php/tests/",
		self::pathCoreSources => "/_core/php/src/",

		self::pathCoreAdmin => "/_core/php/admin/",
		self::pathCoreAdminControllers => "/_core/php/admin/controllers/",
		self::pathCoreAdminPages => "/_core/php/admin/pages/",
		self::pathCoreAdminWorkers => "/_core/php/admin/workers/",

		// finally, old style project libs
		self::pathOldLib => "/_cfg/classes/",
	];

	public static $libSubFolders = [
		self::pathProjectLib => [
			"OAuth2",
			"traits",
		],

		self::pathCoreLib => [
			"OAuth2",
			"traits",
		],
	];

	const workerPrefix = "/_core/php/workers/";
	const workerAdminPrefix = "/_core/php/admin/workers/";

	/** @deprecated */
	static public $classesAr = [];

	/** @deprecated */
	static public $classPropertiesAr = [];

	public static function registerNamespace($namespaces)
	{
		if (!is_array($namespaces))
		{
			$namespaces = [$namespaces];
		}

		foreach ($namespaces as $namespace)
		{
			self::$projectNamespaces[] = $namespace;
		}

		\diCore\Data\Config::resetClass();
	}

	public static function getAllNamespaces()
	{
		return array_merge(self::$projectNamespaces, self::$coreNamespaces);
	}

	public static function getFirstNamespace()
	{
		$ns = static::getAllNamespaces();

		return isset($ns[0]) ? $ns[0] : null;
	}

	public static function checkCompatibility()
	{
		if (version_compare(PHP_VERSION, '5.5.0', '<'))
		{
			die('diCMS requires PHP 5.5.0 or higher. Current version is ' . PHP_VERSION);
		}
	}

	static public function getWorkerBasePath($controller = null, $action = null, $paramsAr = null, $options = [])
	{
		$options = extend([
			'underscoreParams' => true,
		], $options);
		$suffixAr = [];

		if (!is_null($controller))
		{
			if (is_array($controller) && is_null($action) && is_null($paramsAr))
			{
				$suffixAr = $controller;
			}
			else
			{
				$suffixAr[] = $controller;

				if ($action)
				{
					$suffixAr[] = $action;
				}

				if ($paramsAr)
				{
					if (!is_array($paramsAr))
					{
						$paramsAr = [$paramsAr];
					}

					$suffixAr = array_merge($suffixAr, $paramsAr);
				}
			}
		}

		if ($suffixAr)
		{
			if ($options['underscoreParams'])
			{
				$suffixAr = array_map("underscore", $suffixAr);
			}

			$suffixAr[] = "";
		}

		return join("/", $suffixAr);
	}

	public static function getClassNameInNamespace($basicName, $ns, $kind)
	{
		switch ($kind)
		{
			case self::SIMPLE_CLASS:
				return $ns . '\\' . $basicName;

			case self::MODULE:
				return $ns . '\\' . 'Module' . '\\' . $basicName;

			case self::MODEL:
				return $ns . '\\' . 'Entity' . '\\' . $basicName . '\\Model';

			case self::COLLECTION:
				return $ns . '\\' . 'Entity' . '\\' . $basicName . '\\Collection';

			case self::ADMIN_PAGE:
				return $ns . '\\' . 'Admin\\Page' . '\\' . $basicName;

			case self::CONTROLLER:
				return $ns . '\\' . 'Controller' . '\\' . $basicName;

			default:
				return 'di' . $basicName;
		}
	}

	public static function getClassNameFor($name, $kind)
	{
		$basicName = camelize($name, false);

		foreach (self::getAllNamespaces() as $ns)
		{
			$class = self::getClassNameInNamespace($basicName, $ns, $kind);

			if (self::exists($class))
			{
				return $class;
			}
		}

		$class = camelize("di_" . $name . "_custom_" . self::$kindNames[$kind]);

		if (self::exists($class))
		{
			return $class;
		}

		return camelize("di_" . $name . '_' . self::$kindNames[$kind]);
	}

	public static function parentNamespace($namespace)
	{
		$x = strrpos($namespace, '\\');

		return $x === false
			? ''
			: substr($namespace, 0, $x);

		//return dirname($namespace);
	}

	public static function childNamespace($namespace)
	{
		$x = strrpos($namespace, '\\');

		return $x === false
			? ''
			: substr($namespace, $x + 1);

		//return basename($namespace);
	}

	public static function getChildClass($parentFullClassName, $customClassName = null)
	{
		$basicName = $customClassName ?: self::childNamespace($parentFullClassName);
		$subNamespace = self::parentNamespace($parentFullClassName);
		$subNamespace = preg_replace('/^[^\\\\]+\\\\/', '\\', $subNamespace);

		// костыли!
		if (!$basicName && strpos($parentFullClassName, '\\') === false)
		{
			$basicName = preg_replace("/^di/", '', $parentFullClassName);

			if ($basicName == 'Auth' && $parentFullClassName == 'diAuth')
			{
				$subNamespace = '\\Tool';
			}
		}

		foreach (self::getAllNamespaces() as $ns)
		{
			$class = self::getClassNameInNamespace($basicName, $ns . $subNamespace, self::SIMPLE_CLASS);

			if (self::exists($class))
			{
				return $class;
			}
		}

		$class = camelize('di_custom_' . $basicName);

		if (self::exists($class))
		{
			return $class;
		}

		$class = camelize('di_' . $basicName);

		if (self::exists($class))
		{
			return $class;
		}

		return $parentFullClassName;
	}

	static public function getWorkerPath($controller = null, $action = null, $paramsAr = null, $options = [])
	{
		return self::workerPrefix . self::getWorkerBasePath($controller, $action, $paramsAr, $options);
	}

	static public function getAdminWorkerPath($controller = null, $action = null, $paramsAr = null, $options = [])
	{
		return self::workerAdminPrefix . self::getWorkerBasePath($controller, $action, $paramsAr, $options);
	}

	static public function getRoot()
	{
		return str_replace("\\", "/", dirname(dirname(dirname(__FILE__))));
	}

	static public function has($className)
	{
		return !!self::normalizeClassName($className);
	}

	static public function normalizeClassName($className)
	{
		$x = array_search(strtolower($className), array_map("strtolower", self::$classesAr));

		return $x !== false
			? self::$classesAr[$x]
			: null;
	}

	static public function getClassPathSubfolder($className)
	{
		if (isset(self::$classPropertiesAr[$className]["subfolder"]))
		{
			return self::$classPropertiesAr[$className]["subfolder"];
		}

		return "";
	}

	static public function getClassPath($className)
	{
	    $location = isset(self::$classPropertiesAr[$className]["location"])
	    	? self::$classPropertiesAr[$className]["location"]
	    	: null;

		return $location ? self::$libPathsAr[$location] : null;
	}

	public static function getLocation()
	{
		if (self::$location === null)
		{
			foreach (self::$locationMarkers as $locationId => $markerAr)
			{
				$marker = DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $markerAr) . DIRECTORY_SEPARATOR;

				if (strpos(__FILE__, $marker) !== false)
				{
					self::$location = $locationId;

					break;
				}
			}
		}

		if (self::$location === null)
		{
			throw new Exception('Unknown diCore location: ' . __FILE__);
		}

		return self::$location;
	}

	public static function getAssetLocations()
	{
		switch (self::getLocation())
		{
			case self::LOCATION_BEYOND:
				return [
					'css' => '/assets/styles/_core/',
					'fonts' => '/assets/fonts/',
					'images' => '/assets/images/_core/',
					'js' => '/assets/js/_core/',
					'vendor' => '/assets/vendor/'
				];

			default:
			case self::LOCATION_HTDOCS:
				return [
					'css' => '/_core/css/',
					'fonts' => '/_core/fonts/',
					'images' => '/_core/i/',
					'js' => '/_core/js/',
					'vendor' => '/_core/vendor/'
				];
		}
	}

	public static function isNamespaceRoot($namespace)
	{
		return in_array($namespace, self::$coreNamespaces);
	}

	static public function getClassFilename($className, $subFolder = "")
	{
	    $root = $_SERVER['DOCUMENT_ROOT'];
		$path = null;
		$libSubFolderProcessor = null;

		switch (self::getLocation())
		{
			case self::LOCATION_BEYOND:
				$root = dirname($root);

				$libSubFolderProcessor = function($subFolder) {
					return preg_replace("#^/_core#", '/vendor/dimaninc/di_core', $subFolder);
				};

				break;
		}

		// new format, listed
		if (self::has($className))
		{
			$className = self::normalizeClassName($className);
			$path = self::getClassPath($className);
			$subFolder = $subFolder ?: self::getClassPathSubfolder($className);
		}

		if (!$path)
		{
			// namespaces repository
			if (strpos($className, '\\') !== false)
			{
				$rootNamespace = strtok($className, '\\');

				$pathId = self::isNamespaceRoot($rootNamespace)
					? self::pathCoreSources
					: self::pathProjectSources;

				$path = self::$libPathsAr[$pathId];

				// replacing slashes
				$className = preg_replace('#\\\|_(?!.+\\\)#', '/', $className);
			}

			// new format, not listed
			foreach (self::$libPathsAr as $folderId => $folderPath)
			{
				$libSubFolders = [$subFolder];

				if (!empty(self::$libSubFolders[$folderId]))
				{
					$libSubFolders = array_merge($libSubFolders, self::$libSubFolders[$folderId]);
				}

				if ($libSubFolderProcessor)
				{
					$folderPath = $libSubFolderProcessor($folderPath);
				}

				foreach ($libSubFolders as $libSubFolder)
				{
					if ($libSubFolder)
					{
						$libSubFolder .= '/';
					}

					if (is_file($root . $folderPath . $libSubFolder . $className . '.php'))
					{
						$subFolder = $libSubFolder;
						$path = $folderPath;

						break(2);
					}
				}
			}

			// old format
			if (!$path)
			{
				$className = '_class_' . strtolower($className);
				$path = self::$libPathsAr[self::pathOldLib];
			}
		}

		if ($subFolder)
		{
			$subFolder .= '/';
		}

		$fullFn = $root . $path . $subFolder . $className . '.php';

		if (!is_file($fullFn))
		{
			return null;
		}

		return $fullFn;
	}

	static public function exists($className, $subFolder = "")
	{
		if (class_exists($className))
		{
			return true;
		}

		return is_file(self::getClassFilename($className, $subFolder));
	}

	public static function realInc($className, $subFolder = "")
	{
		if (class_exists($className))
		{
			return true;
		}

		$fileName = self::getClassFilename($className, $subFolder);

		if ($fileName)
		{
			require $fileName;

			$ar = get_defined_vars();

			foreach ($ar as $k => $v)
			{
				if (in_array($k, ["class_name", "path_prefix"]))
				{
					continue;
				}

				$GLOBALS[$k] = $v;
			}
		}

		return true;
	}

	/**
	 * @deprecated
	 * Old method to include classes. Now autoload is used
	 *
	 * @param string $className
	 * @param string $subFolder
	 * @return bool
	 */
	static public function inc($className, $subFolder = "")
	{
		if (diLib::AUTOLOAD && !$subFolder)
		{
			return true;
		}

		return self::realInc($className, $subFolder);
	}

	static public function incInterface($interfaceName, $subFolder = "")
	{
		if (interface_exists($interfaceName))
		{
			return true;
		}

		require self::getClassFilename($interfaceName, $subFolder);

		$ar = get_defined_vars();

		foreach ($ar as $k => $v)
		{
			if (in_array($k, array("class_name", "path_prefix")))
			{
				continue;
			}

			$GLOBALS[$k] = $v;
		}

		return true;
	}
}

diLib::checkCompatibility();

if (diLib::AUTOLOAD)
{
	spl_autoload_register(function($class) {
		diLib::realInc($class);
	});
}

require_once dirname(dirname(__FILE__)) . "/vendor/Twig/Autoloader.php";
Twig_Autoloader::register();