<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 10.09.15
 * Time: 16:38
 */

use diCore\Base\CMS;

abstract class diModule
{
	/** @var CMS */
	private $Z;

	protected $useModuleCache = false;

	protected $renderOptions = [];
	protected $bootstrapSettings = [];

	const NO_CACHE_OPTION = 'noCache';

	public function __construct(CMS $Z)
	{
		$this->Z = $Z;
	}

	public static function create(CMS $Z, $options = [])
	{
		$options = extend([
			'noCache' => false,
			'ignoreRealRoutes' => false,
			'bootstrapSettings' => null,
		], $options);

		/** @var \diModule $o */
		$o = new static($Z);

		$m = "render";
		$beforeM = "beforeRender";
		$afterM = "afterRender";

		if (!method_exists($o, $m))
		{
			throw new \Exception("Class " . get_class($o) . " doesn't have '$m' method");
		}

		if ($o->$beforeM())
		{
			$o->doRender($options);
		}

		$o->$afterM();

		if ($o->getTwig()->hasPage())
		{
			if ($Z::templateEngineIsFastTemplate())
			{
				$o->getTpl()
					->assign([
						"PAGE" => $o->getTwig()->getPage(),
					]);
			}
		}
		elseif ($o->getTpl()->defined("page"))
		{
			$o->getTpl()->process('page');

			if ($Z::templateEngineIsTwig())
			{
				$o->getTwig()
					->importFromFastTemplate($o->getTpl(), [
						\diTwig::TOKEN_FOR_PAGE => 'page',
					], false);
			}
		}

		return $o;
	}

	public function getResultPage()
	{
		return $this->getTwig()->getPage() ?: $this->getTpl()->getAssigned('PAGE');
	}

	abstract public function render();

	public function beforeRender()
	{
		return true;
	}

	public function afterRender()
	{
		$this->getZ()->beforeParsePage();

		return $this;
	}

	protected function doRender($options = [])
	{
		$this->setRenderOptions($options);

		if ($this->useModuleCache() && !$this->getRenderOption('noCache'))
		{
			$bootstrapSettings = $this->getCurrentBootstrapSettings();

			if (empty($bootstrapSettings[self::NO_CACHE_OPTION]))
			{
				$MC = \diCore\Tool\Cache\Module::basicCreate();
				$contents = $MC->getCachedContents($this, [
					'language' => $this->getZ()->getLanguage(),
					'query_string' => \diRequest::requestQueryString(),
					'bootstrap_settings' => $bootstrapSettings,
				]);
			}

			if (!empty($contents))
			{
				$this
					->setBootstrapSettings($bootstrapSettings)
					->cachedBootstrap();

				$this->getTwig()->assign([
					\diTwig::TOKEN_FOR_PAGE => $contents,
				]);

				return $this;
			}
		}

		// todo: remove routes when module cache is being made from browser
		if ($this->getRenderOption('noCache') && false)
		{
			$this
				->bootstrapRoutes();
		}

		$this
			->bootstrap()
			->render();

		return $this;
	}

	/**
	 * @return array
	 */
	protected function getCurrentBootstrapSettings()
	{
		return [];
	}

	protected function cachedBootstrap()
	{
		return $this;
	}

	protected function bootstrapRoutes()
	{
		$this->getZ()
			->setRoute([]);

		return $this;
	}

	protected function bootstrap()
	{
		return $this;
	}

	protected function setRenderOptions($options)
	{
		$this->renderOptions = $options;
		$this->setBootstrapSettings($this->getRenderOption('bootstrapSettings'));

		return $this;
	}

	protected function getRenderOption($name = null)
	{
		return $name === null
			? $this->renderOptions
			: (isset($this->renderOptions[$name]) ? $this->renderOptions[$name] : null);
	}

	protected function setBootstrapSettings($options)
	{
		if (!is_array($options))
		{
			$a = explode(\diCore\Tool\Cache\Module::BOOTSTRAP_SETTINGS_END, $options);
			$options = [];

			foreach ($a as $kv)
			{
				list($k, $v) = array_merge(explode(\diCore\Tool\Cache\Module::BOOTSTRAP_SETTINGS_EQ, $kv), [null, null]);

				if ($k)
				{
					$options[$k] = $v;
				}
			}
		}

		$this->bootstrapSettings = $options;

		return $this;
	}

	protected function getBootstrapSettings($name = null)
	{
		return $name === null
			? $this->bootstrapSettings
			: (isset($this->bootstrapSettings[$name]) ? $this->bootstrapSettings[$name] : null);
	}

	protected function useModuleCache()
	{
		return \diCore\Data\Config::useModuleCache() && $this->useModuleCache;
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function getZ()
	{
		return $this->Z;
	}

	/**
	 * @return FastTemplate
	 * @deprecated
	 */
	public function getTpl()
	{
		return $this->getZ()->getTpl();
	}

	/**
	 * @return \diTwig
	 */
	public function getTwig()
	{
		return $this->getZ()->getTwig();
	}

	/**
	 * @return \diDB
	 */
	public function getDb()
	{
		return $this->getZ()->getDb();
	}

	/**
	 * @param int|null $idx
	 * @return array|string|null
	 */
	public function getRoute($idx = null)
	{
		return $this->getRenderOption('ignoreRealRoutes')
            ? null
            : $this->getZ()->getRoute($idx);
	}

	/**
	 * @return \diCore\Base\BreadCrumbs
	 */
	public function getBreadCrumbs()
	{
		return $this->getZ()->getBreadCrumbs();
	}

	/**
	 * @param $href
	 * @param bool|true $die
	 * @return $this
	 */
	public function redirect($href, $die = true, $headerDebugMessage = null, $headerDebugName = null)
	{
		$this->getZ()->redirect($href, $die, $headerDebugMessage, $headerDebugName);

		return $this;
	}

	/**
	 * @param $href
	 * @param bool|string $die  Redirect debug message can be instead of $die (this means also that $die = true)
	 * @return $this
	 */
	public function redirect_301($href, $die = true, $headerDebugMessage = null, $headerDebugName = null)
	{
		$this->getZ()->redirect_301($href, $die, $headerDebugMessage, $headerDebugName);

		return $this;
	}

	public function getName()
	{
		$name = get_class($this);

		if ($id = \diLib::childNamespace($name))
		{
			$id = underscore($id);
		}
		else
		{
			$id = underscore($name);
			$id = preg_replace('/^di_|(_custom)?_module$/', '', $id);
		}

		return $id;
	}
}