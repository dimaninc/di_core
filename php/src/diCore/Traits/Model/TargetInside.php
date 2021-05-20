<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.02.2017
 * Time: 22:54
 */

namespace diCore\Traits\Model;

use diCore\Tool\CollectionCache;

/**
 * Trait TargetInside
 * @package diCore\Traits\Model
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 *
 * @method $this setTargetType($value)
 * @method $this setTargetId($value)
 */
trait TargetInside
{
	/** @var \diModel */
	protected $target;

	protected function getTunedTargetType()
    {
        return $this->getTargetType();
    }

    public function getTargetTypeName()
    {
        return \diTypes::getName($this->getTargetType());
    }

    public function getTargetTypeTitle()
    {
        return \diTypes::getTitle($this->getTargetType());
    }

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

    public function isTarget(\diModel $model)
    {
        return
            $this->hasTargetType() &&
            $this->hasTargetId() &&
            $this->getTargetType() == $model->modelType() &&
            $this->getTargetId() == $model->getId();
    }

	public function getTargetModel()
	{
		if (!$this->target) {
			$this->target = method_exists($this, 'getRelated') && $this->getRelated('target_model')
				? $this->getRelated('target_model')
				: ($this->getTargetType()
					? CollectionCache::getModel($this->getTunedTargetType(), $this->getTargetId(), true)
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

    /**
     * @param \diModel|int $targetType
     * @param int|null $targetId
     * @return $this
     * @throws \Exception
     */
	public static function createByTarget($targetType, $targetId = null)
    {
        if ($targetType instanceof \diModel && $targetId === null) {
            $targetId = $targetType->getId();
            $targetType = \diTypes::getId($targetType->getTable());
        }

        /** @var \diCollection $colClass */
        $colClass = static::getCollectionClass();

        $col = $colClass::create()
            ->filterByTargetType($targetType)
            ->filterByTargetId($targetId);

        return $col->getFirstItem();
    }
}