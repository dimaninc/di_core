<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2020
 * Time: 16:19
 */

namespace diCore\Database;

use diCore\Helper\ArrayHelper;

class ConnectionData
{
    protected $host;
    protected $port;
    protected $login;
    protected $password;
    protected $database;
    protected $ssl;
    protected $sslCert;
    protected $sslKey;

    public function __construct($connData)
    {
        $this->parseConnData($connData);
    }

    protected function parseConnData($connData)
    {
        $this->setHost(ArrayHelper::get($connData, 'host'))
            ->setPort(ArrayHelper::get($connData, 'port'))
            ->setLogin(
                ArrayHelper::get($connData, 'login') ?:
                ArrayHelper::get($connData, 'username')
            )
            ->setPassword(ArrayHelper::get($connData, 'password'))
            ->setDatabase(
                ArrayHelper::get($connData, 'database') ?:
                ArrayHelper::get($connData, 'dbname')
            )
            ->setSsl(ArrayHelper::get($connData, 'ssl'))
            ->setSslCert(ArrayHelper::get($connData, 'cert'))
            ->setSslKey(ArrayHelper::get($connData, 'key'));

        return $this;
    }

    public function get()
    {
        return array_filter([
            'host' => $this->getHost(),
            'username' => $this->getLogin(),
            'password' => $this->getPassword(),
            'dbname' => $this->getDatabase(),
            'port' => $this->getPort(),
            'ssl' => $this->getSsl(),
            'cert' => $this->getSslCert(),
            'key' => $this->getSslKey(),
        ]);
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
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param string $login
     * @return $this
     */
    public function setLogin($login)
    {
        $this->login = $login;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param string $database
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;

        return $this;
    }

    /**
     * @return bool
     */
    public function getSsl()
    {
        return $this->ssl;
    }

    /**
     * @param bool $ssl
     * @return $this
     */
    public function setSsl($ssl)
    {
        $this->ssl = $ssl;

        return $this;
    }

    /**
     * @return string
     */
    public function getSslCert()
    {
        return $this->sslCert;
    }

    /**
     * @param string $sslCert
     * @return $this
     */
    public function setSslCert($sslCert)
    {
        $this->sslCert = $sslCert;

        return $this;
    }

    /**
     * @return string
     */
    public function getSslKey()
    {
        return $this->sslKey;
    }

    /**
     * @param string $sslKey
     * @return $this
     */
    public function setSslKey($sslKey)
    {
        $this->sslKey = $sslKey;

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
            // docker variant
            [
                'host' => 'db',
                'port' => 3306,
                'login' => 'root',
                'password' => $password,
                'database' => $database,
            ],
        ];
    }

    public static function localPostgresConnData($database)
    {
        $password = 'postgres';

        return [
            [
                'host' => 'localhost',
                'port' => 5432,
                'login' => 'postgres',
                'password' => $password,
                'database' => $database,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 5432,
                'login' => 'postgres',
                'password' => $password,
                'database' => $database,
            ],
            [
                'host' => '/var/run/postgresql',
                'port' => 5432,
                'login' => 'postgres',
                'password' => $password,
                'database' => $database,
            ],
        ];
    }
}
