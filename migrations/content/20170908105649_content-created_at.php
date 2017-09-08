<?php
class diMigration_20170908105649 extends diMigration
{
	public static $idx = "20170908105649";
	public static $name = "Content: created_at";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE content
			ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER top,
			ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE content
			DROP COLUMN created_at,
			DROP COLUMN updated_at
		");
	}
}