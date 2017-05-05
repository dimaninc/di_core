<?php

namespace diCore\Database;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.05.2017
 * Time: 22:36
 */
class MysqlConnection extends Connection
{
	const engine = Engine::MYSQL;

	protected function connect()
	{
		$this->db = new \diMYSQLi($this->getHost(), $this->getLogin(), $this->getPassword(), $this->getDatabase());

		return $this;
	}
}