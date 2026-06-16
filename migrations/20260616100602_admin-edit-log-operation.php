<?php

use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Entity\AdminTableEditLog\Model;

/**
 * Adds the `operation` field (update/delete) to the admin edit log.
 *
 * The log can live on different engines depending on the project, so we branch on
 * the connection of the resolved log model (NOT the default migration connection):
 *   - MySQL / Postgres: ADD COLUMN ... DEFAULT 'update' — the column default
 *     backfills existing rows, so no extra UPDATE is needed.
 *   - Mongo: schemaless, no column default — explicitly backfill existing documents
 *     that predate the feature (they are all plain updates; deletions set the field).
 *   - Other engines: no-op.
 */
class diMigration_20260616100602 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20260616100602';
    public static $name = 'Admin edit log: operation column (update/delete)';

    const TABLE = 'admin_table_edit_log';

    public function up()
    {
        $conn = $this->logConnection();
        $db = $conn->getDb();

        switch ($conn::getEngine()) {
            case Engine::MYSQL:
            case Engine::MYSQL_OLD:
                if (
                    $this->mysqlTableExists($db) &&
                    !$this->mysqlColumnExists($db, 'operation')
                ) {
                    $db->q(
                        'ALTER TABLE ' .
                            self::TABLE .
                            " ADD COLUMN operation varchar(16) not null default 'update' AFTER new_data"
                    );
                }
                break;

            case Engine::POSTGRESQL:
                // IF EXISTS / IF NOT EXISTS keep it idempotent and a no-op when the
                // table is absent; the default backfills existing rows.
                $db->q(
                    'ALTER TABLE IF EXISTS ' .
                        self::TABLE .
                        " ADD COLUMN IF NOT EXISTS operation varchar(16) not null default 'update'"
                );
                break;

            case Engine::MONGO:
                // {operation: null} matches both missing and null fields
                $db->update(
                    self::TABLE,
                    [
                        'operation' => Model::OPERATION_UPDATE,
                    ],
                    ['operation' => null]
                );
                break;
        }
    }

    public function down()
    {
        $conn = $this->logConnection();
        $db = $conn->getDb();

        switch ($conn::getEngine()) {
            case Engine::MYSQL:
            case Engine::MYSQL_OLD:
                if (
                    $this->mysqlTableExists($db) &&
                    $this->mysqlColumnExists($db, 'operation')
                ) {
                    $db->q('ALTER TABLE ' . self::TABLE . ' DROP COLUMN operation');
                }
                break;

            case Engine::POSTGRESQL:
                $db->q(
                    'ALTER TABLE IF EXISTS ' .
                        self::TABLE .
                        ' DROP COLUMN IF EXISTS operation'
                );
                break;

            // Mongo: the field is harmless on rollback and the wrapper has no
            // $unset, so leave the documents as-is.
        }
    }

    /**
     * The log model is resolved by type, so in a project that stores it elsewhere
     * (e.g. 1romantic → Mongo) we get that connection, not the default one.
     */
    private function logConnection()
    {
        $class = \diModel::existsFor(Model::type);

        return $class ? $class::getConnection() : Connection::get();
    }

    private function mysqlTableExists($db): bool
    {
        return $this->mysqlCount(
            $db,
            "SELECT COUNT(*) AS n FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::TABLE .
                "'"
        ) > 0;
    }

    private function mysqlColumnExists($db, string $column): bool
    {
        return $this->mysqlCount(
            $db,
            "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::TABLE .
                "' AND COLUMN_NAME = '$column'"
        ) > 0;
    }

    private function mysqlCount($db, string $sql): int
    {
        $rs = $db->q($sql);
        $row = $rs ? $db->fetch($rs) : null;

        return $row ? (int) $row->n : 0;
    }
}
