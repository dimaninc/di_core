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
 * @method integer	getQuantity
 * @method string	getCreatedAt
 * @method string	getData
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasQuantity
 * @method bool hasCreatedAt
 * @method bool hasData
 *
 * @method $this setTargetType($value)
 * @method $this setTargetId($value)
 * @method $this setQuantity($value)
 * @method $this setCreatedAt($value)
 * @method $this setData($value)
 */
trait OrderItem
{
    /** @var  \diModel */
    protected $item;

    protected $data;

    protected function initItem()
    {
        if (!$this->item) {
            $this->item = CollectionCache::getModel($this->getTargetType(), $this->getTargetId(), true);
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
            : ArrayHelper::getValue($this->data, $key);
    }
}