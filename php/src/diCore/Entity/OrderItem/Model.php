<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\OrderItem;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getOrderId
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method double	getPrice
 * @method double	getOrigPrice
 * @method integer	getQuantity
 * @method integer	getStatus
 * @method string	getCreatedAt
 *
 * @method bool hasOrderId
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasPrice
 * @method bool hasOrigPrice
 * @method bool hasQuantity
 * @method bool hasStatus
 * @method bool hasCreatedAt
 *
 * @method Model setOrderId($value)
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setPrice($value)
 * @method Model setOrigPrice($value)
 * @method Model setQuantity($value)
 * @method Model setStatus($value)
 * @method Model setCreatedAt($value)
 */
class Model extends \diModel
{
	const type = \diTypes::order_item;
	protected $table = 'order_item';
}