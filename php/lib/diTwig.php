<?php

use diCore\Data\Config;
use diCore\Helper\FileSystemHelper;

/**
 * Wrapper for Twig template engine
 *
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.04.2016
 * Time: 9:45
 */
class diTwig
{
	const TEMPLATES_FOLDER = 'templates';
	const CACHE_FOLDER = '_cfg/cache/twig';

	const TOKEN_FOR_PAGE = '_page';

	const FILE_EXTENSION = '.html.twig';

	const customClassName = "diCustomTwig";

	const NAMESPACE_CORE = 'core';
	const NAMESPACE_MAIN = Twig_Loader_Filesystem::MAIN_NAMESPACE;

	/**
	 * @var Twig_Loader_Filesystem
	 */
	private $loader;

	/**
	 * @var Twig_Environment
	 */
	private $Twig;

	/**
	 * @var array
	 */
	private $data = [];

	/**
	 * diTwig constructor.
	 * @param array $options
	 */
	public function __construct($options = [])
	{
		$this->loader = new Twig_Loader_Filesystem();

		foreach ($this->getAllPaths() as $namespace => $paths)
		{
			$this->loader->setPaths(static::wrapPaths($paths), $namespace);
		}

		$this->Twig = new Twig_Environment($this->loader, extend([
			'cache' => Config::getCacheFolder() . static::CACHE_FOLDER,
			'auto_reload' => diCurrentCMS::ignoreCaches(),
		], $options));
	}

	/**
	 * @param array $options
	 * @return diTwig
	 */
	public static function create($options = [])
	{
		$className = class_exists(self::customClassName)
			? self::customClassName
			: get_called_class();

		$t = new $className($options);

		return $t;
	}

	protected function getAllPaths()
	{
		return extend([
			//self::NAMESPACE_CORE => $this->getCorePaths(),
			self::NAMESPACE_MAIN => array_merge($this->getMainPaths(), $this->getCorePaths()),
		], $this->getOtherPaths());
	}

	protected function getCorePaths()
	{
		return [
			Config::getTwigCorePath(),
		];
	}

	protected function getMainPaths()
	{
		return [
			'',
		];
	}

	protected function getOtherPaths()
	{
		return [
			// 'namespace' => ['paths'],
		];
	}

	protected static function wrapPaths($paths)
	{
		if (!is_array($paths))
		{
			$paths = [$paths];
		}

		foreach ($paths as &$path)
		{
			$path = Config::getTemplateFolder() . static::TEMPLATES_FOLDER . ($path ? '/' . $path : '');
		}

		return $paths;
	}

	protected static function wrapTemplateName($name)
	{
		return $name . static::FILE_EXTENSION;
	}

	/**
	 * @deprecated
	 * @return Twig_Environment
	 */
	public function getTwig()
	{
		return $this->getEngine();
	}

	/**
	 * @return Twig_Environment
	 */
	public function getEngine()
	{
		return $this->Twig;
	}

	/**
	 * Checks if template file exists
	 *
	 * @param string $templateName
	 * @return bool
	 */
	public function exists($templateName)
	{
		return $this->loader->exists(static::wrapTemplateName($templateName));
	}

	/**
	 * Get whole context data array or an item by key
	 *
	 * @param string|null $key
	 * @return array|string|null
	 */
	public function get($key = null)
	{
		if ($key !== null)
		{
			return isset($this->data[$key]) ? $this->data[$key] : null;
		}

		return $this->data;
	}

	public function getPage()
	{
		return $this->get(self::TOKEN_FOR_PAGE);
	}

	/**
	 * Check if context data item exists by key
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has($key)
	{
		return !!$this->get($key);
	}

	/**
	 * Add context data
	 *
	 * @param array|object $data
	 * @return $this
	 */
	public function assign($data, $recursive = false)
	{
		if ($data)
		{
			if ($recursive)
			{
				$this->data = array_replace_recursive($this->data, $data);
			}
			else
			{
				$this->data = extend($this->data, $data);
			}
		}

		return $this;
	}

	public function getAssigned($token)
	{
		if (isset($this->data[$token]))
		{
			return $this->data[$token];
		}

		return null;
	}

	public function assigned($token)
	{
		return !!$this->getAssigned($token);
	}

	public function addFunction($name, callable $callable)
	{
		$function = new Twig_SimpleFunction($name, $callable);
		$this->Twig->addFunction($function);

		return $this;
	}

	/**
	 * Parse template from file
	 * @param $template
	 * @param array $data
	 * @return string
	 */
	public function parse($template, $data = [])
	{
		return $this->getEngine()
			->render(static::wrapTemplateName($template), extend($this->get(), $data));
	}

	/**
	 * Parse template from text
	 * @param $templateText
	 * @param array $data
	 * @return string
	 * @throws Exception
	 */
	public function parseVirtual($templateText, $data = [])
	{
		return $this->getEngine()
			->createTemplate($templateText)
			->render(extend($this->get(), $data));
	}

	/**
	 * @param $template
	 * @param string $token
	 * @param array $data
	 * @return $this
	 */
	public function render($template, $token, $data = [])
	{
		$this->assign([
			$token => $this->parse($template, $data),
		]);

		return $this;
	}

	/**
	 * @param string $template
	 * @param array $data
	 * @return diTwig
	 */
	public function renderPage($template, $data = [])
	{
		global $Z;

		return $this
			->render($template, self::TOKEN_FOR_PAGE, extend([
				'Z' => isset($Z) ? $Z : null,
			], $data));
	}

	public function importFromFastTemplate(FastTemplate $tpl, $tokens = [], $clear = true)
	{
		foreach ($tokens as $k => $v)
		{
			if (!is_string($k))
			{
				$k = $v;
			}

			$this->assign([
				$k => $tpl->getAssigned($v),
			]);

			if ($clear)
			{
				$tpl->clear($v);
			}
		}

		return $this;
	}

	public static function flushCache()
	{
		$dir = Config::getCacheFolder() . self::CACHE_FOLDER;

		FileSystemHelper::delTree($dir, false);
	}
}