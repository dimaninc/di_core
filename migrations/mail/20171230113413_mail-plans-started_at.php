<?php
class diMigration_20171230113413 extends diMigration
{
	public static $idx = "20171230113413";
	public static $name = "Mail plans: started_at";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE mail_plans
			ADD COLUMN started_at   DATETIME NULL DEFAULT NULL AFTER created_at,
			DROP INDEX idx,
			ADD INDEX idx(target_type, target_id, mode, started_at, processed_at)
		");
		$this->getDb()->q("UPDATE mail_plans SET started_at = processed_at");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE mail_plans
			DROP COLUMN started_at
		");
	}
}