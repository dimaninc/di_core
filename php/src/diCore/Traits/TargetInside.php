<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.02.2017
 * Time: 22:54
 */

namespace diCore\Traits;

/**
 * Class TargetInside
 * @package diCore\Traits
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 *
 * @method TargetInside setTargetType($value)
 * @method TargetInside setTargetId($value)
 */
trait TargetInside
{
	/** @var \diModel */
	private $target;

	/**
	 * @param TargetInside $model
	 * @return bool
	 */
	public function isTargetTheSameWith($model)
	{
		return
			$this->hasTargetType() &&
			$this->hasTargetId() &&
			$this->getTargetType() == $model->getTargetType() &&
			$this->getTargetId() == $model->getTargetId();
	}

	public function getTargetModel()
	{
		if (!$this->target)
		{
			$this->target = method_exists($this, 'getRelated') && $this->getRelated('target_model')
				? $this->getRelated('target_model')
				: ($this->getTargetType()
					? \diModel::create($this->getTargetType(), $this->getTargetId())
					: new \diModel()
				);
		}

		return $this->target;
	}

	public function setTarget(\diModel $target)
	{
		$this
			->setTargetType($target->modelType())
			->setTargetId($target->getId());

		return $this;
	}
}