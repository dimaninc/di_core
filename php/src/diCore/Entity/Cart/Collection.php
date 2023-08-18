<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\Cart;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterBySessionId($value, $operator = null)
 * @method Collection filterByUserId($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderBySessionId($direction = null)
 * @method Collection orderByUserId($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectSessionId()
 * @method Collection selectUserId()
 * @method Collection selectCreatedAt()
 */
class Collection extends \diCollection
{
    const type = \diTypes::cart;
    protected $table = 'cart';
    protected $modelType = 'cart';
}
