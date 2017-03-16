<?php
class diMigration_20160920131354 extends diMigration
{
	public static $idx = "20160920131354";
	public static $name = "Payments: outer number -> string";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_receipts CHANGE COLUMN outer_number outer_number VARCHAR(32) DEFAULT ''");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE payment_receipts CHANGE COLUMN outer_number outer_number BIGINT DEFAULT '0'");
	}
}