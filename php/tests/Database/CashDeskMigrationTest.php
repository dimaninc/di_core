<?php

namespace diCore\Tests\Database;

use diCore\Controller\Cash;
use diCore\Database\Connection;
use diCore\Database\Tool\Migration;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';
require_once __DIR__ .
    '/../../../migrations/payments/20261031150000_payment-receipt-cashdesk-v2.php';
require_once __DIR__ .
    '/../../../migrations/cashdesk/20261031150100_cashdesk-heartbeat-journal.php';

/**
 * Both cash desk v2 migrations on a live server (DI_CORE_TEST_DB): they use the real
 * table names, so never against a consumer's database. The test server is left
 * without `payment_receipts` and the cash desk tables.
 */
class CashDeskMigrationTest extends TestCase
{
    const NEW_COLUMNS = [
        'fiscal_mark_kkt',
        'fiscal_fn_number',
        'fiscal_kkt_reg_number',
        'fiscal_total',
    ];

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    public function testIdxIsUniqueAcrossAllCoreMigrations(): void
    {
        foreach (
            [\diMigration_20261031150000::$idx, \diMigration_20261031150100::$idx]
            as $idx
        ) {
            $files = glob(
                __DIR__ . '/../../../migrations/{,*/}' . $idx . '_*.php',
                GLOB_BRACE
            );

            $this->assertCount(1, $files, $idx);
        }
    }

    public function testLiveReceiptColumnsFromScratchAndOverAnOldTable(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $migration = new \diMigration_20261031150000();

        try {
            $db->q('DROP TABLE IF EXISTS payment_receipts');

            // No table: created from the dump of this engine
            $migration->run(Migration::UP);
            $this->assertSame(self::NEW_COLUMNS, $this->newColumns());

            // An old table with a row in it: columns appended, the row kept
            $migration->run(Migration::DOWN);
            $this->assertSame([], $this->newColumns());
            $db->q(
                "INSERT INTO payment_receipts (amount, outer_number, draft_id) VALUES (290, 'x', 1)"
            );

            $migration->run(Migration::UP);
            $this->assertSame(self::NEW_COLUMNS, $this->newColumns());
            $this->assertTrue($this->pendingIndexExists());
            $this->assertSame(
                1,
                AuthorizationPinTestDb::count(
                    'payment_receipts',
                    "fiscal_mark_kkt = ''"
                )
            );

            // A second run is harmless
            $migration->run(Migration::UP);
            $this->assertSame(self::NEW_COLUMNS, $this->newColumns());
        } finally {
            $db->q('DROP TABLE IF EXISTS payment_receipts');
        }
    }

    public function testLiveCashDeskTables(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $migration = new \diMigration_20261031150100();
        $tables = [Cash::HEARTBEAT_TABLE, Cash::JOURNAL_TABLE];

        try {
            $migration->run(Migration::DOWN);
            $this->assertSame(
                [false, false],
                array_map([$this, 'tableExists'], $tables)
            );

            $migration->run(Migration::UP);
            $migration->run(Migration::UP);
            $this->assertSame(
                [true, true],
                array_map([$this, 'tableExists'], $tables)
            );

            // seq is unique within a journal: a new journal starts over at 1
            $insert = function (string $journalId) use ($db) {
                return $db->q(
                    'INSERT INTO ' .
                        Cash::JOURNAL_TABLE .
                        " (journal_id, seq, payload, received_at) VALUES ('$journalId', 1, '{}', '" .
                        \diDateTime::sqlFormat() .
                        "')"
                );
            };
            $this->assertNotFalse($insert('5f0c6a2e-8d1b-4c3a-9e7f-0a1b2c3d4e5f'));
            $this->assertNotFalse($insert('a3e1b9c0-4f2d-4e8a-b6c7-d8e9f0a1b2c3'));
            $this->assertFalse(@$insert('5f0c6a2e-8d1b-4c3a-9e7f-0a1b2c3d4e5f'));
            $this->assertSame(
                2,
                AuthorizationPinTestDb::count(Cash::JOURNAL_TABLE, 'seq = 1')
            );
        } finally {
            $migration->run(Migration::DOWN);
        }
    }

    private function newColumns(): array
    {
        $fields = AuthorizationPinTestDb::db()->getFields('payment_receipts');

        return array_values(
            array_filter(self::NEW_COLUMNS, function ($c) use ($fields) {
                return array_key_exists($c, $fields);
            })
        );
    }

    private function pendingIndexExists(): bool
    {
        $db = AuthorizationPinTestDb::db();

        return Connection::get()::isPostgres()
            ? AuthorizationPinTestDb::count(
                    'pg_indexes',
                    "indexname = '" .
                        \diMigration_20261031150000::INDEX_POSTGRES .
                        "'"
                ) === 1
            : $db->indexExists(
                'payment_receipts',
                \diMigration_20261031150000::INDEX_MYSQL
            );
    }

    private function tableExists(string $table): bool
    {
        return in_array($table, AuthorizationPinTestDb::db()->getTableNames(), true);
    }
}
