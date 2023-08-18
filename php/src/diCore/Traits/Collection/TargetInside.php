<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.08.2019
 * Time: 10:53
 */

namespace diCore\Traits\Collection;

/**
 * Trait TargetInside
 * @package diCore\Traits\Collection
 *
 * @method $this filterByTargetType($value, $operator = null)
 * @method $this filterByTargetId($value, $operator = null)
 *
 * @method $this orderByTargetType($direction = null)
 * @method $this orderByTargetId($direction = null)
 *
 * @method $this selectTargetType()
 * @method $this selectTargetId()
 */
trait TargetInside
{
    public function filterByTarget(\diModel $target)
    {
        return $this->filterByTargetType(
            $target->modelType()
        )->filterByTargetId($target->getId());
    }
}
