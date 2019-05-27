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
 * @method Collection filterByOrderId($value, $operator = null)
 * @method Collection filterByPrice($value, $operator = null)
 * @method Collection filterByOrigPrice($value, $operator = null)
 * @method Collection filterByStatus($value, $operator = null)
 *
 * @method Collection orderByOrderId($direction = null)
 * @method Collection orderByPrice($direction = null)
 * @method Collection orderByOrigPrice($direction = null)
 * @method Collection orderByStatus($direction = null)
 *
 * @method Collection selectOrderId()
 * @method Collection selectPrice()
 * @method Collection selectOrigPrice()
 * @method Collection selectStatus()
 */
class Collection extends \diCollection
{
    use OrderItem;

	const type = \diTypes::order_item;
	protected $table = 'order_item';
	protected $modelType = 'order_item';
}