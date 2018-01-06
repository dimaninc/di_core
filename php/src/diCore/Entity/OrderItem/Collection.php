<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\OrderItem;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByOrderId($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByPrice($value, $operator = null)
 * @method Collection filterByOrigPrice($value, $operator = null)
 * @method Collection filterByQuantity($value, $operator = null)
 * @method Collection filterByStatus($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByOrderId($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByPrice($direction = null)
 * @method Collection orderByOrigPrice($direction = null)
 * @method Collection orderByQuantity($direction = null)
 * @method Collection orderByStatus($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectOrderId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectPrice()
 * @method Collection selectOrigPrice()
 * @method Collection selectQuantity()
 * @method Collection selectStatus()
 * @method Collection selectCreatedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::order_item;
	protected $table = 'order_item';
	protected $modelType = 'order_item';
}