<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2020
 * Time: 16:19
 */

namespace diCore\Database;

class ConnectionData
{
    protected $host;
    protected $port;
    protected $login;
    protected $password;
    protected $database;

    public function __construct($connData)
    {
        $this
            ->parseConnData($connData);
    }

    protected function parseConnData($connData)
    {
        $connData = extend([
            'host' => null,
            'port' => null,
            'login' => null,
            'username' => null,
            'password' => null,
            'database' => null,
            'dbname' => null,
        ], $connData);

        $this
            ->setHost($connData['host'])
            ->setPort($connData['port'])
            ->setLogin($connData['login'] ?: $connData['username'])
            ->setPassword($connData['password'])
            ->setDatabase($connData['database'] ?: $connData['dbname']);

        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param mixed $login
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param mixed $database
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;

        return $this;
    }

    public static function localMysqlConnData($database)
    {
        $password = '11111111';

        return [
            [
                'host' => 'localhost',
                'login' => 'root',
                'password' => $password,
                'database' => $database,
            ],
            [
                'host' => 'localhost',
                'login' => 'root',
                'password' => '',
                'database' => $database,
            ],
            [
                'host' => 'localhost',
                'port' => 3306,
                'login' => 'root',
                'password' => $password,
                'database' => $database,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 3306,
                'login' => 'root',
                'password' => $password,
                'database' => $database,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 3306,
                'login' => 'root',
                'password' => '',
                'database' => $database,
            ],
        ];
    }
}