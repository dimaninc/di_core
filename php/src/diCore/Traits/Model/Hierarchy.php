<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.02.2022
 * Time: 11:35
 */

namespace diCore\Traits\Model;

/**
 * Trait Hierarchy
 * @package diCore\Traits\Model
 *
 * @method integer	getParent
 * @method integer	getLevelNum
 * @method integer	getOrderNum
 *
 * @method bool hasParent
 * @method bool hasLevelNum
 * @method bool hasOrderNum
 *
 * @method $this setParent($value)
 * @method $this setLevelNum($value)
 * @method $this setOrderNum($value)
 */
trait Hierarchy
{
    public function addChildren(\diModel $model)
    {
        $this->setRelated('_children', $this->getChildren()->addItem($model));

        return $this;
    }

    /**
     * @return \diCollection
     */
    public function getChildren()
    {
        return $this->getRelated('_children') ?:
            static::getCollectionClass()::createEmpty();
    }

    public function hasChildren()
    {
        return $this->getChildren()->count() > 0;
    }
}
