<?php

namespace diCore\Tests\Queue;

use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Queue\OpenTransaction;
use diCore\Queue\PgQueue;
use diCore\Queue\Policy;
use PHPUnit\Framework\TestCase;

/**
 * Live server for the queue tests: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db (the
 * queue is Postgres-only). The table TestSetup::TABLE and its notify trigger are recreated
 * there from the core dumps once per process; without the variable the tests are skipped.
 *
 * The connection is opened under a scratch name and unregistered: the queue gets its PDO and
 * diDB explicitly, the consumer's 'default' connection is never touched.
 */
trait QueueTestDb
{
    private static ?Connection $queueConn = null;

    private \PDO $pdo;
    private \diDB $db;
    private TestSetup $setup;
    private PgQueue $queue;

    protected function setUp(): void
    {
        if (!self::queueDsn()) {
            $this->markTestSkipped('Set DI_CORE_TEST_DB to a postgresql:// DSN');
        }

        // A consumer's host may lack them; a skip, not a fatal "undefined function".
        foreach (static::REQUIRED_FUNCTIONS as $function) {
            if (!function_exists($function)) {
                $this->markTestSkipped("Needs $function() (ext-pcntl / ext-posix)");
            }
        }

        self::$queueConn ??= self::openQueueDb('di_core_queue_test', true);
        $this->db = self::$queueConn->getDb();
        $this->pdo = $this->db->getLink();
        $this->pdo->exec('DELETE FROM ' . TestSetup::TABLE);
        $this->setup = new TestSetup($this->rules());
        $this->queue = new PgQueue($this->pdo, $this->setup->policy(), $this->db, $this->setup);
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            // Through the core: the connection is shared by the class, and a counter left at 1
            // by a failed test would turn the next tests' transactions into savepoints.
            OpenTransaction::rollBack($this->pdo, $this->db);
            $this->pdo->exec('DELETE FROM ' . TestSetup::TABLE);
        }
    }

    /** Rules of the test's Setup; null – TestSetup's own. */
    protected function rules(): ?array
    {
        return null;
    }

    public static function queueDsn(): ?string
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        return $dsn && str_starts_with($dsn, 'postgresql://') ? $dsn : null;
    }

    /**
     * A connection to the test server, unregistered. $create – (re)create the table, the
     * function and the trigger (once per process; a fixture process connects without it).
     */
    public static function openQueueDb(string $name, bool $create = false): Connection
    {
        $p = parse_url(self::queueDsn());
        $conn = Connection::open(
            [
                'host' => $p['host'],
                'port' => $p['port'] ?? null,
                'login' => rawurldecode($p['user'] ?? ''),
                'password' => rawurldecode($p['pass'] ?? ''),
                'database' => ltrim($p['path'] ?? '', '/'),
            ],
            Engine::POSTGRESQL,
            $name
        );
        $registry = new \ReflectionProperty(Connection::class, 'connections');
        $all = $registry->getValue();
        unset($all[$name]);
        $registry->setValue(null, $all);

        if ($create) {
            self::createTable($conn->getDb()->getLink());
        }

        return $conn;
    }

    /** A second plain PDO to the test server – as another process. */
    private function connect(): \PDO
    {
        return self::openQueueDb('di_core_queue_other_' . uniqid())->getDb()->getLink();
    }

    private function otherQueue(): PgQueue
    {
        return new PgQueue($this->connect(), new Policy($this->setup->rules()), null, $this->setup);
    }

    private function jobField(int $id, string $field)
    {
        return $this->pdo->query("SELECT $field FROM " . TestSetup::TABLE . " WHERE id = $id")->fetchColumn();
    }

    private static function createTable(\PDO $pdo): void
    {
        $t = TestSetup::TABLE;
        $folder = __DIR__ . '/../../../sql/postgres/';
        $ddl = preg_replace(['/__job__/', '/\bjob\b/'], ["__{$t}__", $t], file_get_contents($folder . 'job.sql'));

        $pdo->exec("DROP TABLE IF EXISTS $t");
        $pdo->exec($ddl);
        $pdo->exec("ALTER TABLE $t ADD CONSTRAINT {$t}_queue_check CHECK (queue IN ('" .
            implode("', '", (new TestSetup())->queues()) . "'))");
        $pdo->exec(file_get_contents($folder . 'job_notify.sql'));
        $pdo->exec("CREATE TRIGGER trg__{$t}__notify AFTER INSERT ON $t
            FOR EACH ROW EXECUTE FUNCTION di_queue_notify('" . TestSetup::CHANNEL . "')");
    }
}
