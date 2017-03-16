<?php
class diMigration_20151009175545 extends diMigration
{
	public static $idx = "20151009175545";
	public static $name = "Content: ico and color lengths";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE content
			ADD COLUMN ico varchar(50) default '' AFTER pic2_t,
			ADD COLUMN ico_w int default '0' AFTER ico,
			ADD COLUMN ico_h int default '0' AFTER ico_w,
			ADD COLUMN ico_t int default '0' AFTER ico_h,
			CHANGE COLUMN color color varchar(32) default '',
			CHANGE COLUMN background_color background_color varchar(32) default ''
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE content
			DROP COLUMN ico varchar(50) default '',
			DROP COLUMN ico_w int default '0',
			DROP COLUMN ico_h int default '0',
			DROP COLUMN ico_t int default '0',
			CHANGE COLUMN color color varchar(10) default '',
			CHANGE COLUMN background_color background_color varchar(10) default ''
		");
	}
}