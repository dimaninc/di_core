<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * lastInsertId() on Postgres is LASTVAL(), which fails when the session has no nextval()
 * yet (a fresh connection, or a table without a sequence). The core used to ask for it after
 * every query and insert, and inside a transaction the failure aborted it (25P02): every
 * later query there silently returned nothing, and the COMMIT rolled back.
 *
 * Live: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db, skipped otherwise.
 */
class PostgresqlTransactionReadsTest extends TestCase
{
    private Postgresql $db;

    protected function setUp(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        // A fresh connection for every test: no nextval() in this session yet.
        $this->db = new Postgresql(self::connectionParams());
        $this->db->q('CREATE TEMPORARY TABLE di_core_lastval_seq (id serial PRIMARY KEY, v int)');
        $this->db->q('CREATE TEMPORARY TABLE di_core_lastval_link (a int, b int, PRIMARY KEY (a, b))');
        $this->db->q('CREATE TEMPORARY TABLE di_core_lastval_kv (k int PRIMARY KEY, v int)');
    }

    public function testEveryReadInATransactionSeesRows(): void
    {
        $this->db->startTransaction();

        foreach ([1, 2, 3] as $n) {
            $this->assertTransactionAlive("read #$n");
        }

        $this->db->rollbackTransaction();
    }

    public function testInsertWithoutSequenceKeepsTheTransaction(): void
    {
        $this->db->startTransaction();

        $this->db->insert('di_core_lastval_link', ['a' => 1, 'b' => 2]);
        $this->assertTransactionAlive('after insert()');

        $this->db->q('INSERT INTO di_core_lastval_link (a, b) VALUES (3, 4)');
        $this->assertTransactionAlive('after a raw INSERT');

        $this->db->insert_or_update('di_core_lastval_kv', ['k' => 1, 'v' => 1], 'k');
        $this->assertTransactionAlive('after insert_or_update()');

        // A read that merely mentions INSERT: guards against telling inserts by query text.
        $this->db->q("WITH x AS (SELECT 'INSERT' AS w) SELECT w FROM x");
        $this->assertTransactionAlive('after a read mentioning INSERT');

        $this->assertSame(2, (int) $this->db->fetch_array($this->db->q('SELECT count(*) AS c FROM di_core_lastval_link'))['c']);
        $this->db->rollbackTransaction();
    }

    public function testRawInsertReportsItsId(): void
    {
        $this->db->q('INSERT INTO di_core_lastval_seq (v) VALUES (10)');
        $this->assertSame(1, (int) $this->db->getLastInsertId());

        // Leading comments: guards against telling inserts by query text.
        $this->db->q("/* tag */ INSERT INTO di_core_lastval_seq (v) VALUES (20)");
        $this->assertSame(2, (int) $this->db->getLastInsertId(), 'INSERT after a comment');

        $this->db->startTransaction();
        $this->db->q('SELECT v FROM di_core_lastval_seq');
        $this->db->q("-- line\nINSERT INTO di_core_lastval_seq (v) VALUES (30)");
        $this->db->q('SELECT v FROM di_core_lastval_seq');
        $this->assertSame(3, (int) $this->db->getLastInsertId(), 'asked after later reads, inside a transaction');
        $this->assertTransactionAlive('after the insert');
        $this->db->rollbackTransaction();
    }

    public function testInsertReportsItsIdInATransaction(): void
    {
        // The path of save(): insert() on a fresh connection, inside a transaction.
        $this->db->startTransaction();
        $this->assertSame(1, (int) $this->db->insert('di_core_lastval_seq', ['v' => 1]));
        $this->assertTransactionAlive('after insert()');
        $this->db->rollbackTransaction();
    }

    public function testConflictingInsertIgnoreKeepsTheTransaction(): void
    {
        $this->db->q('INSERT INTO di_core_lastval_link (a, b) VALUES (1, 1)');
        $this->db->startTransaction();
        $this->db->insertIgnore('di_core_lastval_link', ['a' => 1, 'b' => 1]);
        $this->assertTransactionAlive('after a conflicting insertIgnore()');
        $this->db->rollbackTransaction();
    }

    public function testNestedRollbackAfterInsertKeepsTheOuterLevel(): void
    {
        $this->db->startTransaction();
        $this->db->insert('di_core_lastval_seq', ['v' => 1]);
        $this->db->startTransaction();
        $this->db->insert('di_core_lastval_link', ['a' => 5, 'b' => 5]);
        $this->db->rollbackTransaction();

        $this->assertTransactionAlive('outer level after the inner rollback');
        $this->assertSame(1, (int) $this->db->fetch_array($this->db->q('SELECT count(*) AS c FROM di_core_lastval_seq'))['c']);
        $this->assertSame(0, (int) $this->db->fetch_array($this->db->q('SELECT count(*) AS c FROM di_core_lastval_link'))['c']);
        $this->db->rollbackTransaction();
    }

