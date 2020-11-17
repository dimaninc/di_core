<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.05.2019
 * Time: 19:24
 */

namespace diCore\Traits\Model;

use diCore\Helper\ArrayHelper;
use diCore\Tool\CollectionCache;

/**
 * Trait OrderItem
 * @package diCore\Traits\Model
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method double	getPrice
 * @method integer	getQuantity
 * @method string	getCreatedAt
 * @method string	getData
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasPrice
 * @method bool hasQuantity
 * @method bool hasCreatedAt
 * @method bool hasData
 *
 * @method $this setTargetType($value)
 * @method $this setTargetId($value)
 * @method $this setPrice($value)
 * @method $this setQuantity($value)
 * @method $this setCreatedAt($value)
 * @method $this setData($value)
 */
trait OrderItem
{
    /** @var  \diModel */
    protected $item;

    protected $data;

    public static function createByTarget($targetType, $targetId = null)
    {
        if ($targetType instanceof \diModel && $targetId === null) {
            $targetId = $targetType->getId();
            $targetType = $targetType->modelType();
        }

        $col = \diCollection::create(static::modelTypeName());
        $col
            ->filterBy('target_type', $targetType)
            ->filterBy('target_id', $targetId);

        return $col->getFirstItem();
    }

    protected function initItem()
    {
        if (!$this->item) {
            $this->item = CollectionCache::getModel($this->getTargetType(), $this->getTargetId(), true);
            $this->item
                ->setRelated('user_id', $this->getRelated('user_id'));
        }

        return $this;
    }

    public function getItem()
    {
        return $this->initItem()->item;
    }

    public function getDataObject($key = null)
    {
        if (!$this->data && $this->hasData()) {
            $this->data = json_decode($this->getData());
        }

        return $key === null
            ? $this->data
            : ArrayHelper::get($this->data, $key);
    }

    public function getCost()
    {
        $quantity = method_exists($this, 'getTunedQuantity')
            ? $this->getTunedQuantity()
            : $this->getQuantity();

        /*
        $price = method_exists($this, 'getActualPrice')
            ? $this->getActualPrice()
            : $this->getPrice();
        */
        $price = $this->getPrice();

        return $price * $quantity;
    }

    public function addQuantity($difference)
    {
        $this->setQuantity($this->getQuantity() + $difference);

        return $this;
    }
}