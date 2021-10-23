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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByOrigFn($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicTn($value, $operator = null)
 * @method $this filterByPicTnT($value, $operator = null)
 * @method $this filterByPicTnW($value, $operator = null)
 * @method $this filterByPicTnH($value, $operator = null)
 * @method $this filterByPicTn2T($value, $operator = null)
 * @method $this filterByPicTn2W($value, $operator = null)
 * @method $this filterByPicTn2H($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByByDefault($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByOrigFn($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicTn($direction = null)
 * @method $this orderByPicTnT($direction = null)
 * @method $this orderByPicTnW($direction = null)
 * @method $this orderByPicTnH($direction = null)
 * @method $this orderByPicTn2T($direction = null)
 * @method $this orderByPicTn2W($direction = null)
 * @method $this orderByPicTn2H($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByByDefault($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByOrderNum($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectOrigFn()
 * @method $this selectPic()
 * @method $this selectPicT()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicTn()
 * @method $this selectPicTnT()
 * @method $this selectPicTnW()
 * @method $this selectPicTnH()
 * @method $this selectPicTn2T()
 * @method $this selectPicTn2W()
 * @method $this selectPicTn2H()
 * @method $this selectDate()
 * @method $this selectByDefault()
 * @method $this selectVisible()
 * @method $this selectOrderNum()
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

		if ($field !== null) {
			$col
				->filterByTargetField($field);
		}

		return $col;
	}

    /**
     * @param $table
     * @param $id
     * @param $field
     * @return Collection
     * @throws \Exception
     */
    public static function createByTargetTable($table, $field = null)
    {
        /** @var Collection $col */
        $col = static::create(static::type);
        $col
            ->filterByTargetTable($table);

        if ($field !== null) {
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