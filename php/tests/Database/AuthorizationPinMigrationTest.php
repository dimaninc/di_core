<?php

namespace diCore\Tests\Database;

use diCore\Database\Tool\Migration;
use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';
require_once __DIR__ .
    '/../../../migrations/auth/20261001120000_authorization-pin-tables.php';

/**
 * The core migration builds both tables from the dumps of the current engine and is
 * harmless where they already exist. Live: see AuthorizationPinTestDb.
 */
class AuthorizationPinMigrationTest extends TestCase
{
    const TABLES = ['authorization_pin', FailureLog::TABLE];

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
        $idx = \diMigration_20261001120000::$idx;
        $files = glob(
            __DIR__ . '/../../../migrations/{,*/}' . $idx . '_*.php',
            GLOB_BRACE
        );

        $this->assertCount(1, $files);
        $this->assertStringStartsWith($idx . '_', basename($files[0]));
    }

    public function testLiveUpCreatesBothTablesAndIsIdempotentDownDropsThem(): void
    {
        AuthorizationPinTestDb::need($this);

        $migration = new \diMigration_20261001120000();

        try {
            $migration->run(Migration::DOWN);
            $this->assertSame(
                [false, false],
                array_map([$this, 'tableExists'], self::TABLES)
            );

            $migration->run(Migration::UP);
            $this->assertSame(
                [true, true],
                array_map([$this, 'tableExists'], self::TABLES)
            );

            // A project that already has the tables must not fail.
            $migration->run(Migration::UP);
            $this->assertSame(
                [true, true],
                array_map([$this, 'tableExists'], self::TABLES)
            );
        } finally {
            // Leave the tables for the other live tests whatever happened.
            $migration->run(Migration::UP);
        }
    }

    private function tableExists(string $table): bool
    {
        $db = AuthorizationPinTestDb::db();

        try {
            $rs = $db->q("SELECT COUNT(*) AS c FROM $table");
        } catch (\Exception $e) {
            $rs = false;
        }

        $db->resetLog();

        return (bool) $rs;
    }
}
