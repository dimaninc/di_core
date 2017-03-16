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
}