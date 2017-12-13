<?php
class diMigration_20171213192952 extends diMigration
{
	public static $idx = "20171213192952";
	public static $name = "Slugs: unique index";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE slugs
			ADD UNIQUE INDEX full_slug_idx(full_slug),
			DROP INDEX target_idx,
			ADD INDEX target_idx(target_type,target_id,level_num)
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE slugs DROP INDEX full_slug_idx");
	}
}