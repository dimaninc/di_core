<?php
class diMigration_20220824103426 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20220824103426';
	public static $name = 'Payment draft outer number';

	public function up()
	{
        $this->getDb()->q("ALTER TABLE payment_drafts ADD COLUMN outer_number VARCHAR(32) AFTER amount");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE payment_drafts DROP COLUMN outer_number");
	}
}