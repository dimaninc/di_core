<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.05.2017
 * Time: 22:31
 */

namespace diCore\Database;

use diCore\Base\CMS;
use diCore\Data\Environment;
use diCore\Helper\ArrayHelper;

abstract class Connection
{
    const DEFAULT_NAME = 'default';

    /**
     * @var array Hash array of connections 'name' => [properties]
     */
    private static $connections = [];

    const engine = null;
    /**
     * Has tables/collections inside
     */
    const consists_of_tables = true;
    /**
     * Is AFTER command supported in SQL
     */
    const alter_after_supported = false;

    /** @var ConnectionData */
    protected $data;

    /** @var string */
    private $name;

    /**
     * Possible ConnectionData records (used for dev env, with different passwords)
     * @var ConnectionData[]
     */
    protected $dataVariants = [];

    /** @var \diDB */
    protected $db;

    public function __construct($connData, $name = null)
    {
        $this->name = $name;

        $this->parseConnData($connData)->connectAll();
    }

    public static function getEngine()
    {
        return static::engine;
    }

    public static function isNoSql(): bool
    {
        return Engine::isNoSql(static::getEngine());
    }

    public static function isKeyValue(): bool
    {
        return Engine::isKeyValue(static::getEngine());
    }

    public static function isRelational(): bool
    {
        return Engine::isRelational(static::getEngine());
    }

    /**
     * @param string|array $connData
     * @param int|null $engine
     * @param string|null $name
     */
    public static function open($connData, $engine = null, $name = null)
    {
        $name = $name ?? static::DEFAULT_NAME;

        // connection string support
        if (is_string($connData)) {
            return static::openByDsn($connData, $name);
        }

        $className = self::getChildClassName($engine ?? Engine::MYSQL);
        /** @var Connection $conn */
        $conn = new $className($connData, $name);

        self::add($name, $conn);

        return $conn;
    }

    public static function openByDsn(string $dsn, string $name)
    {
        if (empty($dsn)) {
            throw new \InvalidArgumentException('DSN must be provided');
        }

        $connData = [
            'host' => null,
            'port' => null,
            'login' => null,
            'password' => null,
            'database' => null,
        ];

        $parts = parse_url($dsn);
        if ($parts === false) {
            throw new \InvalidArgumentException('DSN can not be parsed');
        }

        $engine = $parts['scheme'] ?? null;
        $connData['host'] = $parts['host'] ?? null;
        $connData['port'] = isset($parts['port']) ? (int) $parts['port'] : null;
        $connData['login'] = $parts['user'] ?? null;
        $connData['password'] = $parts['pass'] ?? null;

        if (isset($parts['path'])) {
            $path = ltrim($parts['path'], '/');

            if ($path) {
                $dbParts = explode('?', $path, 2);
                $connData['database'] = $dbParts[0] ?: null;
            }
        }

        $className = self::getChildClassName($engine);
        /** @var Connection $conn */
        $conn = new $className($connData, $name);

        self::add($name, $conn);

        return $conn;
    }

    /**
     * @param $name
     * @return Connection|RedisConnection
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

    public static function localMysqlConnData($database): array
    {
        return ConnectionData::localMysqlConnData($database);
    }

    public static function localPostgresConnData($database): array
    {
        return ConnectionData::localPostgresConnData($database);
    }

    public static function localMongoConnData($database): array
    {
        return ConnectionData::localMongoConnData($database);
    }

    public static function localRedisConnData(): array
    {
        return ConnectionData::localRedisConnData();
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
        $engine = Engine::normalizeId($engine);

        if (!($name = Engine::name($engine))) {
            throw new \diRuntimeException("Unknown engine $engine");
        }

        return \diLib::parentNamespace(self::class) .
            '\\' .
            ucfirst(camelize("{$name}_connection"));
    }

    /**
     * @return $this
     */
    abstract protected function connect(ConnectionData $connData);

    private function connectAll()
    {
        $errors = [];

        foreach ($this->dataVariants as $connData) {
            try {
                $this->connect($connData);

                $this->data = $connData;

                break;
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                // do nothing, just go to the next connection data
            }
        }

        if (!$this->data) {
            $message = "No suitable database connection data for '$this->name'";

            if (Environment::getInitiating() || CMS::debugMode()) {
                $message .= ', errors: ' . join('; ', $errors);
            }

            throw (new \diDatabaseException($message))->addMetadata([
                'className' => static::class,
                'connData' => $this->data,
            ]);
        }

        return $this;
    }

    protected function parseConnData($connData): static
    {
        $allData = ArrayHelper::isAssoc($connData) ? [$connData] : $connData;

        foreach ($allData as $data) {
            $this->addConnData($data);
        }

        return $this;
    }

    protected function addConnData($connData): static
    {
        $this->dataVariants[] = new ConnectionData($connData);

        return $this;
    }

    public function getConnData()
    {
        return $this->data;
    }

    public function getTableNames()
    {
        if (!static::consistsOfTables()) {
            return [];
        }

        return $this->getDb()->getTableNames();
    }

    public function checkHealth()
    {
        return !!$this->getTableNames();
    }

    public static function consistsOfTables()
    {
        return static::consists_of_tables;
    }

    public static function isAlterAfterSupported()
    {
        return static::alter_after_supported;
    }

    public static function isMongo()
    {
        return static::engine === Engine::MONGO;
    }

    public static function isPostgres()
    {
        return static::engine === Engine::POSTGRESQL;
    }

    public static function isMysql()
    {
        return static::engine === Engine::MYSQL;
    }

    public static function isBooleanTypeSupported()
    {
        return false;
    }
}
