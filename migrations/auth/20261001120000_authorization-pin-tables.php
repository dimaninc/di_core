<?php

use diCore\Controller\Db;
use diCore\Database\Connection;
use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Entity\AuthorizationPin\Model;

/**
 * Creates `authorization_pin` and `authorization_pin_failure` from the core dumps of
 * the current engine. CREATE TABLE IF NOT EXISTS: a project that already has an
 * older `authorization_pin` keeps it as is and converts it with its own migration.
 */
class diMigration_20261001120000 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20261001120000';
    public static $name = 'Authorization pins: codes and failed-check log tables';

    public function up()
    {
        $folder = Db::getCoreSqlFolder();

        if (Connection::get(static::CONNECTION_NAME)::isPostgres()) {
            $folder .= 'postgres/';
        }

        $this->executeSqlFile(
            [Model::table . '.sql', FailureLog::TABLE . '.sql'],
            $folder
        );
    }

    /**
     * Drops both tables – including an older project `authorization_pin` that up() left alone.
     */
    public function down()
    {
        $db = $this->getDb();

        foreach ([FailureLog::TABLE, Model::table] as $table) {
            $db->q('DROP TABLE IF EXISTS ' . $db->escapeTable($table));
        }
    }
}
