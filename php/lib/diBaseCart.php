<?php
/*
    // dimaninc

    // 2013/05/07
        * re-birth for rebzi-shop.ru

    // 2009/02/24
        * re-birth for master-product.ru

    // 2007/12/25
        * re-birth for stopcar.ru

    // 2007/09/11
        * birthday
*/

class diBaseCart
{
	public $ar;
	public $id;
	public $r;

	protected $forceGetRecord = true;

	/** @var diCartModel */
	protected $model;

	/** @var diDB */
	private $db;

	function __construct($uid = null)
	{
		global $db;

		$this->db = $db;

		$this->ar = array();

		$this->model = diModel::create(diTypes::cart, $uid);
		$this->r = $this->getModel()->get();
		$this->id = $this->getModel()->getId();

		$this->load_from_db();

		/*
		if (!$this->id)
		{
			if (logged_in())
			{
				//$this->load_from_db();
			}
			else
			{
				//$this->create_cart_in_db();
			}
		}
		*/
	}

	protected function getDb()
	{
		return $this->db;
	}

	protected function getModel()
	{
		return $this->model;
	}

	/**
	 * @param int $idx
	 * @return diCartItem
	 */
	public function getByIdx($idx)
	{
		return $this->ar[$idx];
	}

	function create_cart_in_db()
	{
		$cart_r = $this->id ? $this->getDb()->r("carts", $this->id) : false;

		if ($cart_r)
		{
			return $this->id;
		}

		$this->id = $this->getDb()->insert("carts", array(
			"user_id" => diAuth::i()->getUserId() ?: 0,
			"session_id" => diSession::id(),
		));

		return $this->id;
	}

	function load_from_db()
	{
		if (count($this->ar) == 0)
		{
			$cartItems = $this->getModel()->exists() ? diCollection::create("cart_item", "WHERE cart_id='{$this->getModel()->getId()}'") : array();
			/** @var diCartItemModel $cartItem */
			foreach ($cartItems as $cartItem)
			{
				if ($cartItem->hasQuantity())
				{
					$this->update($cartItem);
				}
			}
		}
	}

	function migrate_to_user($session_id = false, $user_id = false)
	{
		if ($session_id === false)
		{
			$session_id = diSession::id();
		}

		if ($user_id === false && diAuth::i()->authorized())
		{
			$user_id = diAuth::i()->getUserId();
		}

		if ($user_id && $session_id)
		{
			$this->getDb()->update("carts", array("user_id" => $user_id), "WHERE session_id='$session_id'");

			$this->load_from_db();
		}
	}

	/**
	 * @param $id
	 * @param string $type
	 * @return bool|diCartItem
	 */
	function get_item($id, $type = "items")
	{
		$idx = $this->get_item_idx($id, $type);

		if ($idx === false)
		{
			return false;
		}
		else
		{
			return $this->getByIdx($idx);
		}
	}

	function get_item_idx($id, $type = "items")
	{
		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			//if (in_array($id, array($o->id, $o->id_obj->item)) && $o->type == $type)
			if ($id == $o->id && $o->type == $type)
			{
				return $idx;
			}
		}

		return false;
	}

	function get_item_count($id, $type = "items")
	{
		$idx = $this->get_item_idx($id, $type);

		if ($idx === false)
		{
			return false;
		}
		else
		{
			return $this->getByIdx($idx)->get_count();
		}
	}

	function get_item_price($id, $type = "items")
	{
		$idx = $this->get_item_idx($id, $type);

		if ($idx === false)
		{
			return false;
		}
		else
		{
			return $this->getByIdx($idx)->get_price();
		}
	}

	function get_item_cost($id, $type = "items")
	{
		$idx = $this->get_item_idx($id, $type);

		if ($idx === false)
		{
			return false;
		}
		else
		{
			return $this->getByIdx($idx)->get_cost();
		}
	}

	function get_total_items_count()
	{
		$count = 0;

		if (is_null($this->ar))
		{
			$this->ar = array();
		}

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			$count += $o->get_count();
		}

		return $count;
	}

	function get_items_count($type = "items")
	{
		$count = 0;

		if (is_null($this->ar))
		{
			$this->ar = array();
		}

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			if ($o->type == $type)
			{
				$count += $o->get_count();
			}
		}

		return $count;
	}

	function get_items_cost($type = "items")
	{
		$cost = 0;

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			if ($o->type == $type)
			{
				$cost += $o->get_cost();
			}
		}

		return $cost;
	}

	function get_items_weight($type = "items")
	{
		$weight = 0;

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			if ($o->type == $type && !empty($o->weight))
			{
				$weight += $o->weight * $o->count;
			}
		}

		return $weight;
	}

	function get_items_volume($type = "items")
	{
		$volume = 0;

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			if ($o->type == $type && !empty($o->volume))
			{
				$volume += $o->volume * $o->count;
			}
		}

		return $volume;
	}

	function get_total_items_cost()
	{
		$cost = 0;

		/**
		 * @var int $idx
		 * @var diCartItem $o
		 */
		foreach ($this->ar as $idx => $o)
		{
			$cost += $o->get_cost();
		}

		return $cost;
	}

	function get_ids_array($type = "items", $raw = true)
	{
		$a = array();

		foreach ($this->ar as $o)
		{
			if ($o->type == $type)
				$a[] = $raw ? $o->id : intval($o->id);
		}

		return $a;
	}

	function get_ids_for_query($type = "items")
	{
		return "'".join("','", $this->get_ids_array($type, false))."'";
	}

	function add($id, $type = "items", $count = 1)
	{
		$idx = $this->get_item_idx($id, $type);

		$this->create_cart_in_db();

		if ($idx === false)
		{
			if ($count)
			{
				$this->ar[] = new diCartItem($id, $type, $count, $this->id);
			}
		}
		else
		{
			if ($count)
			{
				$this->getByIdx($idx)->update($count);
			}
			else
			{
				$this->remove($id, $type);
			}
		}
	}

	function update($id, $type = "items", $count = 1)
	{
		if (gettype($id) == "object" && $id instanceof diCartItemModel)
		{
			$type = $id->getType();
			$count = $id->getQuantity();
			$id = $id->getComplexId();
		}

		$this->add($id, $type, $count);
	}

	function add_more($id, $type = "items", $count = 1)
	{
		$idx = $this->get_item_idx($id, $type);

		$this->create_cart_in_db();

		if ($idx === false)
		{
			if ($count)
			{
				$this->ar[] = new diCartItem($id, $type, $count, $this->id);
			}
		}
		else
		{
			if ($count)
			{
				$this->getByIdx($idx)->add($count);
			}

			if (!$this->getByIdx($idx)->get_count())
			{
				$this->remove($id, $type);
			}
		}
	}

	function remove($id, $type = "items")
	{
		$idx = $this->get_item_idx($id, $type);

		if ($idx !== false && $this->id)
		{
			$this->getDb()->delete("cart_items", "WHERE cart_id='$this->id' and id='{$this->getByIdx($idx)->cart_item_id}'");

			unset($this->ar[$idx]);
		}
	}

	function remove_by_type($type = "items")
	{
		/** @var diCartItem $o */
		foreach ($this->ar as $o)
		{
			if ($o->type == $type)
			{
				$this->remove($o->id, $o->type);
			}
		}
	}

	function remove_all()
	{
		$this->ar = array();

		if ($this->id)
		{
			$this->getDb()->delete("cart_items", "WHERE cart_id='$this->id'");
			$this->getDb()->delete("carts", $this->id);
		}
	}
}