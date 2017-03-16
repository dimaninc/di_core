<?php
class diMigration_20160505183315 extends diMigration
{
	public static $idx = "20160505183315";
	public static $name = "Drafts and receipts: vendor added";

	protected $tables = ["payment_drafts", "payment_receipts"];

	public function up()
	{
		foreach ($this->tables as $table)
		{
			$this->getDb()->q("ALTER TABLE `$table`
				ADD COLUMN vendor tinyint unsigned default '0' AFTER pay_system
			");
		}
	}

	public function down()
	{
		foreach ($this->tables as $table)
		{
			$this->getDb()->q("ALTER TABLE `$table`
				DROP COLUMN vendor
			");
		}
	}
}