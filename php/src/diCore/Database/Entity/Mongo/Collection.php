<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.01.2018
 * Time: 18:03
 */

namespace diCore\Database\Entity\Mongo;

use diCore\Database\Legacy\Mongo;

class Collection extends \diCollection
{
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
}