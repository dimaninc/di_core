<?php
class diMigration_20150717140200 extends diMigration
{
	public static $idx = "20150717140200";
	public static $name = "Fonts: weight and style columns";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE fonts
			ADD COLUMN weight varchar(50) default '' AFTER token,
			ADD COLUMN style varchar(50) default '' AFTER weight,
			CHANGE COLUMN date date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE fonts
			DROP COLUMN weight,
			DROP COLUMN style,
			CHANGE COLUMN date date DATETIME NULL
		");
	}
}