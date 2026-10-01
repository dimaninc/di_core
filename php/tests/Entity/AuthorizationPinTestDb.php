<?php

namespace diCore\Tests\Entity;

use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Entity\AuthorizationPin\FailureLog;
use PHPUnit\Framework\TestCase;

/**
 * Live server for the authorization pin tests: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db
 * or mysql://…. Both tables are recreated there from the core dumps once per process.
 * Without the variable the live tests are skipped.
 *
 * The models use the 'default' connection, which a consumer's bootstrap has usually
 * opened already, so `open()` puts the test server in its place and `close()` puts
 * the consumer's back – the rest of the suite must not run against the test server.
 */
class AuthorizationPinTestDb
{
    /** @var Connection|null|false false – no DSN */
    private static $conn = null;
    /** @var Connection|null the consumer's default while ours is in place */
    private static $replaced = null;
    private static $active = false;

    /**
     * Call from setUpBeforeClass(); pair with close() in tearDownAfterClass().
     */
    public static function open(): bool
    {
        if (self::$conn === null) {
            self::$conn = self::connect() ?: false;
        }

        if (!self::$conn || self::$active) {
            return (bool) self::$conn;
        }

        $all = self::registry();
        self::$replaced = $all[Connection::DEFAULT_NAME] ?? null;
        $all[Connection::DEFAULT_NAME] = self::$conn;
        self::registry($all);
        self::$active = true;

        return true;
    }

    public static function close(): void
    {
        if (!self::$active) {
            return;
        }

        $all = self::registry();
        unset($all[Connection::DEFAULT_NAME]);

        if (self::$replaced) {
            $all[Connection::DEFAULT_NAME] = self::$replaced;
        }

        self::registry($all);
        self::$replaced = null;
        self::$active = false;
    }

    public static function clear(): void
    {
        if (self::$active) {
            $db = self::db();
            $db->q('DELETE FROM authorization_pin');
            $db->q('DELETE FROM ' . FailureLog::TABLE);
        }
    }

    public static function need(TestCase $test): void
    {
        if (!self::$active) {
            $test->markTestSkipped(
                'Set DI_CORE_TEST_DB to a postgresql:// or mysql:// DSN'
            );
        }
    }

    public static function db(): \diDB
    {
        return Connection::get()->getDb();
    }

    public static function count(string $table, string $where = '1 = 1'): int
    {
        $db = self::db();
        $row = $db->fetch_array(
            $db->q("SELECT COUNT(*) AS c FROM $table WHERE $where")
        );

        return (int) ($row['c'] ?? 0);
    }

    public static function age(string $table, string $where, int $seconds): void
    {
        self::db()->q(
            "UPDATE $table SET created_at = '" .
                \diDateTime::sqlFormat(time() - $seconds) .
                "' WHERE $where"
        );
    }

    /**
     * For a forked child: a connection of its own as 'default'. The parent's sockets
     * are inherited – the child must never use or close them (end it with SIGKILL).
     */
    public static function reconnectInChild(): void
    {
        $conn = self::openDsn('di_core_pin_child');
        $all = self::registry();
        unset($all['di_core_pin_child']);
        $all[Connection::DEFAULT_NAME] = $conn;
        self::registry($all);
    }

    /**
     * A second connection to the test server (not registered), e.g. to hold a lock.
     */
    public static function secondConnection(): Connection
    {
        $conn = self::openDsn('di_core_pin_second');
        $all = self::registry();
        unset($all['di_core_pin_second']);
        self::registry($all);

        return $conn;
    }

    /**
     * @return Connection|null
     */
    private static function connect()
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn) {
            return null;
        }

        // Opened under a scratch name, then unregistered: open() can't replace a name.
        $name = 'di_core_pin_test';
        $conn = self::openDsn($name);
        $all = self::registry();
        unset($all[$name]);
        self::registry($all);

        $db = $conn->getDb();
        $folder =
            __DIR__ . '/../../../sql/' . ($conn::isPostgres() ? 'postgres/' : '');

        foreach (['authorization_pin', FailureLog::TABLE] as $table) {
            $db->q('DROP TABLE IF EXISTS ' . $table);

            foreach (explode(';', file_get_contents("$folder$table.sql")) as $sql) {
                if (trim($sql)) {
                    $db->q($sql);
                }
            }
        }

        return $conn;
    }

    private static function openDsn(string $name): Connection
    {
        // Parsed here: Engine::normalizeId() does not resolve the "mysql"/"postgresql" schemes.
        $p = parse_url(getenv('DI_CORE_TEST_DB'));

        return Connection::open(
            [
                'host' => $p['host'],
                'port' => $p['port'] ?? null,
                'login' => rawurldecode($p['user'] ?? ''),
                'password' => rawurldecode($p['pass'] ?? ''),
                'database' => ltrim($p['path'] ?? '', '/'),
            ],
            $p['scheme'] === 'mysql' ? Engine::MYSQL : Engine::POSTGRESQL,
            $name
        );
    }

    private static function registry(?array $value = null): array
    {
        $property = new \ReflectionProperty(Connection::class, 'connections');
        $property->setAccessible(true);

        if ($value !== null) {
            $property->setValue(null, $value);
        }

        return $property->getValue();
    }
}
