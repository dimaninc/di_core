<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\CartItem;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByCartId($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByQuantity($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByCartId($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByQuantity($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectCartId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectQuantity()
 * @method Collection selectCreatedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::cart_item;
	protected $table = 'cart_item';
	protected $modelType = 'cart_item';
}