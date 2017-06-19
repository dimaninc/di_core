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

	public function __construct(CMS $Z)
	{
		$this->Z = $Z;
	}

	public static function create(CMS $Z, $options = [])
	{
		$options = extend([
			'noCache' => false,
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

		if ($o->getTwig()->has(\diTwig::TOKEN_FOR_PAGE))
		{
			$o->getTpl()
				->assign([
					"PAGE" => $o->getTwig()->getPage(),
				]);
		}
		elseif ($o->getTpl()->defined("page"))
		{
			$o->getTpl()->parse("PAGE");
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
		if ($this->useModuleCache() && empty($options['noCache']))
		{
			$MC = \diCore\Tool\Cache\Module::basicCreate();
			$contents = $MC->getCachedContents($this, [
				'language' => $this->getZ()->getLanguage(),
				'query_string' => \diRequest::requestQueryString(),
			]);

			if ($contents)
			{
				$this->getTwig()->assign([
					\diTwig::TOKEN_FOR_PAGE => $contents,
				]);

				return $this;
			}
		}

		$this->render();

		return $this;
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
		return $this->getZ()->getRoute($idx);
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
	public function redirect($href, $die = true)
	{
		$this->getZ()->redirect($href, $die);

		return $this;
	}

	/**
	 * @param $href
	 * @param bool|true $die
	 * @return $this
	 */
	public function redirect_301($href, $die = true)
	{
		$this->getZ()->redirect_301($href, $die);

		return $this;
	}

	public function getName()
	{
		$name = get_class($this);

		if ($id = diLib::childNamespace($name))
		{
			$id = strtolower($id);
		}
		else
		{
			$id = underscore($name);
			$id = preg_replace('/^di_|(_custom)?_module$/', '', $id);
		}

		return $id;
	}
}