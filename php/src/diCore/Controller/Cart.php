<?php

namespace diCore\Controller;

use diCore\Entity\Cart\Model;

class Cart extends \diBaseController
{
	/** @var Model */
	protected $cart;
	/** @var \diCore\Entity\CartItem\Model */
	protected $cartItem;

	protected $targetType;
	protected $targetId;
	protected $quantity;

	public function __construct($params = [])
	{
		parent::__construct($params);

		$this->cart = Model::autoCreate()
			->save();

		$this->targetType = \diRequest::request('target_type', 0);
		$this->targetId = \diRequest::request('target_id', 0);
		$this->quantity = \diRequest::request('quantity', 0);
	}
	
	protected function setupCartItem()
	{
		if (!$this->cartItem)
		{
			$this->cartItem = $this->getCart()
				->getItem($this->targetType, $this->targetId);
		}

		return $this;
	}

	public function _postItemAction()
	{
		$this->updateItem();

		$this->getCartItem()
			->save();

		return $this->getInfo();
	}

	public function _deleteItemAction()
	{
		$this->getCartItem()
			->hardDestroy();

		return $this->getInfo();
	}

	protected function updateItem()
	{
		$this->getCartItem()
			->setQuantity($this->quantity);

		return $this;
	}

	/**
	 * @return Model
	 */
	protected function getCart()
	{
		return $this->cart;
	}

	protected function getCartItem()
	{
		$this
			->setupCartItem();
		
		return $this->cartItem;
	}

	protected function getInfo()
	{
		return [
			'ok' => true,
			'target_type' => $this->targetType,
			'target_id' => $this->targetId,
			'quantity' => $this->quantity,
			'totals' => [
			],
		];
	}
}