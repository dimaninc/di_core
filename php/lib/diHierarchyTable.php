<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.05.2015
 * Time: 22:27
 */

class diHierarchyTable
{
	/** @var string */
	protected $table;

	/** @var diDB */
	protected $db;

	public function __construct($table = null)
	{
		global $db;

		if ($table)
		{
			$this->table = $table;
		}

		$this->db = $db;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getChildLevelNum($id)
	{
		$r = $this->getDb()->r($this->getTable(), (int)$id);

		return $r ? $r->level_num + 1 : 0;
	}

	public function getParentsAr($id)
	{
		$ar = array();
		$id0 = $id;

		while ($r = $this->getDb()->r($this->getTable(), (int)$id))
		{
			if ($id0 != $r->id)
			{
				$ar[] = $r;
			}

			$id = $r->parent;

			if (!$id)
			{
				break;
			}
		}

		return array_reverse($ar);
	}

	public function getParentsArByParentId($id)
	{
		$ar = array();

		while (
			($parentId = isset($r) ? $r->parent : $id) &&
			$r = $this->getDb()->r($this->getTable(), $parentId)
		)
		{
			$ar[] = $r;

			if ($r->parent > 0)
			{
				$id = $r->parent;
			}
			else
			{
				break;
			}
		}

		return array_reverse($ar);
	}

	public function getParent0Id($id)
	{
		while ($r = $this->getDb()->r($this->getTable(), isset($r) ? $r->parent : $id))
		{
			if ($r->parent > 0)
			{
				$id = $r->parent;
			}
			else
			{
				break;
			}
		}

		return $id;
	}

	public function getChildrenIdsAr($id, $ar = array(), $order_by = "order_num", $where_suffix = "")
	{
		if ($where_suffix && substr(trim($where_suffix), 0, 4) != "and ")
		{
			$where_suffix = " and $where_suffix";
		}

		$rs = $this->getDb()->rs($this->getTable(), "WHERE parent='$id'{$where_suffix} ORDER BY $order_by ASC", "id");
		while ($r = $this->getDb()->fetch($rs))
		{
			$ar[] = $r->id;

			$ar = $this->getChildrenIdsAr($r->id, $ar);
		}

		return $ar;
	}
}