    public function testReadsDoNotAskForTheId(): void
    {
        // Asking after every read would put a savepoint round trip on each of them.
        $db = $this->countingConnection();
        $db->startTransaction();

        foreach ([1, 2, 3] as $n) {
            $db->q('SELECT 1');
        }

        $db->q("WITH x AS (SELECT 1 AS v) SELECT v FROM x");
        $this->assertSame(0, $db->insertIdCalls, 'reads');

        $db->q('INSERT INTO di_core_lastval_seq (v) VALUES (1)');
        $this->assertSame(1, $db->insertIdCalls, 'a raw INSERT');
        $db->rollbackTransaction();
    }

    public function testStatementsReturningRowsKeepTheId(): void
    {
        // A statement that returns rows is not asked for LASTVAL (that keeps reads free): after
        // INSERT … RETURNING the id comes from its result, getLastInsertId() keeps the previous.
        $this->db->q('INSERT INTO di_core_lastval_seq (v) VALUES (1)');
        $row = $this->db->fetch_array($this->db->q('INSERT INTO di_core_lastval_seq (v) VALUES (2) RETURNING id'));

        $this->assertSame(2, (int) $row['id']);
        $this->assertSame(1, (int) $this->db->getLastInsertId());

        // The same for any statement that returns rows, SELECT nextval() included.
        $this->db->q("SELECT nextval('di_core_lastval_seq_id_seq')");
        $this->assertSame(1, (int) $this->db->getLastInsertId(), 'SELECT nextval()');
    }

    public function testRawInsertIdSurvivesLaterWrites(): void
    {
        $this->db->q('INSERT INTO di_core_lastval_seq (v) VALUES (1)');
        $this->db->insertIgnore('di_core_lastval_link', ['a' => 1, 'b' => 1]);
        $this->assertSame(1, (int) $this->db->getLastInsertId(), 'after insertIgnore()');

        $this->db->rq('INSERT INTO di_core_lastval_seq (v) VALUES (2)');
        $this->assertSame(1, (int) $this->db->getLastInsertId(), 'after rq()');
    }

    public function testInsertWithoutSequenceIsCommitted(): void
    {
        // COMMIT of an aborted transaction is a silent ROLLBACK: the row used to be lost.
        $this->db->startTransaction();
        $this->db->insert('di_core_lastval_link', ['a' => 7, 'b' => 7]);
        $this->db->commitTransaction();

        $this->assertSame(1, (int) $this->db->fetch_array($this->db->q('SELECT count(*) AS c FROM di_core_lastval_link WHERE a = 7'))['c']);
    }

    public function testThrowingInsertIdResetsTheId(): void
    {
        // A consumer's __insert_id() that throws: the query still succeeds, the id becomes null.
        $db = new class (self::connectionParams()) extends Postgresql {
            protected function __insert_id()
            {
                throw new \PDOException('probe');
            }
        };
        $db->q('CREATE TEMPORARY TABLE di_core_lastval_seq (id serial PRIMARY KEY, v int)');

        $this->assertNotFalse($db->q('INSERT INTO di_core_lastval_seq (v) VALUES (1)'));
        $this->assertNull($db->getLastInsertId());
    }

    private function countingConnection(): Postgresql
    {
        $db = new class (self::connectionParams()) extends Postgresql {
            public int $insertIdCalls = 0;

            protected function __insert_id()
            {
                $this->insertIdCalls++;

                return parent::__insert_id();
            }
        };
        $db->q('CREATE TEMPORARY TABLE di_core_lastval_seq (id serial PRIMARY KEY, v int)');
        $db->insertIdCalls = 0;

        return $db;
    }

    private static function connectionParams(): array
    {
        $p = parse_url(getenv('DI_CORE_TEST_DB'));

        return [
            'host' => $p['host'],
            'port' => $p['port'] ?? 5432,
            'username' => rawurldecode($p['user'] ?? ''),
            'password' => rawurldecode($p['pass'] ?? ''),
            'dbname' => ltrim($p['path'] ?? '', '/'),
        ];
    }

    private function assertTransactionAlive(string $message): void
    {
        $this->assertSame(1, (int) $this->db->fetch_array($this->db->q('SELECT 1 AS one'))['one'], $message);
    }
}
