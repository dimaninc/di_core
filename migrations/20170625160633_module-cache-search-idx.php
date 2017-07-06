<?php
class diMigration_20170625160633 extends diMigration
{
	public static $idx = "20170625160633";
	public static $name = "Module cache: search idx";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE module_cache ADD INDEX search_idx(module_id,query_string,bootstrap_settings)");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE module_cache DROP INDEX search_idx");
	}
}