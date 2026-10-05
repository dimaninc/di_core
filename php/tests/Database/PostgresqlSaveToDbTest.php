<?php

namespace diCore\Tests\Database;

use diCore\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * diModel::saveToDb() on Postgres: the id a model gets after insertIgnore()/insert_or_update().
 * ON CONFLICT burns nextval() before the conflict check, and a table without a sequence reports
 * a stale LASTVAL – either way the model used to get an id of no row (or of another table), and
 * its next save() updated nothing. The model's upsert path is not here: on Postgres it conflicts
 * on `id` only, which an auto-increment model never sends – see CLAUDE.md.
 *
 * Live: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db, skipped otherwise.
 */
class PostgresqlSaveToDbTest extends TestCase
{
    private const CONNECTION = 'di_core_pg_save_to_db';

    private static bool $opened = false;

    protected function setUp(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        // Once per process: Connection refuses a second connection under the same name.
        // psql://: Engine::normalizeId() takes aliases only, not the canonical «postgresql».
        if (!self::$opened) {
            Connection::open(preg_replace('#^postgresql://#', 'psql://', $dsn), null, self::CONNECTION);
            self::$opened = true;
        }
        $db = Connection::get(self::CONNECTION)->getDb();
        $db->q('DROP TABLE IF EXISTS _di_core_pg_save, _di_core_pg_save_code, _di_core_pg_save_other');
        $db->q('CREATE TABLE _di_core_pg_save (id serial PRIMARY KEY, k int UNIQUE, v int)');
        $db->q('CREATE TABLE _di_core_pg_save_code (code text PRIMARY KEY, v int)');
        $db->q('CREATE TABLE _di_core_pg_save_other (id serial PRIMARY KEY, v int)');
    }

    protected function tearDown(): void
    {
        if (getenv('DI_CORE_TEST_DB')) {
            Connection::get(self::CONNECTION)->getDb()->q('DROP TABLE IF EXISTS _di_core_pg_save, _di_core_pg_save_code, _di_core_pg_save_other');
        }
    }

    public function testSkippedConflictFindsTheExistingRow(): void
    {
        $this->model()->set('k', 7)->set('v', 1)->save();

        $m = $this->model()->set('k', 7)->set('v', 2)->allowSkipConflictOnInsert(['k']);
        $m->save();

        $this->assertSame(1, (int) $m->getId(), 'looked up by k, not the burned nextval');
    }

    public function testTextKeyIsNotReplacedByAStaleInsertId(): void
    {
        $this->db()->insert('_di_core_pg_save_other', ['v' => 1]);

        $m = new class extends \diModel {
            const connection_name = 'di_core_pg_save_to_db';
            const table = '_di_core_pg_save_code';
            const id_field_name = 'code';
            protected $idAutoIncremented = false;
        };
        $m->setId('abc')->set('v', 1)->allowSkipConflictOnInsert();
        $m->save();

        $this->assertSame('abc', $m->getId());
    }

    private function model(): \diModel
    {
        return new class extends \diModel {
            const connection_name = 'di_core_pg_save_to_db';
            const table = '_di_core_pg_save';
        };
    }

    private function db(): \diDB
    {
        return Connection::get(self::CONNECTION)->getDb();
    }
}
