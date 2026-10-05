<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * ON CONFLICT spends nextval() before the conflict check, so LASTVAL() is not the id of the
 * row an insertIgnore()/insert_or_update() touched: a conflicting insertIgnore() returned an id
 * no row had, an upsert on its UPDATE path – the burned value instead of the updated row's id.
 * diModel::saveToDb() set that id on the model, and its next save() updated nothing.
 *
 * Live: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db, skipped otherwise.
 */
class PostgresqlUpsertIdTest extends TestCase
{
    private Postgresql $db;

    protected function setUp(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        $p = parse_url($dsn);
        $this->db = new Postgresql([
            'host' => $p['host'],
            'port' => $p['port'] ?? 5432,
            'username' => rawurldecode($p['user'] ?? ''),
            'password' => rawurldecode($p['pass'] ?? ''),
            'dbname' => ltrim($p['path'] ?? '', '/'),
        ]);
        $this->db->q('CREATE TEMPORARY TABLE di_core_upsert (id serial PRIMARY KEY, k int UNIQUE, v int)');
    }

    public function testConflictingInsertIgnoreReturnsNoId(): void
    {
        $this->assertSame(1, (int) $this->db->insertIgnore('di_core_upsert', ['k' => 1, 'v' => 1]));

        // A falsy id sends saveToDb() to its lookup by fields instead of setting a stray id.
        $id = $this->db->insertIgnore('di_core_upsert', ['k' => 1, 'v' => 2]);
        $this->assertNotFalse($id, 'not a failure');
        $this->assertEmpty($id, 'no row was written – no id');
    }

    public function testUpsertReturnsTheIdOfTheTouchedRow(): void
    {
        $this->assertSame(1, (int) $this->db->insert_or_update('di_core_upsert', ['k' => 1, 'v' => 1], 'k', 'id'));
        $this->assertSame(1, (int) $this->db->insert_or_update('di_core_upsert', ['k' => 1, 'v' => 2], 'k', 'id'), 'UPDATE path');
        $this->assertSame(3, (int) $this->db->insert_or_update('di_core_upsert', ['k' => 2, 'v' => 1], 'k', 'id'), 'next INSERT');
        $this->assertSame(2, (int) $this->db->fetch_array($this->db->q('SELECT v FROM di_core_upsert WHERE k = 1'))['v']);
    }

    public function testUpsertWithoutIdFieldReturnsNoId(): void
    {
        // Without an auto-increment field there is no id to report: a stray LASTVAL would end
        // up as the model's id.
        $this->db->insert('di_core_upsert', ['k' => 9, 'v' => 9]);
        $this->db->startTransaction();
        $id = $this->db->insert_or_update('di_core_upsert', ['k' => 1, 'v' => 1], 'k');

        $this->assertNotFalse($id);
        $this->assertEmpty($id);
        // Nor does it ask LASTVAL: the session's last id stays the real insert's.
        $this->assertSame(1, (int) $this->db->getLastInsertId());
        $this->db->rollbackTransaction();
    }

    public function testUpsertInATransaction(): void
    {
        $this->db->startTransaction();
        $this->assertSame(1, (int) $this->db->insert_or_update('di_core_upsert', ['k' => 1, 'v' => 1], 'k', 'id'));
        $this->assertSame(1, (int) $this->db->insert_or_update('di_core_upsert', ['k' => 1, 'v' => 2], 'k', 'id'));
        $this->assertEmpty($this->db->insertIgnore('di_core_upsert', ['k' => 1, 'v' => 3]));
        $this->assertSame(2, (int) $this->db->fetch_array($this->db->q('SELECT v FROM di_core_upsert WHERE k = 1'))['v']);
        $this->db->rollbackTransaction();
    }
}
