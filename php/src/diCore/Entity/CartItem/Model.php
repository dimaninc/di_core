<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\CartItem;

use diCore\Traits\Model\OrderItem;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getCartId
 *
 * @method bool hasCartId
 *
 * @method Model setCartId($value)
 */
class Model extends \diModel
{
    use OrderItem;

	const type = \diTypes::cart_item;
	protected $table = 'cart_item';

	public function getIdForCart()
	{
		return $this->getTargetType() . '-' . $this->getTargetId();
	}

	public function getTitleForCart()
	{
		return $this->getItem()->get('title');
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'id_for_cart' => $this->getIdForCart(),
		]);
	}
}