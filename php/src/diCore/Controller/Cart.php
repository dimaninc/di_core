<?php

namespace diCore\Controller;

use diCore\Data\Types;
use diCore\Entity\Cart\Model;

class Cart extends \diBaseController
{
	/** @var Model */
	private $cart;
	/** @var \diCore\Entity\CartItem\Model */
	private $item;

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

		$this->item = $this->getCart()
			->getItem($this->targetType, $this->targetId);
	}

	public function _postItemAction()
	{
		$this->getItem()
			->setQuantity($this->quantity)
			->save();

		return $this->getInfo();
	}

	public function _deleteItemAction()
	{
		$this->getItem()
			->hardDestroy();

		return $this->getInfo();
	}

	protected function getCart()
	{
		return $this->cart;
	}

	protected function getItem()
	{
		return $this->item;
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