<?php
class diMigration_20241018114132 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20241018114132';
    public static $name = 'Content: properties added';

    public function up()
    {
        if (
            $this->getDb()
                ->getConnection()
                ::isPostgres()
        ) {
            $this->getDb()->q("ALTER TABLE content
                ADD COLUMN properties jsonb
            ");
        } elseif (
            $this->getDb()
                ->getConnection()
                ::isAlterAfterSupported()
        ) {
            $this->getDb()->q("ALTER TABLE content
                ADD COLUMN properties json AFTER top
            ");
        } else {
            $this->getDb()->q("ALTER TABLE content
                ADD COLUMN properties json
            ");
        }
    }

    public function down()
    {
        $this->getDb()->q("ALTER TABLE content
            DROP COLUMN properties
        ");
    }
}
