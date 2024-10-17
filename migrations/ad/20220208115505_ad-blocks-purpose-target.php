<?php
class diMigration_20220208115505 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20220208115505';
	public static $name = 'Ad blocks: purpose, target';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE ad_blocks
            ADD COLUMN purpose int AFTER id,
            ADD COLUMN target_type int AFTER purpose,
            ADD COLUMN target_id int AFTER target_type
        ");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE ad_blocks
            DROP COLUMN purpose,
            DROP COLUMN target_type,
            DROP COLUMN target_id
        ");
	}
}
