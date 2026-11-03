<?php

use diCore\Controller\Db;
use diCore\Database\Connection;

/**
 * Cash desk contract v2 (`Controller\Cash`): what the cash desk reports about the
 * document (FPD into its own `fiscal_mark_kkt`, `fiscal_mark` stays with whoever
 * filled it before, FN, KKT registration number, total in kopecks) and an index
 * for the queue it polls every minute.
 *
 * Idempotent, step by step against the schema. A missing table is created from
 * the dump of the current engine, which already has all of it.
 */
class diMigration_20261031150000 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20261031150000';
    public static $name = 'Payment receipts: cash desk v2 fiscal columns, queue index';

    const TABLE = 'payment_receipts';

    const COLUMNS_MYSQL = [
        'fiscal_mark_kkt' => "varchar(16) DEFAULT ''",
        'fiscal_fn_number' => "varchar(20) DEFAULT ''",
        'fiscal_kkt_reg_number' => "varchar(20) DEFAULT ''",
        'fiscal_total' => 'int DEFAULT NULL',
    ];

    const COLUMNS_POSTGRES = [
        'fiscal_mark_kkt' => "text DEFAULT ''",
        'fiscal_fn_number' => "text DEFAULT ''",
        'fiscal_kkt_reg_number' => "text DEFAULT ''",
        'fiscal_total' => 'bigint DEFAULT NULL',
    ];

    const INDEX_MYSQL = 'pending_idx';
    const INDEX_POSTGRES = 'idx__payment_receipts__pending';

    public function up()
    {
        $db = $this->getDb();

        if (!in_array(self::TABLE, $db->getTableNames(), true)) {
            $this->executeSqlFile([self::TABLE . '.sql'], $this->dumpFolder());

            return;
        }

        $postgres = $this->isPostgres();

        foreach (
            $postgres ? self::COLUMNS_POSTGRES : self::COLUMNS_MYSQL
            as $column => $type
        ) {
            if (!$this->columnExists(self::TABLE, $column)) {
                // Appended last: MySQL 8.0 adds such a column INSTANTly
                $this->exec(
                    'ALTER TABLE ' . self::TABLE . " ADD COLUMN $column $type"
                );
            }
        }

        if ($postgres) {
            $this->exec(
                'CREATE INDEX IF NOT EXISTS ' .
                    self::INDEX_POSTGRES .
                    ' ON ' .
                    self::TABLE .
                    ' (date_payed) WHERE date_uploaded IS NULL'
            );
        } elseif (!$this->indexExists(self::TABLE, self::INDEX_MYSQL)) {
            $this->exec(
                'ALTER TABLE ' .
                    self::TABLE .
                    ' ADD INDEX ' .
                    self::INDEX_MYSQL .
                    ' (date_uploaded, date_payed), ALGORITHM=INPLACE, LOCK=NONE'
            );
        }
    }

    public function down()
    {
        $postgres = $this->isPostgres();

        if ($postgres) {
            $this->exec('DROP INDEX IF EXISTS ' . self::INDEX_POSTGRES);
        } elseif ($this->indexExists(self::TABLE, self::INDEX_MYSQL)) {
            $this->exec(
                'ALTER TABLE ' . self::TABLE . ' DROP INDEX ' . self::INDEX_MYSQL
            );
        }

        foreach (array_keys(self::COLUMNS_MYSQL) as $column) {
            if ($this->columnExists(self::TABLE, $column)) {
                $this->exec('ALTER TABLE ' . self::TABLE . " DROP COLUMN $column");
            }
        }
    }

    private function isPostgres(): bool
    {
        return Connection::get(static::CONNECTION_NAME)::isPostgres();
    }

    private function dumpFolder(): string
    {
        return Db::getCoreSqlFolder() . ($this->isPostgres() ? 'postgres/' : '');
    }

    /**
     * q() only logs a failure; run() turns the log into an exception, but a throw
     * here stops the remaining steps on the spot.
     */
    private function exec(string $sql): void
    {
        if ($this->getDb()->q($sql) === false) {
            throw new \diDatabaseException('Migration #' . static::$idx . ": $sql");
        }
    }
}
