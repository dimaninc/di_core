<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Sqlite;
use PHPUnit\Framework\TestCase;

/**
 * PDO exec() returns the affected count instead of a statement. It used to be dropped,
 * and execWrite()/update() reported rowCount() of the PREVIOUS query – a guarded
 * `UPDATE … WHERE id=? AND status=?` then passed or failed by what a SELECT before it
 * happened to return.
 */
class PdoAffectedRowsTest extends TestCase
{
    /** @param class-string<Sqlite> $class */
    private function makeDb(string $class = Sqlite::class): Sqlite
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Needs pdo_sqlite');
        }

        $db = new $class(['dbname' => ':memory:']);
        $db->q('CREATE TABLE t (id integer primary key, status integer)');
        $db->q('INSERT INTO t (id, status) VALUES (1, 0), (2, 0), (3, 0)');

        return $db;
    }

    public function testExecWriteReportsItsOwnCount(): void
    {
        $db = $this->makeDb();

        $rs = $db->q('SELECT id FROM t');
        while ($db->fetch_array($rs)) {
        }

        $this->assertSame(
            1,
            $db->execWrite('UPDATE t SET status = 1 WHERE id = 2 AND status = 0')
        );
        $this->assertSame(
            0,
            $db->execWrite('UPDATE t SET status = 1 WHERE id = 2 AND status = 0')
        );
        $this->assertSame(2, $db->execWrite('DELETE FROM t WHERE status = 0'));
    }

    public function testUpdateAffectedRowsFollowsTheUpdate(): void
    {
        $db = $this->makeDb();

        $db->update('t', ['status' => 5], 'WHERE id = 3');

        $this->assertSame(1, $db->affected_rows);
    }

    /** Под debug time_log() запускает EXPLAIN – счётчик должен остаться от UPDATE. */
    public function testUpdateAffectedRowsSurvivesDebugExplain(): void
    {
        $db = $this->makeDb(DebugSqlite::class);
        $db->enableDebug();

        $db->update('t', ['status' => 5], 'WHERE id = 3');

        $this->assertSame(1, $db->affected_rows);
        $this->assertTrue($db->explained, 'debug path must have run EXPLAIN');
    }
}

/** Debug без файла: строки журнала только отмечаются. */
class DebugSqlite extends Sqlite
{
    public bool $explained = false;

    public function debugMessage($message)
    {
        if (is_array($message) && count($message) > 4) {
            $this->explained = true;
        }

        return $this;
    }
}
