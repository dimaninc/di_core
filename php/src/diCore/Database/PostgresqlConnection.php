<?php

namespace diCore\Database;

use diCore\Database\Legacy\Postgresql;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.05.2017
 * Time: 22:36
 */
class PostgresqlConnection extends Connection
{
    const engine = Engine::POSTGRESQL;

    protected function connect(ConnectionData $connData)
    {
        $this->db = new Postgresql(
            extend(
                [
                    'connection' => $this,
                ],
                $connData->get()
            )
        );

        return $this;
    }

    public static function isBooleanTypeSupported()
    {
        return true;
    }
}
