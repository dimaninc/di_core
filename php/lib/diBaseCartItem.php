<?php

class diBaseCartItem
{
	/** @var diDB */
	private $db;

	/** @var diCartItemModel */
	private $model;

	public $cart_id;
	public $cart_item_id;
	public $id;
	public $id_obj;
	public $count;
	public $type; // items, item_packages, services
	public $volume = 0;
	public $weight = 0;

	private $tableName = "cart_items";

	function __construct($id, $type = "items", $count = 1, $cart_id = 0)
	{
		global $db;

		$this->db = $db;

		$this->cart_id = $cart_id;

		$this->id = $id;
		$this->id_obj = new stdClass;
		list($this->id_obj->item, $this->id_obj->color, $this->id_obj->size) = array_merge(explode("-", $this->id), array(0, 0));

		$this->id_obj->item = (int)$this->id_obj->item;
		$this->id_obj->color = (int)$this->id_obj->color;
		$this->id_obj->size = (int)$this->id_obj->size;

		$this->type = $type;

		$queryAr = array(
			"cart_id='$this->cart_id'",
			"type='$this->type'",
			"target_id='{$this->id_obj->item}'",
			"color_id='{$this->id_obj->color}'",
			"size_id='{$this->id_obj->size}'",
		);

		$test_rs = $this->getDb()->rs($this->getTableName(), "WHERE ".join(" AND ", $queryAr)." ORDER BY id DESC");

		if ($this->getDb()->count($test_rs) > 0)
		{
			$test_r = $this->getDb()->fetch($test_rs);
			$this->cart_item_id = $test_r->id;

			if ($this->getDb()->count($test_rs) > 1)
			{
				$this->getDb()->delete($this->getTableName(), "WHERE ".join(" AND ", $queryAr)." and id!='$this->cart_item_id'");
			}
		}
		else
		{
			$this->cart_item_id = $this->getDb()->insert($this->getTableName(), array(
				"cart_id" => $this->cart_id,
				"type" => $this->type,
				"target_id" => $this->id_obj->item,
				"color_id" => $this->id_obj->color,
				"size_id" => $this->id_obj->size,
				"quantity" => $this->count,
			));
		}

		$this->update($count);
	}

	protected function getDb()
	{
		return $this->db;
	}

	protected function getTableName()
	{
		return $this->tableName;
	}

	public function getModel()
	{
		return $this->model;
	}

	function get_price()
	{
		$_x = strpos($this->id, "-");
		$_id = $_x === false ? $this->id : substr($this->id, 0, $_x);

		$r = $this->getDb()->get_precached_r($this->type, $_id);
		if (!$r)
		{
			return 0;
		}

		//return $r->price2 ? $r->price2 : $r->price;
		return diAction::getItemPrice($r);
	}

	function get_count()
	{
		return $this->count;
	}

	function get_cost()
	{
		return $this->get_count() * $this->get_price();
	}

	function update($count)
	{
		$this->count = $count;
		$this->check_count();

		$this->getDb()->update($this->getTableName(), array(
			"quantity" => $this->count,
		), $this->cart_item_id);
	}

	function add($count)
	{
		$this->count += $count;
		$this->check_count();

		$this->getDb()->update($this->getTableName(), array(
			"quantity" => $this->count,
		), $this->cart_item_id);
	}

	function check_count()
	{
		if ($this->count < 0)
		{
			$this->count = 0;
		}
	}
}