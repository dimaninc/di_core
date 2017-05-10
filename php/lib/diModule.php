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

	public function __construct(CMS $Z)
	{
		$this->Z = $Z;
	}

	public static function create(CMS $Z)
	{
		/** @var diModule $o */
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
			$o->$m();
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
	 * @return diTwig
	 */
	public function getTwig()
	{
		return $this->getZ()->getTwig();
	}

	/**
	 * @return diDB
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
	 * @return diBreadCrumbs
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
}