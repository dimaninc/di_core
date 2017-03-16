<?php
class diMigration_20150622123705 extends diMigration
{
	public static $idx = "20150622123705";
	public static $name = "Content: removing unused fields";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE content
			DROP COLUMN pic3,
			DROP COLUMN pic4,
			ADD COLUMN pic2_w int default '0' after pic2,
			ADD COLUMN pic2_h int default '0' after pic2_w,
			ADD COLUMN ad_block_id bigint default '0'
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE content
			ADD COLUMN pic3 varchar(32) default '',
			ADD COLUMN pic4 varchar(32) default '',
			DROP COLUMN pic2_w,
			DROP COLUMN pic2_h,
			DROP COLUMN ad_block_id
		");
	}
}