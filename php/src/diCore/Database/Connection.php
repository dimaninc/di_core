<?php

namespace diCore\Database;

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

	protected $host;
	protected $port;
	protected $login;
	protected $password;
	protected $database;

	/** @var \diDB */
	protected $db;

	public function __construct($connData)
	{
		$this
			->parseConnData($connData)
			->connect();
	}

	/**
	 * @param string|array $connData
	 * @param int $engine
	 * @param string $name
	 * @return Connection
	 */
	public static function open($connData, $engine = Engine::MYSQL, $name = self::DEFAULT_NAME)
	{
		if (self::exists($name))
		{
			throw new \diRuntimeException("Connection '$name' already exists");
		}

		$className = self::getChildClassName($engine);
		/** @var Connection $conn */
		$conn = new $className($connData);

		self::add($name, $conn);

		return $conn;
	}

	/**
	 * @param $name
	 * @return Connection
	 */
	public static function get($name = self::DEFAULT_NAME)
	{
		if (!self::exists($name))
		{
			throw new \diRuntimeException("Connection '$name' not found");
		}

		return self::$connections[$name];
	}

	public static function exists($name)
	{
		return isset(self::$connections[$name]);
	}

	private static function add($name, Connection $conn)
	{
		self::$connections[$name] = $conn;
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
		if (!$name = Engine::name($engine))
		{
			throw new \diRuntimeException('Unknown engine ' . $engine);
		}

		return \diLib::parentNamespace(self::class) . '\\' . ucfirst(camelize($name . '_connection'));
	}

	/**
	 * @return $this
	 */
	abstract protected function connect();

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
}