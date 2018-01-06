<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:37
 */

namespace diCore\Entity\OrderStatus;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByColor($value, $operator = null)
 * @method Collection filterByCssClass($value, $operator = null)
 * @method Collection filterByType($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByColor($direction = null)
 * @method Collection orderByCssClass($direction = null)
 * @method Collection orderByType($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTitle()
 * @method Collection selectColor()
 * @method Collection selectCssClass()
 * @method Collection selectType()
 * @method Collection selectVisible()
 * @method Collection selectOrderNum()
 */
class Collection extends \diCollection
{
	const type = \diTypes::order_status;
	protected $table = 'order_status';
	protected $modelType = 'order_status';
}