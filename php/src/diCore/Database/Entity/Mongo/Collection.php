<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.01.2018
 * Time: 18:03
 */

namespace diCore\Database\Entity\Mongo;

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

	}

	protected function getQueryOrderBy()
	{

	}

	protected function getQueryGroupBy()
	{

	}
}