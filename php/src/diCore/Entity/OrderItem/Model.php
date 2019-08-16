<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\OrderItem;

use diCore\Traits\Model\OrderItem;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getOrderId
 * @method double	getPrice
 * @method double	getOrigPrice
 * @method integer	getStatus
 *
 * @method bool hasOrderId
 * @method bool hasPrice
 * @method bool hasOrigPrice
 * @method bool hasStatus
 *
 * @method $this setOrderId($value)
 * @method $this setPrice($value)
 * @method $this setOrigPrice($value)
 * @method $this setStatus($value)
 */
class Model extends \diModel
{
    use OrderItem;

	const type = \diTypes::order_item;
	protected $table = 'order_item';
}