<?php

use diCore\Controller\Cash;
use diCore\Controller\Db;
use diCore\Database\Connection;

/**
 * Cash desk contract v2: the last heartbeats of the cash desk and the copy of its
 * journal (`Controller\Cash::_postHeartbeatAction()`). CREATE TABLE IF NOT EXISTS.
 */
class diMigration_20261031150100 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20261031150100';
    public static $name = 'Cash desk: heartbeat and journal tables';

    public function up()
    {
        $folder = Db::getCoreSqlFolder();

        if (Connection::get(static::CONNECTION_NAME)::isPostgres()) {
            $folder .= 'postgres/';
        }

        $this->executeSqlFile(
            [Cash::HEARTBEAT_TABLE . '.sql', Cash::JOURNAL_TABLE . '.sql'],
            $folder
        );
    }

    public function down()
    {
        $db = $this->getDb();

        foreach ([Cash::JOURNAL_TABLE, Cash::HEARTBEAT_TABLE] as $table) {
            $db->q('DROP TABLE IF EXISTS ' . $db->escapeTable($table));
        }
    }
}
