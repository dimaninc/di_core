<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.05.2017
 * Time: 15:40
 */

namespace diCore\Database\Legacy;

use diCore\Data\Config;
use diCore\Helper\ArrayHelper;

abstract class Pdo extends \diDB
{
	protected $charset = 'utf8';

	/** @var \PDO */
	protected $link;

	/** @var string */
	protected $driver;

	/** @var  \PDOStatement */
	protected $lastResult;

	/** @var bool  */
	protected $ssl = false;
	/** @var string  */
    protected $sslCert = '';
    /** @var string  */
    protected $sslKey = '';

    protected function populateBasicSettings($settings)
    {
        $this->ssl = ArrayHelper::get($settings, 'ssl', false);
        $this->sslCert = ArrayHelper::get($settings, 'cert', '');
        $this->sslKey = ArrayHelper::get($settings, 'key', '');

        return parent::populateBasicSettings($settings);
    }

    protected function __connect()
	{
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

		$time1 = utime();

		try {
			$this->link = new \PDO($this->getDSN(), $this->username, $this->password, $options);
		} catch (\PDOException $e) {
            $message = "Pdo: Unable to connect to host $this->host: " . $e->getMessage();

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

		if (
		    Config::isInitiating() &&
            $this->databaseCreationAllowed()
        ) {
			$this->__q($this->getCreateDatabaseQuery());
		}

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("connect", $time2 - $time1);

		return true;
	}

	protected function databaseCreationAllowed()
    {
        return true;
    }

	protected function getDSN()
	{
		return $dsn = "{$this->driver}:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
	}

	protected function __close()
	{
		return true;
	}

	protected function __error()
	{
	    if (!$this->link) {
	        return null;
        }

		$info = json_encode($this->link->errorInfo());
		
		return $info;
	}

	protected function __q($q)
	{
		try {
			$this->lastResult = $this->link->query($q);

			try {
                $this->lastInsertId = $this->__insert_id() ?: $this->lastInsertId;
            } catch (\PDOException $e) {
                $this->lastInsertId = null;
            }

            return $this->lastResult;
		} catch (\PDOException $e) {
			return $this->_log("Unable to execute query `{$q}`");
		}
	}

	protected function __rq($q)
	{
		return $this->__q($q);
	}

	protected function __mq($q)
	{
		return $this->__q($q);
	}

	protected function __mq_flush()
	{
		return true;
	}

	protected function __reset(&$rs)
	{
		throw new \Exception('PDO doesn\'t support reset of cursor');
	}

	/**
	 * @param $rs \PDOStatement
	 * @return object
	 */
	protected function __fetch($rs)
	{
		return $rs->fetchObject();
	}

	/**
	 * @param $rs \PDOStatement
	 * @return array
	 */
	protected function __fetch_array($rs)
	{
		return $rs->fetch();
	}

	/**
	 * @param $rs \PDOStatement
	 * @return integer
	 */
	protected function __count($rs)
	{
		return $rs
			? $rs->rowCount()
			: 0;
	}

	protected function __insert_id()
	{
		return $this->link->lastInsertId();
	}

	protected function __affected_rows()
	{
		return $this->lastResult
			? $this->lastResult->rowCount()
			: 0;
	}

	public function escape_string($s, $binary = false)
	{
		$s = $this->link->quote($s, $binary ? \PDO::PARAM_LOB : \PDO::PARAM_STR);

		if (strlen($s) >= 2) {
			$s = substr($s, 1, strlen($s) - 2);
		}

		return $s;
	}

	protected function __set_charset($name)
	{
		return true;
	}

	protected function __get_charset()
	{
		return 'utf8';
	}
}
