<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 20:53
 */

namespace diCore\Database;

use diCore\Database\Legacy\Mongo;

class MongoConnection extends Connection
{
	const engine = Engine::MONGO;

	protected function connect()
	{
		$this->db = new Mongo([
			'host' => $this->getHost(),
			'port' => $this->getPort(),
			'username' => $this->getLogin(),
			'password' => $this->getPassword(),
			'dbname' => $this->getDatabase(),
		]);

		return $this;
	}
}