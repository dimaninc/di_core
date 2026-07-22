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
        // Core keys win; everything else (timeouts, client options — see
        // doc/mongo-timeouts.md) is passed through for Mongo to pick up.
        $this->db = new Mongo(
            [
                'host' => $connData->getHost(),
                'port' => $connData->getPort(),
                'username' => $connData->getLogin(),
                'password' => $connData->getPassword(),
                'dbname' => $connData->getDatabase(),
                'connection' => $this,
            ] + $connData->getOtherOptions()
        );

        return $this;
    }
}
