<?php
class diMigration_20170901154926 extends diMigration
{
	public static $idx = "20170901154926";
	public static $name = "Payment draft: paid";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_drafts ADD COLUMN paid TINYINT DEFAULT 0");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE payment_drafts DROP COLUMN paid");
	}
}