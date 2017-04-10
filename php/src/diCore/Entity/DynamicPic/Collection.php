<?php
/**
 * Created by \diModelsManager
 * Date: 10.04.2017
 * Time: 18:54
 */

namespace diCore\Entity\DynamicPic;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByOrigFn($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicT($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByPicTn($value, $operator = null)
 * @method Collection filterByPicTnT($value, $operator = null)
 * @method Collection filterByPicTnW($value, $operator = null)
 * @method Collection filterByPicTnH($value, $operator = null)
 * @method Collection filterByPicTn2T($value, $operator = null)
 * @method Collection filterByPicTn2W($value, $operator = null)
 * @method Collection filterByPicTn2H($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByByDefault($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByOrigFn($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicT($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByPicTn($direction = null)
 * @method Collection orderByPicTnT($direction = null)
 * @method Collection orderByPicTnW($direction = null)
 * @method Collection orderByPicTnH($direction = null)
 * @method Collection orderByPicTn2T($direction = null)
 * @method Collection orderByPicTn2W($direction = null)
 * @method Collection orderByPicTn2H($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByByDefault($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectOrigFn()
 * @method Collection selectPic()
 * @method Collection selectPicT()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectPicTn()
 * @method Collection selectPicTnT()
 * @method Collection selectPicTnW()
 * @method Collection selectPicTnH()
 * @method Collection selectPicTn2T()
 * @method Collection selectPicTn2W()
 * @method Collection selectPicTn2H()
 * @method Collection selectDate()
 * @method Collection selectByDefault()
 * @method Collection selectVisible()
 * @method Collection selectOrderNum()
 */
class Collection extends \diCollection
{
	const type = \diTypes::dynamic_pic;
	protected $table = 'dipics';
	protected $modelType = 'dynamic_pic';

	/**
	 * @param $table
	 * @param $id
	 * @param $field
	 * @return Collection
	 * @throws \Exception
	 */
	public static function createByTarget($table, $id, $field = null)
	{
		/** @var Collection $col */
		$col = static::create(static::type);
		$col
			->filterByTargetTable($table)
			->filterByTargetId((int)$id);

		if ($field !== null)
		{
			$col
				->filterByTargetField($field);
		}

		return $col;
	}

	/**
	 * @param $value
	 * @param null $operator
	 * @return Collection
	 */
	public function filterByTargetTable($value, $operator = null)
	{
		return $operator !== null
			? $this->filterBy('_table', $operator, $value)
			: $this->filterBy('_table', $value);
	}

	/**
	 * @param $value
	 * @param null $operator
	 * @return Collection
	 */
	public function filterByTargetField($value, $operator = null)
	{
		return $operator !== null
			? $this->filterBy('_field', $operator, $value)
			: $this->filterBy('_field', $value);
	}

	/**
	 * @param $value
	 * @param null $operator
	 * @return Collection
	 */
	public function filterByTargetId($value, $operator = null)
	{
		return $operator !== null
			? $this->filterBy('_id', $operator, $value)
			: $this->filterBy('_id', $value);
	}

	/**
	 * @param null $direction
	 * @return $this
	 * @throws \Exception
	 */
	public function orderByTargetTable($direction = null)
	{
		return $this->orderBy('_table', $direction);
	}

	/**
	 * @param null $direction
	 * @return $this
	 * @throws \Exception
	 */
	public function orderByTargetField($direction = null)
	{
		return $this->orderBy('_field', $direction);
	}

	/**
	 * @param null $direction
	 * @return $this
	 * @throws \Exception
	 */
	public function orderByTargetId($direction = null)
	{
		return $this->orderBy('_id', $direction);
	}

	/**
	 * @return $this
	 */
	public function selectTargetTable()
	{
		return $this->select('_table', true);
	}

	/**
	 * @return $this
	 */
	public function selectTargetField()
	{
		return $this->select('_field', true);
	}

	/**
	 * @return $this
	 */
	public function selectTargetId()
	{
		return $this->select('_id', true);
	}
}