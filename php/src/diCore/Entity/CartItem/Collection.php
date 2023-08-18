<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\CartItem;

use diCore\Traits\Collection\OrderItem;
use diCore\Traits\Collection\TargetInside;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterByCartId($value, $operator = null)
 *
 * @method Collection orderByCartId($direction = null)
 *
 * @method Collection selectCartId()
 */
class Collection extends \diCollection
{
    use OrderItem;
    use TargetInside;

    const type = \diTypes::cart_item;
    protected $table = 'cart_item';
    protected $modelType = 'cart_item';
}
