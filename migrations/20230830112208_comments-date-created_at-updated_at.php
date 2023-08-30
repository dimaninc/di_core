<?php
class diMigration_20230830112208 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20230830112208';
    public static $name = 'Comments: date -> created_at/updated_at';

    public function up()
    {
        $this->getDb()->q("ALTER TABLE comments
			CHANGE COLUMN date created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL
		");
    }

    public function down()
    {
        $this->getDb()->q("ALTER TABLE comments
			CHANGE COLUMN created_at date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			DROP COLUMN updated_at
		");
    }
}
