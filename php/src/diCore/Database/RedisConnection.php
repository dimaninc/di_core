<?php

namespace diCore\Database;

/**
 * @method \Redis getDb
 */
class RedisConnection extends Connection
{
    const engine = Engine::REDIS;

    protected function connect(ConnectionData $connData)
    {
        $this->db = new \Redis([
            'host' => $connData->getHost(),
            'port' => $connData->getPort(),
        ]);

        return $this;
    }
}
