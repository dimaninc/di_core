<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 20:53
 */

namespace diCore\Database\Legacy;
use MongoDB\Model\CollectionInfo;

/**
 * Class Mongo
 * @package diCore\Database\Legacy
 *
 * @method \MongoDB\Database getLink
 */
class Mongo extends \diDB
{
	const CHARSET_INIT_NEEDED = false;
	const DEFAULT_PORT = 27017;

	/**
	 * @var \MongoDB\Client
	 */
	protected $mongo;

	protected function __connect()
	{
		$time1 = utime();

		$this->mongo = new \MongoDB\Client($this->getServerConnectionString());
		$this->link = $this->mongo->selectDatabase($this->getDatabase());

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("connect", $time2 - $time1);

		return true;
	}

	protected function getServerConnectionString()
	{
		$s = 'mongodb://';

		if ($this->getUsername())
		{
			$s .= $this->getUsername() . ':' . $this->getPassword() . '@';
		}

		$s .= $this->getHost();

		if ($this->getPort())
		{
			$s .= ':' . $this->getPort();
		}

		$s .= '/';

		/*
		if ($this->getDatabase())
		{
			$s .= $this->getDatabase();
		}
		*/

		return $s;
	}

	protected function getCollectionResource($collectionName)
	{
		return $this->getLink()->selectCollection($collectionName);
	}

	public function insert($table, $fields_values = [])
	{
		$time1 = utime();

		$insertResult = $this->getCollectionResource($table)
			->insertOne($fields_values);
		$id = $insertResult->getInsertedId();

		$time2 = utime();
		$this->execution_time += $time2 - $time1;
		$this->time_log('insert', $time2 - $time1);

		return $id;
	}

	protected function __close()
	{
		return true;
	}

	protected function __error()
	{
		return null;
	}

	protected function __q($q)
	{
		return null;
	}

	protected function __rq($q)
	{
		return null;
	}

	protected function __mq($q)
	{
		return null;
	}

	protected function __mq_flush()
	{
		return true;
	}

	protected function __reset(&$rs)
	{
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

	public function escape_string($s)
	{
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

	public function getTableNames()
	{
		$ar = [];

		/** @var CollectionInfo $col */
		foreach ($this->getLink()->listCollections() as $col)
		{
			$ar[] = $col->getName();
		}

		return $ar;
	}
}