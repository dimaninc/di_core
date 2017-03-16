<?php
class diMigration_20161206182455 extends diMigration
{
	public static $idx = "20161206182455";
	public static $name = "mixplat errors in drafts";

	protected $tables = ['payment_drafts', 'payment_receipts'];

	public function up()
	{
		foreach ($this->tables as $table)
		{
			$this->getDb()->q("ALTER TABLE {$table}
				ADD COLUMN `status` TINYINT DEFAULT '0' AFTER `vendor`
			");
		}
	}

	public function down()
	{
		foreach ($this->tables as $table)
		{
			$this->getDb()->q("ALTER TABLE {$table}
				DROP COLUMN `status`
			");
		}
	}
}