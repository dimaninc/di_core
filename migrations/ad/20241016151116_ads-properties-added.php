<?php
class diMigration_20241016151116 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20241016151116';
    public static $name = 'Ads: properties added';

    public function up()
    {
        if (
            $this->getDb()
                ->getConnection()
                ::isPostgres()
        ) {
            $this->getDb()->q("ALTER TABLE ads
                ADD COLUMN properties jsonb
            ");

            $this->getDb()->q("ALTER TABLE ad_blocks
                ADD COLUMN properties jsonb
            ");
        } elseif (
            $this->getDb()
                ->getConnection()
                ::isAlterAfterSupported()
        ) {
            $this->getDb()->q("ALTER TABLE ads
                ADD COLUMN properties json AFTER id
            ");

            $this->getDb()->q("ALTER TABLE ad_blocks
                ADD COLUMN properties json AFTER id
            ");
        } else {
            $this->getDb()->q("ALTER TABLE ads
                ADD COLUMN properties json
            ");

            $this->getDb()->q("ALTER TABLE ad_blocks
                ADD COLUMN properties json
            ");
        }
    }

    public function down()
    {
        $this->getDb()->q("ALTER TABLE ads
            DROP COLUMN properties
        ");

        $this->getDb()->q("ALTER TABLE ad_blocks
            DROP COLUMN properties
        ");
    }
}
