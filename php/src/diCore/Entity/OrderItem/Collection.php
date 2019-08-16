<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\OrderItem;

use diCore\Traits\Collection\OrderItem;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterByOrderId($value, $operator = null)
 * @method $this filterByPrice($value, $operator = null)
 * @method $this filterByOrigPrice($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 *
 * @method $this orderByOrderId($direction = null)
 * @method $this orderByPrice($direction = null)
 * @method $this orderByOrigPrice($direction = null)
 * @method $this orderByStatus($direction = null)
 *
 * @method $this selectOrderId()
 * @method $this selectPrice()
 * @method $this selectOrigPrice()
 * @method $this selectStatus()
 */
class Collection extends \diCollection
{
    use OrderItem;

	const type = \diTypes::order_item;
	protected $table = 'order_item';
	protected $modelType = 'order_item';
}