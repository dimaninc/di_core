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
        $this->db = new \diMYSQLi(
            $connData->getHost(),
            $connData->getLogin(),
            $connData->getPassword(),
            $connData->getDatabase(),
            $this
        );

        return $this;
    }
}
