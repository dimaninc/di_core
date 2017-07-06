<?php
class diMigration_20170624155143 extends diMigration
{
	public static $idx = "20170624155143";
	public static $name = "Module cache: bootstrap_settings";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE module_cache
			ADD COLUMN title VARCHAR(255) DEFAULT '' AFTER id,
			ADD COLUMN bootstrap_settings VARCHAR(255) DEFAULT '' AFTER query_string");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE module_cache
			DROP COLUMN title,
			DROP COLUMN bootstrap_settings");
	}
}