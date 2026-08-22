<?php

namespace diCore\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * Covers the ref-counted / savepoint-based transaction nesting in \diDB.
 *
 * MySQL/Postgres/SQLite have no true nested transactions (a second
 * START TRANSACTION implicitly commits the first). \diDB emulates nesting so a
 * caller can wrap several writes — each of which opens its own transaction via
 * \diModel::save() — in one outer transaction that commits or rolls back
 * atomically. The real BEGIN/COMMIT/ROLLBACK fires only at the outermost level;
 * inner levels use SAVEPOINTs.
 *
 * Self-contained: a throwaway table, dropped in tearDown.
 */
class TransactionNestingTest extends TestCase
{
    private const TABLE = '_di_core_test_tx_nesting';

    private \diDB $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ' . \diCore\Data\Config::getDbCharsetClause()
        );
    }

    protected function tearDown(): void
    {
        // make sure a failed assertion can't leave a transaction open
        while ($this->db->getTransactionNestingLevel() > 0) {
            $this->db->rollbackTransaction();
        }

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        parent::tearDown();
    }

    private function rowCount(): int
    {
        return (int) $this->db->r(self::TABLE, '', 'COUNT(*) AS n')->n;
    }

    private function insert(string $title): void
    {
        $this->db->insert(self::TABLE, ['title' => $title]);
    }

    public function testOuterCommitPersistsInnerWork(): void
    {
        $this->db->startTransaction(); // outer → real BEGIN
        $this->insert('outer');

        $this->db->startTransaction(); // inner → SAVEPOINT
        $this->insert('inner');
        $this->db->commitTransaction(); // inner → RELEASE SAVEPOINT (not durable yet)

        $this->db->commitTransaction(); // outer → real COMMIT

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(2, $this->rowCount());
    }

    public function testOuterRollbackUndoesInnerCommittedWork(): void
    {
        // The key property the gift-coupon claim relies on: an inner save()
        // "commits" (releases its savepoint) but is NOT durable until the outer
        // transaction commits — so an outer rollback wipes it.
        $this->db->startTransaction();
        $this->insert('outer');

        $this->db->startTransaction();
        $this->insert('inner');
        $this->db->commitTransaction(); // inner release — still inside outer tx

        $this->db->rollbackTransaction(); // outer ROLLBACK → everything gone

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(0, $this->rowCount());
    }

    public function testInnerRollbackKeepsOuterAlive(): void
    {
        // Partial rollback: undoing the inner level (ROLLBACK TO SAVEPOINT) must
        // leave the outer transaction open and committable.
        $this->db->startTransaction();
        $this->insert('keep');

        $this->db->startTransaction();
        $this->insert('discard');
        $this->db->rollbackTransaction(); // ROLLBACK TO SAVEPOINT — only 'discard'

        $this->db->commitTransaction(); // outer commits 'keep'

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(1, $this->rowCount());

        $row = $this->db->r(self::TABLE, '', 'title');
        $this->assertSame('keep', $row->title);
    }

    public function testThreeLevelsCommit(): void
    {
        $this->db->startTransaction();
        $this->insert('l1');
        $this->db->startTransaction();
        $this->insert('l2');
        $this->db->startTransaction();
        $this->insert('l3');
        $this->db->commitTransaction();
        $this->db->commitTransaction();
        $this->db->commitTransaction();

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(3, $this->rowCount());
    }

    public function testSingleLevelRollback(): void
    {
        $this->db->startTransaction();
        $this->insert('gone');
        $this->db->rollbackTransaction();

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(0, $this->rowCount());
    }

    public function testInnerSavepointNameReuseAfterRollback(): void
    {
        // Roll back an inner level, then open ANOTHER inner level at the same
        // depth and commit it — the di_sp_<level> naming redefines the savepoint,
        // which MySQL allows. The second inner's work must survive to the outer
        // commit; the discarded one must not.
        $this->db->startTransaction(); // outer
        $this->insert('base');

        $this->db->startTransaction(); // inner di_sp_1
        $this->insert('discarded');
        $this->db->rollbackTransaction(); // ROLLBACK TO SAVEPOINT di_sp_1

        $this->db->startTransaction(); // inner di_sp_1 again (redefined)
        $this->insert('kept');
        $this->db->commitTransaction(); // RELEASE SAVEPOINT di_sp_1

        $this->db->commitTransaction(); // outer COMMIT

        $this->assertSame(0, $this->db->getTransactionNestingLevel());
        $this->assertSame(2, $this->rowCount()); // 'base' + 'kept'
    }

    public function testNestedUnwindToleratesServerSideRollback(): void
    {
        // Simulate InnoDB rolling the whole transaction back underneath us
        // (deadlock / lock-wait timeout): every savepoint vanishes. The nested
        // unwind must NOT throw the resulting "SAVEPOINT does not exist" (that
        // would mask a caller's original exception, since rollbackTransaction()
        // runs inside diModel::save()'s catch) and must reset the counter so the
        // connection stays reusable.
        $this->db->startTransaction(); // outer → real BEGIN
        $this->db->startTransaction(); // inner → SAVEPOINT di_sp_1
        $this->insert('doomed');

        // the server wipes the transaction + all savepoints out from under us
        $this->db->q('ROLLBACK');

        // both levels unwind without throwing, despite the savepoints being gone
        $this->db->rollbackTransaction(); // ROLLBACK TO SAVEPOINT di_sp_1 → 1305, swallowed
        $this->db->rollbackTransaction();

        $this->assertSame(0, $this->db->getTransactionNestingLevel());

        // connection is clean and reusable
        $this->insert('after');
        $this->assertSame(1, $this->rowCount());
    }
}
