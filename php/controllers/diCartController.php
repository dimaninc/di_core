<?php
class diCartController extends diBaseController
{
	/** @var diCart */
	private $Cart;

	protected $type;
	protected $targetId;
	protected $count;
	protected $rnd;

	public function __construct()
	{
		parent::__construct();

		$this->Cart = new diCart();

		$this->type = diRequest::get("type", "items");
		$this->targetId = diRequest::get("id", "");
		$this->count = diRequest::get("count", 0);
		$this->rnd = diRequest::get("rnd", "");
	}

	protected function getCart()
	{
		return $this->Cart;
	}

	public function addAction()
	{
		$this->getCart()->add($this->targetId, $this->type, $this->count);

		$this->response("add");
	}

	public function addMoreAction()
	{
		$this->getCart()->add_more($this->targetId, $this->type, $this->count);

		$this->response("add_more");
	}

	public function updateAction()
	{
		$this->getCart()->update($this->targetId, $this->type, $this->count);

		$this->response("update");
	}

	public function removeAction()
	{
		$this->getCart()->remove($this->targetId, $this->type);

		$this->response("remove");
	}

	public function removeItemAction()
	{
		$this->getCart()->removeItem($this->targetId);

		$this->response("remove_item");
	}

	protected function response($action)
	{
		$price = $this->getCart()->get_item_price($this->targetId, $this->type);
		$count = (int)$this->getCart()->get_item_count($this->targetId, $this->type);

		$this->defaultResponse(array(
			"ok" => 1,
			"action" => $action,
			"id" => $this->targetId,
			"type" => $this->type,
			"price" => $price,
			"count" => $count,
			"cost" => $price * $count,
			"rnd" => $this->rnd,
			"totals" => array(
				"count" => $this->getCart()->get_total_items_count(),
				"cost" => $this->getCart()->get_total_items_cost(),
			),
		));
	}
}