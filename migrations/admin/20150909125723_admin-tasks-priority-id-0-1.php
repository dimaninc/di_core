<?php
class diMigration_20150909125723 extends diMigration
{
	public static $idx = "20150909125723";
	public static $name = "Admin tasks priority ID 0 -> 1";

	public function up()
	{
		$this->getDb()->update("admin_tasks", array("priority" => 1), "WHERE priority='0'");
	}

	public function down()
	{
		$this->getDb()->update("admin_tasks", array("priority" => 0), "WHERE priority='1'");
	}
}