<?php
class diMigration_20161020223201 extends diMigration
{
	public static $idx = "20161020223201";
	public static $name = "Admin: unique index for login";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE `admins` ADD UNIQUE INDEX login_idx(login)");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE `admins` DROP INDEX login_idx");
	}
}