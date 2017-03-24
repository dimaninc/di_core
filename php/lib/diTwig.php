<?php

use diCore\Helper\FileSystemHelper;
use diCore\Data\Config;

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
		$this->loader = new Twig_Loader_Filesystem(static::wrapPaths($this->getPaths()));

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

	protected function getCustomPaths()
	{
		return [];
	}

	protected function getPaths()
	{
		return array_merge([
			'',
			Config::getTwigCorePath(),
		], $this->getCustomPaths());
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
	public function assign($data)
	{
		if ($data)
		{
			$this->data = extend($this->data, $data);
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

	/**
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
	 * @param $template
	 * @param string|null $token
	 * @param array $data
	 * @return $this
	 */
	public function render($template, $token = null, $data = [])
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
		return $this
			->render($template, self::TOKEN_FOR_PAGE, $data);
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