<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.05.2017
 * Time: 17:27
 */

namespace diCore\Database;

use diCore\Database\Legacy\Sqlite;

class SqliteConnection extends Connection
{
    const engine = Engine::SQLITE;

    protected function connect(ConnectionData $connData)
    {
        $this->db = new Sqlite(
            null,
            null,
            null,
            $connData->getDatabase(),
            $this
        );

        return $this;
    }
}
