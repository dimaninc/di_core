<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.05.2015
 * Time: 22:27
 */

class diHierarchyTable
{
	/** @var int */
	protected $type;

	/**
	 * @deprecated
	 * @var string
	 */
	protected $table;

	/** @var diDB */
	protected $db;

	public function __construct($type = null)
	{
		global $db;

		if ($type)
		{
			if (isInteger($type))
			{
				$this->type = $type;
				$this->table = \diTypes::getTableByName($this->type);
			}
			else
			{
				$this->table = $type;
				$this->type = \diTypes::getId($this->table);
			}
		}
		else
		{
			if (!$this->type)
			{
				$this->type = \diTypes::getId($this->table);
			}

			if (!$this->table)
			{
				$this->table = \diTypes::getTableByName($this->type);
			}
		}

		$this->db = $db;
	}

	/** @deprecated  */
	public function getTable()
	{
		return $this->table;
	}

	public function getType()
	{
		return $this->type;
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

	/** @deprecated  */
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

	public function getParents($id)
	{
		$ar = [];
		$id0 = $id;

		while (
			($model = \diCollection::create($this->getType())->find((int)$id)->getFirstItem()) &&
			$model->exists()
		)
		{
			if ($id0 != $model->getId())
			{
				$ar[] = $model;
			}

			$id = $model->get('parent');

			if ($id <= 0)
			{
				break;
			}
		}

		return array_reverse($ar);
	}

	/** @deprecated  */
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

	public function getParentsByParentId($id)
	{
		$ar = [];

		while (
			($parentId = isset($model) ? $model->get('parent') : $id) &&
			$model = \diCollection::create($this->getType())->find($parentId)->getFirstItem()
		)
		{
			$ar[] = $model;

			if ($model->get('parent') > 0)
			{
				$id = $model->get('parent');
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

	protected function createModel($data = null, $options = [])
	{
		return \diModel::create($this->getType(), $data, $options);
	}

	public function getChildrenIdsAr($id, $ar = [], $order_by = 'order_num', $where_suffix = '')
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

	public function getChildren($id, $orderBy = 'order_num', $whereSuffix = '', $ar = [])
	{
		if ($whereSuffix && substr(trim($whereSuffix), 0, 4) != "and ")
		{
			$whereSuffix = " and $whereSuffix";
		}

		$rs = $this->getDb()->rs($this->getTable(), "WHERE parent='$id'{$whereSuffix} ORDER BY $orderBy ASC", "id");
		while ($a = $this->getDb()->fetch_array($rs))
		{
			$m = $this->createModel($a);
			$ar[] = $m;

			$ar = $this->getChildren($m->getId(), $orderBy, $whereSuffix, $ar);
		}

		return $ar;
	}

	public function getFirstChild($id, $orderBy = 'order_num', $whereSuffix = '')
	{
		$children = $this->getChildren($id, [], $orderBy, $whereSuffix);

		return count($children)
			? $children[0]
			: $this->createModel();
	}
}
