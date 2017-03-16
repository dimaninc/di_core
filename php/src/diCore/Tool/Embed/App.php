<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.06.2016
 * Time: 22:12
 */

namespace diCore\Tool\Embed;

class App
{
	use \diSingleton;

	const STANDARD = 1;
	const VK_APP = 2;
	const FACEBOOK_APP = 3;
	const SITE_EMBED_APP = 4;

	protected static $outputModes = [
		self::STANDARD => 'standard',
		self::VK_APP => 'vk-app',
		self::FACEBOOK_APP => 'fb-app',
		self::SITE_EMBED_APP => 'site-embed',
	];

	protected static $classes = [
		self::VK_APP => vkHelper::class,
		self::FACEBOOK_APP => fbHelper::class,
		self::SITE_EMBED_APP => siteHelper::class,
	];

	const QUERY_PARAM = 'outputMode';

	protected $mode = self::STANDARD;

	public function detect()
	{
		/**
		 * @var int $mode
		 * @var Helper $class
		 */
		foreach (self::$classes as $mode => $class)
		{
			if ($class::is())
			{
				$this->setMode($mode);
			}
		}

		return $this;
	}

	public function killGetParams()
	{
		if (!$this->isEmbedApp())
		{
			return $this;
		}

		/** @var Helper $class */
		$class = $this->getModeHelperClass();

		$_GET = \diArrayHelper::filterByKey($_GET, [], array_merge(
			[App::QUERY_PARAM],
			$class::getQueryParamsToRemove()
		));

		return $this;
	}

	public function isVkApp()
	{
		return $this->getMode() == self::VK_APP;
	}

	public function isFacebookApp()
	{
		return $this->getMode() == self::FACEBOOK_APP;
	}

	public function isSocialEmbedApp()
	{
		return $this->isVkApp() || $this->isFacebookApp();
	}

	public function isSiteEmbedApp()
	{
		return $this->getMode() == self::SITE_EMBED_APP;
	}

	public function isEmbedApp()
	{
		return $this->isSocialEmbedApp() || $this->isSiteEmbedApp();
	}

	public function isStandard()
	{
		return $this->getMode() == self::STANDARD;
	}

	public function vkApp()
	{
		$this->setMode(self::VK_APP);

		return $this;
	}

	public function facebookApp()
	{
		$this->setMode(self::FACEBOOK_APP);

		return $this;
	}

	public function siteEmbedApp()
	{
		$this->setMode(self::SITE_EMBED_APP);

		return $this;
	}

	protected function setMode($mode)
	{
		$this->mode = $mode;

		return $this;
	}

	public function getMode()
	{
		return $this->mode;
	}

	public function getModeName()
	{
		return isset(self::$outputModes[$this->getMode()])
			? self::$outputModes[$this->getMode()]
			: null;
	}

	public function getModeHelperClass()
	{
		return isset(self::$classes[$this->mode]) ? self::$classes[$this->mode] : null;
	}

	public function getAllGetParams()
	{
		$ar = [
			self::QUERY_PARAM,
		];

		/**
		 * @var int $mode
		 * @var Helper $class
		 */
		foreach (self::$classes as $mode => $class)
		{
			$ar = array_merge($ar, $class::getQueryParamsToRemove());
		}

		return $ar;
	}
}