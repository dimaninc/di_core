<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.01.2018
 * Time: 18:03
 */

namespace diCore\Database\Entity\Mongo;

use diCore\Database\Legacy\Mongo;
use diCore\Helper\ArrayHelper;
use MongoDB\Driver\Cursor;

/**
 * Class Collection
 * @package diCore\Database\Entity\Mongo
 *
 * @method Mongo getDb
 */
class Collection extends \diCollection
{
	/** @var  Cursor */
	protected $cursor;

	protected static $operators = [
		'=' => '$eq',
		'!=' => '$ne',
		'in' => '$in',
		'not in' => '$nin',
		'>' => '$gt',
		'>=' => '$gte',
		'<' => '$lt',
		'<=' => '$lte',
	];

	public function addAliasToTable($table, $alias = null)
	{
		return $table;
	}

	public function addAliasToField($field, $alias = null)
	{
		if ($field == 'id')
		{
			/** @var Model $m */
			$m = $this->getNewEmptyItem();

			return $m->getIdFieldName();
		}

		return $field;
	}

	public function getFullQuery()
	{
		$ar = [
			'filter' => $this->getQueryWhere(),
			//todo: 'group' => $this->getQueryGroupBy(),
			'sort' => $this->getQueryOrderBy(),
			'skip' => $this->getStartFrom(),
			'limit' => $this->getPageSize(),
		];

		return $ar;
	}

	protected function getQueryWhere()
	{
		$filter = [];

		/** @var Model $modelClass */
		$modelClass = static::getModelClass();

		if ($this->sqlParts['where'])
		{
			foreach ($this->sqlParts['where'] as $val)
			{
				$val['value'] = $modelClass::tuneFieldValueByTypeBeforeDb($val['field'], $val['value']);

				$existingFilter = isset($filter[$val['field']])
					? $filter[$val['field']]
					: [];

				$val['operator'] = mb_strtolower($val['operator']);

				if (isset(self::$operators[$val['operator']]))
				{
					$operator = self::$operators[$val['operator']];

					$newFilter = [
						$operator => $val['value'],
					];
				}
				elseif ($val['operator'] == 'between')
				{
					if (is_array($val['value']) && count($val['value']) == 2)
					{
						$newFilter = [
							'$gte' => ArrayHelper::getValue($val['value'], 0),
							'$lte' => ArrayHelper::getValue($val['value'], 1),
						];
					}
					else
					{
						throw new \Exception('Operator "' . $val['operator'] .
							'" supports only array with 2 values, but given: ' . print_r($val['value'], true));
					}
				}
				else
				{
					throw new \Exception('Operator "' . $val['operator'] . '" not supported yet');
				}

				$existingFilter = array_merge($existingFilter, $newFilter);

				if ($existingFilter)
				{
					$filter[$val['field']] = $existingFilter;
				}
			}
		}

		return $filter;
	}

	protected function getQueryOrderBy()
	{
		$sort = [];

		if ($this->sqlParts['orderBy'])
		{
			foreach ($this->sqlParts['orderBy'] as $val)
			{
				$sort[$val['field']] = Mongo::convertDirection($val['direction']);
			}
		}

		return $sort;
	}

	protected function getQueryGroupBy()
	{
		throw new \Exception('Group by is not implemented for Mongo yet: ' . print_r($this->sqlParts['groupBy'], true));
	}

	protected function getDbRecords()
	{
		$this->cursor = parent::getDbRecords();

		return $this->cursor;
	}

	public function count()
	{
		if ($this->count === null)
		{
			$this->realCount = $this->count =
				$this->getDb()->count([
					'collectionName' => $this->getQueryTable(),
					'filters' => $this->getFullQuery(),
				]);
		}

		if ($this->pageSize && $this->count > $this->pageSize)
		{
			$this->count = $this->pageSize;
		}

		return $this->count;
	}
}