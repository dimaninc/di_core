<?php
class diMigration_20171224111919 extends diMigration
{
	public static $idx = "20171224111919";
	public static $name = "Ads: date/time show settings";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE ads
			ADD COLUMN href_target TINYINT DEFAULT '0' AFTER href,
			ADD COLUMN show_date1 DATE NULL DEFAULT NULL AFTER date,
			ADD COLUMN show_date2 DATE NULL DEFAULT NULL after show_date1,
			ADD COLUMN show_time1 TIME NULL DEFAULT NULL AFTER show_date2,
			ADD COLUMN show_time2 TIME NULL DEFAULT NULL after show_time1,
			ADD COLUMN show_on_weekdays VARCHAR(50) DEFAULT '' AFTER show_time2,
			ADD COLUMN show_on_holidays TINYINT DEFAULT 0 AFTER show_on_weekdays,
			DROP INDEX idx,
			ADD INDEX idx(block_id, category_id, show_date1, show_date2, show_time1, show_time2, show_on_weekdays, show_on_holidays, visible, order_num)
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE ads
			DROP COLUMN href_target,
			DROP COLUMN show_date1,
			DROP COLUMN show_date2,
			DROP COLUMN show_time1,
			DROP COLUMN show_time2,
			DROP COLUMN show_on_weekdays,
			DROP COLUMN show_on_holidays
		");
	}
}