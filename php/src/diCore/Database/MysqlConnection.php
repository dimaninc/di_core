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
    const alter_after_supported = true;

    protected function connect(ConnectionData $connData)
    {
        $host = $connData->getHost();

        if ($connData->getPort()) {
            $host .= ':' . $connData->getPort();
        }

        $this->db = new \diMYSQLi(
            $host,
            $connData->getLogin(),
            $connData->getPassword(),
            $connData->getDatabase(),
            $this
        );

        return $this;
    }
}
