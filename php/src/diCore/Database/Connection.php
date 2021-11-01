<?php

namespace diCore\Database;

use diCore\Helper\ArrayHelper;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.05.2017
 * Time: 22:31
 */
abstract class Connection
{
	const DEFAULT_NAME = 'default';

	/**
	 * @var array Hash array of connections 'name' => [properties]
	 */
	private static $connections = [];

	const engine = null;

	/** @var ConnectionData */
	protected $data;

	/** @var string */
	private $name;

    /**
     * Possible ConnectionData records (used for dev env, with different passwords)
     * @var array
     */
	protected $allData = [];

	/** @var \diDB */
	protected $db;

	public function __construct($connData, $name = null)
	{
	    $this->name = $name;

		$this
			->parseConnData($connData)
			->connectAll();
	}

	public static function getEngine()
    {
        return static::engine;
    }

	/**
	 * @param string|array $connData
	 * @param int $engine
	 * @param string $name
	 * @return Connection
	 */
	public static function open($connData, $engine = Engine::MYSQL, $name = self::DEFAULT_NAME)
	{
		$className = self::getChildClassName($engine);
		/** @var Connection $conn */
		$conn = new $className($connData, $name);

		self::add($name, $conn);

		return $conn;
	}

	/**
	 * @param $name
	 * @return Connection
	 */
	public static function get($name = null)
	{
	    if ($name === null) {
	        $name = self::DEFAULT_NAME;
        }

		if (!self::exists($name)) {
			throw new \diRuntimeException("Connection '$name' not found");
		}

		return self::$connections[$name];
	}

	public static function exists($name)
	{
		return isset(self::$connections[$name]);
	}

	public static function getAll()
    {
        return self::$connections;
    }

	public static function localMysqlConnData($database)
    {
        return ConnectionData::localMysqlConnData($database);
    }

    public static function localPostgresConnData($database)
    {
        return ConnectionData::localPostgresConnData($database);
    }

	private static function add($name, Connection $conn)
	{
		if (is_array($name)) {
			foreach ($name as $n) {
				self::add($n, $conn);
			}
		} else {
			if (self::exists($name)) {
				throw new \diRuntimeException("Connection '$name' already exists");
			}

			self::$connections[$name] = $conn;
		}
	}

	/**
	 * @return \diDB
	 */
	public function getDb()
	{
		return $this->db;
	}

	public static function getChildClassName($engine)
	{
		if (!$name = Engine::name($engine)) {
			throw new \diRuntimeException('Unknown engine ' . $engine);
		}

		return \diLib::parentNamespace(self::class) . '\\' . ucfirst(camelize($name . '_connection'));
	}

	/**
	 * @return $this
	 */
	abstract protected function connect(ConnectionData $connData);

	private function connectAll()
    {
        /** @var ConnectionData $connData */
        foreach ($this->allData as $connData) {
            try {
                $this->connect($connData);

                $this->data = $connData;

                break;
            } catch (\Exception $e) {
                // do nothing, just go to the next connection data
            }
        }

        if (!$this->data) {
            throw new \diDatabaseException("No suitable database connection data for '$this->name'");
        }

        return $this;
    }

	protected function parseConnData($connData)
	{
	    $allData = ArrayHelper::isAssoc($connData)
            ? [$connData]
            : $connData;

	    foreach ($allData as $data) {
	        $this->addConnData($data);
        }

		return $this;
	}

	protected function addConnData($connData)
    {
        $this->allData[] = new ConnectionData($connData);

        return $this;
    }

	public function getTableNames()
    {
        return $this->getDb()->getTableNames();
    }

    public static function isMongo()
    {
        return static::engine === Engine::MONGO;
    }
}