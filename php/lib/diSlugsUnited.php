<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.07.2015
 * Time: 12:07
 */

use diCore\Base\CMS;
use diCore\Data\Types;

class diSlugsUnited
{
	private $targetType;
	private $targetId;
	private $levelNum;

	/** @var \diSlugModel */
	private $model;

	public function __construct($targetType, $targetId, $levelNum = 0)
	{
		$this->targetType = $targetType;
		$this->targetId = $targetId;
		$this->levelNum = $levelNum;

		/** @var \diSlugCollection $col */
		$col = \diCollection::create(Types::slug);
		$col
			->filterByTargetType($this->targetType)
			->filterByTargetId($this->targetId);
		$this->model = $col->getFirstItem();

		if (!$this->getModel()->exists())
		{
			$this->getModel()
				->setTargetType($this->targetType)
				->setTargetId($this->targetId)
				->setLevelNum($this->levelNum);
		}
	}

	public static function emulateRealHref(\diSlugModel $s, CMS $Z)
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
		$this->getModel()
			->hardDestroy();

		return $this;
	}

	protected function getUniqueOptions()
	{
		return [];
	}

	protected function unique($source)
	{
		return \diSlug::unique(
			$source,
			$this->getModel()->getTable(),
			$this->getModel()->getId(),
			extend([
				'queryConditions' => [
					"`level_num` = '$this->levelNum'",
				],
			], $this->getUniqueOptions())
		);
	}

	protected function prepare($source, $lowerCase = true)
	{
		return \diSlug::prepare($source, '-', $lowerCase);
	}

	public function generate($source, $options = [])
	{
		$options = extend([
			'prepare' => true,
            'lowerCase' => true,
		], $options);

		if ($options['prepare'])
		{
			$source = $this->prepare($source, $options['lowerCase']);
		}

		$this->setShortSlug($this->unique($source));

		return $this;
	}

	public function setShortSlug($slug)
	{
		$this->getModel()
			->setSlug($slug);

		return $this;
	}

	public function setFullSlug($parentSlugs = [])
	{
		if (is_scalar($parentSlugs))
		{
			$parentSlugs = $parentSlugs ? [$parentSlugs] : [];
		}

		$parentSlugs[] = $this->getModel()->getSlug();

		$this->getModel()->setFullSlug(join('/', $parentSlugs));

		return $this;
	}

	public function setTargetId($id)
	{
		$this->getModel()->setTargetId($id);

		return $this;
	}

	public function save()
	{
		\diCore\Tool\Logger::getInstance()->log('Slug model: ' . print_r($this->getModel()->get(), true),
            'diSlugsUnited');

		$this->getModel()->save();

		return $this;
	}
}