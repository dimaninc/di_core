<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.01.2018
 * Time: 18:03
 */

namespace diCore\Database\Entity\Mongo;

use diCore\Database\Legacy\Mongo;
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

	public function addAliasToField($field, $alias = null)
	{
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

		if ($this->sqlParts['where'])
		{
			foreach ($this->sqlParts['where'] as $val)
			{
				switch ($val['operator'])
				{
					case '=':
						$filter[$val['field']] = $val['value'];
						break;

					case '>':
						$filter[$val['field']] = [
							'&gt;' => $val['value'],
						];
						break;

					case '<':
						$filter[$val['field']] = [
							'&lt;' => $val['value'],
						];
						break;
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
		var_dump($this->sqlParts['groupBy']);
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