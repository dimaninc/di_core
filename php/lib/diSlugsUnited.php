<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.07.2015
 * Time: 12:07
 */

use diCore\Base\CMS;

class diSlugsUnited
{
	private $targetType;
	private $targetId;
	private $levelNum;

	/** @var diSlugModel */
	private $model;

	public function __construct($targetType, $targetId, $levelNum = null)
	{
		$this->targetType = $targetType;
		$this->targetId = $targetId;
		$this->levelNum = $levelNum;

		$this->model = diCollection::create("slug", "WHERE target_type='$this->targetType' and target_id='$this->targetId'")
			->getFirstItem();

		if (!$this->getModel()->exists())
		{
			$this->getModel()
				->setTargetType($this->targetType)
				->setTargetId($this->targetId)
				->setLevelNum($this->levelNum);
		}
	}

	public static function emulateRealHref(diSlugModel $s, CMS $Z)
	{
	}

	public function needed()
	{
		return $this->getModel()->exists();
	}

	public function getModel()
	{
		return $this->model;
	}

	public function generateAndSave($source, $parentSlugs)
	{
		return $this
			->generate($source)
			->setFullSlug($parentSlugs)
			->save();
	}

	public function kill()
	{
		$this->getModel()->hardDestroy();
	}

	protected function getUniqueOptions()
	{
		return array();
	}

	protected function unique($source)
	{
		return diSlug::unique(
			$source,
			$this->getModel()->getTable(),
			$this->getModel()->getId(),
			extend(array(
				"queryConditions" => array(
					"`level_num`='$this->levelNum'",
				),
			), $this->getUniqueOptions())
		);
	}

	protected function prepare($source)
	{
		return diSlug::prepare($source);
	}

	public function generate($source, $options = array())
	{
		$options = extend(array(
			"prepare" => true,
		), $options);

		if ($options["prepare"])
		{
			$source = $this->prepare($source);
		}

		$this->getModel()->setSlug($this->unique($source));

		return $this;
	}

	public function setFullSlug($parentSlugs = array())
	{
		if (is_scalar($parentSlugs))
		{
			$parentSlugs = $parentSlugs ? array($parentSlugs) : array();
		}

		$parentSlugs[] = $this->getModel()->getSlug();

		$this->getModel()->setFullSlug(join("/", $parentSlugs));

		return $this;
	}

	public function setTargetId($id)
	{
		$this->getModel()->setTargetId($id);

		return $this;
	}

	public function save()
	{
		$this->getModel()->save();

		return $this;
	}
}