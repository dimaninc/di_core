<?php
class diMigration_20160826110724 extends diMigration
{
	public static $idx = "20160826110724";
	public static $name = "Created_at trigger";

	protected $triggerTables = [
		"geo_ip_cache",
	];

	protected $triggerActions = [
		"before" => [
			"insert",
		],
	];

	protected $queries = [];

	public function up()
	{
		$this
			->triggersUp()
			->go();
	}

	public function down()
	{
		$this
			->triggersDown()
			->go();
	}

	protected function go()
	{
		foreach ($this->queries as $q)
		{
			$this->getDb()->q($q);
		}

		return $this;
	}

	protected function triggersUp()
	{
		foreach ($this->triggerTables as $table)
		{
			foreach ($this->triggerActions as $when => $actions)
			{
				foreach ($actions as $action)
				{
					$triggerName = "created_at_{$table}_{$when}_{$action}_trg";
					$source = "NEW"; //$when == "before" ? "OLD" : "NEW";

					$this->queries[] = "DROP TRIGGER IF EXISTS `{$triggerName}`";
					$this->queries[] = "CREATE TRIGGER `{$triggerName}` {$when} {$action} ON {$table}
					FOR EACH ROW
					BEGIN
						SET NEW.created_at = NOW();
					END";
				}
			}
		}

		return $this;
	}

	protected function triggersDown()
	{
		foreach ($this->triggerTables as $table)
		{
			foreach ($this->triggerActions as $when => $actions)
			{
				foreach ($actions as $action)
				{
					$triggerName = "created_at_{$table}_{$when}_{$action}_trg";
					$this->queries[] = "DROP TRIGGER IF EXISTS `{$triggerName}`";
				}
			}
		}

		return $this;
	}
}