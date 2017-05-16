<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.05.2017
 * Time: 15:37
 */

namespace diCore\Database\Legacy;


class Sqlite extends Pdo
{
	protected $driver = 'sqlite';

	protected function getDSN()
	{
		return $dsn = "{$this->driver}:{$this->dbname}";
	}

	public function getTableNames()
	{
		$ar = [];

		$tables = $this->q("SELECT * FROM sqlite_master WHERE type = 'table'");
		while ($table = $this->fetch_array($tables))
		{
			$ar[] = $table['name'];
		}

		return $ar;
	}
}