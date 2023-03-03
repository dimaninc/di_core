<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 20:53
 */

namespace diCore\Database;

use diCore\Database\Legacy\Mongo;

/**
 * Class MongoConnection
 * @package diCore\Database
 *
 * @method Mongo getDb
 */
class MongoConnection extends Connection
{
	const engine = Engine::MONGO;

	protected function connect(ConnectionData $connData)
	{
		$this->db = new Mongo([
			'host' => $connData->getHost(),
			'port' => $connData->getPort(),
			'username' => $connData->getLogin(),
			'password' => $connData->getPassword(),
			'dbname' => $connData->getDatabase(),
            'connection' => $this,
		]);

		return $this;
	}
}