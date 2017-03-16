<?php
abstract class diMigration
{
	const UP = 1;
	const DOWN = 0;

	/** @var diDB */
	private $db;

	public static $idx;
	public static $name;

	public function __construct()
	{
		global $db;

		$this->db = $db;
	}

	abstract public function up();
	abstract public function down();

	public function run($state)
	{
		$method = $state ? "up" : "down";

		$this->getDb()
			->resetLog()
			->startTransaction();

		try
		{
			$result = $this->$method();

			if ($this->getDb()->getLog() || $result === false)
			{
				$this->getDb()->rollbackTransaction();

				throw new Exception("Error(s) during migration: " . $this->getDb()->getLogStr());
			}
			else
			{
				$this->getDb()->commitTransaction();
			}
		}
		catch (Exception $e)
		{
			$this->getDb()->rollbackTransaction();

			throw $e;
		}

		return $this;
	}

	protected function getDb()
	{
		return $this->db;
	}
}