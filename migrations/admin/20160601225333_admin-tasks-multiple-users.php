<?php

use diCore\Entity\AdminTask\Collection;
use diCore\Entity\AdminTask\Model;

class diMigration_20160601225333 extends diMigration
{
	public static $idx = "20160601225333";
	public static $name = "Admin tasks: participants users";

	protected $linkTable = 'admin_task_participants';

	public function up()
	{
		try
		{
			$this
				->createTable();
				//->copyData()
				//->dropField();
		}
		catch (Exception $e)
		{
			echo $e->getMessage();

			return false;
		}

		return true;
	}

	public function down()
	{
		try
		{
			$this
				//->addField()
				//->copyDataBack()
				->dropTable();
		}
		catch (Exception $e)
		{
			echo $e->getMessage();

			return false;
		}

		return true;
	}

	protected function copyData()
	{
		/** @var Model $task */
		foreach (diCollection::create(diTypes::admin_task) as $task)
		{
			if (!$task->hasAdminId())
			{
				continue;
			}

			/** @var diAdminTaskParticipantModel $m */
			$m = diModel::create(diTypes::admin_task_participant);

			$m
				->setAdminId($task->getAdminId())
				->setTaskId($task->getId())
				->save();
		}

		return $this;
	}

	protected function copyDataBack()
	{
		/** @var Collection $tasks */
		$tasks = diCollection::create(diTypes::admin_task);

		/** @var diAdminTaskParticipantModel $participant */
		foreach (diCollection::create(diTypes::admin_task_participant) as $participant)
		{
			/** @var Model $task */
			$task = $tasks->getById($participant->getTaskId());

			$task
				->setAdminId($participant->getAdminId())
				->save();
		}

		return $this;
	}

	protected function dropField()
	{
		$this->getDb()->q("ALTER TABLE `admin_tasks` DROP COLUMN `admin_id`");

		return $this;
	}

	protected function addField()
	{
		$this->getDb()->q("ALTER TABLE `admin_tasks` ADD COLUMN `admin_id` int default '0' AFTER `id`");

		return $this;
	}

	protected function createTable()
	{
		$this->getDb()->q("CREATE TABLE IF NOT EXISTS admin_task_participants(
  			admin_id int,
  			task_id int,
  			UNIQUE index idx(admin_id,task_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");

		return $this;
	}

	protected function dropTable()
	{
		$this->getDb()->q("DROP TABLE IF EXISTS admin_task_participants;");

		return $this;
	}
}