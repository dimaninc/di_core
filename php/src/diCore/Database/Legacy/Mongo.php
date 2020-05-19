<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 20:53
 */

namespace diCore\Database\Legacy;

use diCore\Helper\ArrayHelper;
use MongoDB\Driver\Cursor;
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
	/** @var string|null */
	protected $lastInsertId = null;

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

		if ($this->getUsername()) {
			$s .= $this->getUsername() . ':' . $this->getPassword() . '@';
		}

		$s .= $this->getHost();

		if ($this->getPort()) {
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

	public function getCollectionResource($collectionName)
	{
		return $this->getLink()->selectCollection($collectionName);
	}

	public function insert($table, $fields_values = [])
	{
		$time1 = utime();

		$insertResult = $this->getCollectionResource($table)
			->insertOne($fields_values);
		/** @var \MongoDB\BSON\ObjectId $id */
		$id = $insertResult->getInsertedId();

		$time2 = utime();
		$this->execution_time += $time2 - $time1;
		$this->time_log('insert', $time2 - $time1);

		return $this->lastInsertId = (string)$id;
	}

	public static function convertDirection($direction)
	{
		switch (mb_strtolower($direction)) {
			case 'asc':
				$direction = 1;
				break;

			case 'desc':
				$direction = -1;
				break;
		}

		return $direction;
	}

	public function rs($table, $q_ending = "", $q_fields = "*")
	{
		if (is_array($q_ending)) {
			$ar = extend([
				'filter' => [],
				'sort' => [],
				'skip' => null,
				'limit' => null,
			], $q_ending);

			foreach ($ar['sort'] as $field => &$direction) {
				$direction = static::convertDirection($direction);
			}

			$options = array_filter($ar) ?: [];
			unset($options['filter']);

			/** @var Cursor $cursor */
			$cursor = $this->getCollectionResource($table)->find($ar['filter'], $options);

			return $cursor;
		} else {
			throw new \Exception('Mongo can not execute queries, array filter needed');
		}
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
	 * @param $rs Cursor
	 * @return object
	 */
	protected function __fetch($rs)
	{
		return (object)$this->__fetch_array($rs);
	}

	/**
	 * @param $rs Cursor
	 * @return array
	 */
	protected function __fetch_array($rs)
	{
		return null;
	}

	protected function __count($options)
	{
		$options = extend([
			'collectionName' => null,
			'filters' => [],
		], $options);

		$options['filters'] = extend([
			'filter' => [],
			'sort' => [],
			'skip' => null,
			'limit' => null,
		], ArrayHelper::filterByKey($options['filters'], ['filter', 'skip', 'limit']));

		$filter = $options['filters']['filter'];
		$options['filters'] = array_filter($options['filters']) ?: [];
		unset($options['filters']['filter']);

		return $options['collectionName']
			? $this->getCollectionResource($options['collectionName'])->count($filter, $options['filters'])
			: null;
	}

	protected function __insert_id()
	{
		return $this->lastInsertId;
	}

	protected function __affected_rows()
	{
		return null;
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
		foreach ($this->getLink()->listCollections() as $col) {
			$ar[] = $col->getName();
		}

		return $ar;
	}

    public function getFields($table)
    {
        $fields = [];

        $ar = $this->ar($table) ?: [];
        foreach ($ar as $name => $value) {
            $fields[$name] = gettype($value);
        }

        return $fields;
    }
}