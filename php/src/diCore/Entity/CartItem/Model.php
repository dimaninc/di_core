<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\CartItem;
use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getCartId
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method integer	getQuantity
 * @method string	getCreatedAt
 *
 * @method bool hasCartId
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasQuantity
 * @method bool hasCreatedAt
 *
 * @method Model setCartId($value)
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setQuantity($value)
 * @method Model setCreatedAt($value)
 */
class Model extends \diModel
{
	const type = \diTypes::cart_item;
	protected $table = 'cart_item';
	/** @var  \diModel */
	protected $item;

	public function getIdForCart()
	{
		return $this->getTargetType() . '-' . $this->getTargetId();
	}

	public function getTitleForCart()
	{
		return $this->getItem()->get('title');
	}

	protected function initItem()
	{
		if (!$this->item)
		{
			$this->item = CollectionCache::getModel($this->getTargetType(), $this->getTargetId(), true);
		}

		return $this;
	}

	public function getItem()
	{
		return $this->initItem()->item;
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'id_for_cart' => $this->getIdForCart(),
		]);
	}
}