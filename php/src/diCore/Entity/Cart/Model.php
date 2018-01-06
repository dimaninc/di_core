<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\Cart;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getSessionId
 * @method integer	getUserId
 * @method string	getCreatedAt
 *
 * @method bool hasSessionId
 * @method bool hasUserId
 * @method bool hasCreatedAt
 *
 * @method Model setSessionId($value)
 * @method Model setUserId($value)
 * @method Model setCreatedAt($value)
 */
class Model extends \diModel
{
	const type = \diTypes::cart;
	protected $table = 'cart';
}