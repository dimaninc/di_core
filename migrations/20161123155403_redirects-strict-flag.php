<?php
class diMigration_20161123155403 extends diMigration
{
	public static $idx = "20161123155403";
	public static $name = "Redirects: strict flag";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE redirects ADD COLUMN strict_for_query TINYINT DEFAULT 0");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE redirects DROP COLUMN strict_for_query");
	}
}