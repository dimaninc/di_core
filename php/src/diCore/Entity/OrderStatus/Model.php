<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:37
 */

namespace diCore\Entity\OrderStatus;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getColor
 * @method string	getCssClass
 * @method integer	getType
 * @method integer	getVisible
 * @method integer	getOrderNum
 *
 * @method bool hasTitle
 * @method bool hasColor
 * @method bool hasCssClass
 * @method bool hasType
 * @method bool hasVisible
 * @method bool hasOrderNum
 *
 * @method Model setTitle($value)
 * @method Model setColor($value)
 * @method Model setCssClass($value)
 * @method Model setType($value)
 * @method Model setVisible($value)
 * @method Model setOrderNum($value)
 */
class Model extends \diModel
{
	const type = \diTypes::order_status;
	protected $table = 'order_status';
}