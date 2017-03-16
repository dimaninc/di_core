<?php
class diMigration_20000101000003 extends diMigration
{
	public static $idx = "20000101000003";
	public static $name = "Tables (old format -> new) dipics";

	private $tables = array("dipics");

	public function up()
	{
		foreach ($this->tables as $table)
		{
			$db = $this->getDb();

			$this->getDb()->q("ALTER TABLE $table ADD COLUMN date2 datetime");

			$this->getDb()->rs_go(function($r) use ($table, $db) {
				$db->update($table, array(
					"date2" => is_numeric($r->date) ? date("Y-m-d H:i:s", $r->date) : $r->date,
				), $r->id);
			}, $table);

			$this->getDb()->q("ALTER TABLE $table
				DROP COLUMN date,
				CHANGE COLUMN date2 date timestamp default CURRENT_TIMESTAMP
			");
		}
	}

	public function down()
	{
		foreach ($this->tables as $table)
		{
			$db = $this->getDb();

			$this->getDb()->q("ALTER TABLE $table ADD COLUMN date2 int default '0'");

			$this->getDb()->rs_go(function($r) use ($table, $db) {
				$db->update($table, array(
					"date2" => !is_numeric($r->date) ? strtotime($r->date) : $r->date,
				), $r->id);
			}, $table);

			$this->getDb()->q("ALTER TABLE $table
				DROP COLUMN date,
				CHANGE COLUMN date2 date int default '0'
			");
		}
	}
}