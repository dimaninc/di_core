<?php
class diMigration_20151013160113 extends diMigration
{
	public static $idx = "20151013160113";
	public static $name = "Auto update counts: photos, videos";

	protected $procedureName = "album_media_count_updater";
	protected $triggerTables = [
		"photos",
		"videos",
	];

	protected $triggerActions = [
		"after" => [
			"insert",
			"update",
		],
		"before" => [
			"delete",
		],
	];

	protected $queries = [];

	public function up()
	{
		$this
			->proceduresUp()
			->triggersUp()
			->go();
	}

	public function down()
	{
		$this
			->proceduresDown()
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

	protected function proceduresUp()
	{
		$this->queries[] = "DROP PROCEDURE IF EXISTS `{$this->procedureName}`";

		$q = "CREATE PROCEDURE `{$this->procedureName}`(IN media_table_name VARCHAR(100), alb_id INT, diff INT)\nBEGIN\n";

		foreach ($this->triggerTables as $table)
		{
			$q .= "IF media_table_name = '{$table}' THEN
					UPDATE albums SET
						`{$table}_count` = diff + (SELECT COUNT(id) FROM {$table} WHERE album_id = alb_id and visible = '1')
					WHERE id = alb_id;
				END IF;\n";
		}

		$q .= "END";

		$this->queries[] = $q;

		/* great solution, but doesn't work in triggers
			SET @sql = CONCAT('UPDATE albums SET `', media_table_name, '_count` = (SELECT COUNT(id) FROM ', media_table_name, ' WHERE album_id = ? and visible = 1);');
			PREPARE stmt FROM @sql;
			SET @param1 = album_id;
			EXECUTE s1 USING @param1;
		 */

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
					$triggerName = "album_{$table}_{$when}_{$action}_trg";
					$source = $when == "before" ? "OLD" : "NEW";
					$diff = $when == "before" ? -1 : 0; // correction for BEFORE DELETE

					$this->queries[] = "DROP TRIGGER IF EXISTS `{$triggerName}`";
					$this->queries[] = "CREATE TRIGGER `{$triggerName}` {$when} {$action} ON {$table}
					FOR EACH ROW
					BEGIN
						CALL {$this->procedureName}('{$table}', {$source}.album_id, {$diff});
					END";
				}
			}
		}

		return $this;
	}

	protected function proceduresDown()
	{
		$this->queries[] = "DROP PROCEDURE IF EXISTS `{$this->procedureName}`";

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
					$triggerName = "album_{$table}_{$when}_{$action}_trg";
					$this->queries[] = "DROP TRIGGER IF EXISTS `{$triggerName}`";
				}
			}
		}

		return $this;
	}
}