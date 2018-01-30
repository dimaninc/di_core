<?php

use diCore\Data\Config;
use diCore\Entity\DiMigrationsLog\Model;
use diCore\Entity\DiMigrationsLog\Collection;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

class diMigrationsManager
{
	const logTable = "di_migrations_log";
	const childClassName = "diCustomMigrationsManager";
	const dirChmod = 0775;
	const fileChmod = 0664;

	const defaultFolder = "_cfg/migrations";
	protected static $localFolder;

	const FOLDER_LOCAL = 1;
	const FOLDER_CORE_MIGRATIONS = 2;

	public static $foldersIdsAr = [
		self::FOLDER_LOCAL,
		self::FOLDER_CORE_MIGRATIONS,
	];

	/** @var diDB */
	private $db;

	public function __construct()
	{
		global $db;

		$this->db = $db;

		$this
			->initTables()
			->initFolder();
	}

	public static function create()
	{
		$mmClassName = \diLib::exists(self::childClassName)
			? self::childClassName
			: "diMigrationsManager";

		return new $mmClassName();
	}

	protected function initFolder()
	{
		self::$localFolder = Config::getSourcesFolder() . self::defaultFolder;

		return $this;
	}

	public static function getClassNameByIdx($idx)
	{
		return "diMigration_" . $idx;
	}

	public static function getIdxByFileName($fn)
	{
		$name = substr(basename($fn), 0, -4);
		$idx = substr($name, 0, strpos($name, "_"));

		return $idx;
	}

	public static function getMigrationsInFolder($folder, $idx = null)
	{
		if (is_numeric($folder))
		{
			$folder = self::getFolderById($folder);
		}

		return array_reverse(glob_recursive(StringHelper::slash($folder) . ($idx ?: "*") . "_*.php"));
	}

	public static function getSubFolders($folder)
	{
		if (is_numeric($folder))
		{
			$folder = self::getFolderById($folder);
		}

		$contents = FileSystemHelper::folderContents($folder, true, true);

		return $contents["d"];
	}

	public function getClassFileNameByIdx($idx)
	{
		$files = array();

		foreach (static::$foldersIdsAr as $folderId)
		{
			$files = array_merge($files, self::getMigrationsInFolder($folderId, $idx));
		}

		if (count($files) == 0)
		{
			throw new \Exception("No migrations with idx '$idx' found");
		}
		elseif (count($files) > 1)
		{
			throw new \Exception("More that just 1 migration found with idx '$idx': " . join(", ", $files));
		}

		return $files[0];
	}

	protected function getSimpleTemplate()
	{
		return <<<EOF
<?php
class %s extends diMigration
{
	public static \$idx = "%s";
	public static \$name = "%s";

	public function up()
	{
		// migration code here
	}

	public function down()
	{
		// migration rollback code here
	}
}
EOF;
	}

	protected function getTemplate($idx, $name, $folder)
	{
		return $this->getSimpleTemplate();
	}

	public function createMigration($idx, $name, $folder = "")
	{
		$contents = $this->getTemplate($idx, $name, $folder);

		$fullFolder = StringHelper::slash(self::$localFolder);

		if ($folder)
		{
			$fullFolder .= StringHelper::slash($folder);

			if (!is_dir($fullFolder))
			{
				FileSystemHelper::createTree(self::$localFolder, $folder, self::dirChmod);
			}
		}

		$contents = sprintf($contents, self::getClassNameByIdx($idx), $idx, $name);
		$fn = $fullFolder . self::getMigrationFileName($idx, $name);

		file_put_contents($fn, $contents);
		chmod($fn, self::fileChmod);
	}

	public static function getMigrationFileName($idx, $name)
	{
		$s = $idx . "_" . $name;

		$s = str_replace(array(" ", "/", "\\"), "-", $s);
		$s = preg_replace("/\&\#?[a-z0-9]+\;/", "", $s);
		$s = transliterate_rus_to_eng($s);
		$s = preg_replace('/[^a-z0-9_-]/i', '', $s);
		$s = preg_replace('/-{2,}/', "-", $s);
		$s = preg_replace('/_{2,}/', "_", $s);

		return $s . ".php";
	}

	/**
	 * @return diDB
	 */
	protected function getDb()
	{
		return $this->db;
	}

	/**
	 * @param $id integer
	 * return string
	 * @throws Exception
	 */
	public static function getFolderById($id)
	{
		switch ($id)
		{
			case self::FOLDER_LOCAL:
				return static::getLocalFolder();

			case self::FOLDER_CORE_MIGRATIONS:
				return static::getCoreMigrationsFolder();

			default:
				throw new Exception("Undefined folder id#$id");
		}
	}

	public static function getLocalFolder()
	{
		return static::$localFolder;
	}

	public static function getCoreMigrationsFolder()
	{
		return dirname(dirname(dirname(__FILE__))) . "/migrations/";
	}

	private function initTables()
	{
		$res = $this->getDb()->q("CREATE TABLE IF NOT EXISTS `".static::logTable."`(
			id bigint not null auto_increment,
			admin_id bigint,
			idx varchar(100),
			name varchar(250),
			direction tinyint,
			date timestamp not null default CURRENT_TIMESTAMP,
			index main_idx(idx),
			primary key(id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

		if (!$res)
		{
			throw new Exception("Unable to init Table: " . $this->getDb()->getLogStr());
		}

		return $this;
	}

	/**
	 * @return diMigration
	 */
	private function getMigrationObject($idx)
	{
		$className = self::getClassNameByIdx($idx);
		$fn = $this->getClassFileNameByIdx($idx);

		include($fn);

		return new $className();
	}

	public function run($idx, $state)
	{
		if (!$idx)
		{
			throw new Exception("Empty IDX of migration");
		}

		$executed = $this->wasExecuted($idx);

		if ($state && $executed)
		{
			throw new Exception("Migration '$idx' has been already executed");
		}
		elseif (!$state && !$executed)
		{
			throw new Exception("Migration '$idx' has not been executed yet");
		}

		$migration = $this->getMigrationObject($idx);

		if ($migration->run($state))
		{
			$this->logExecution($migration, $state);
		}

		return $this;
	}

	private function logExecution(diMigration $migration, $state)
	{
		$adminUser = \diAdminUser::create();

		/** @var Model $log */
		$log = \diModel::create(\diTypes::di_migrations_log);
		$log
			->setAdminId($adminUser->getModel()->getId())
			->setIdx($migration::$idx)
			->setName($migration::$name)
			->setDirection($state ? \diMigration::UP : \diMigration::DOWN)
			->save();

		return $this;
	}

	/**
	 * @param $idx
	 * @return Model
	 * @throws \Exception
	 */
	protected function getLastLogByIdx($idx)
	{
		/** @var Collection $col */
		$col = \diCollection::create(\diCore\Data\Types::di_migrations_log);

		if ($idx)
		{
			$col
				->filterByIdx($idx)
				->orderById('desc');

			return $col->getFirstItem();
		}
		else
		{
			return $col->getNewEmptyItem();
		}
	}

	public function wasExecuted($idx)
	{
		$log = $this->getLastLogByIdx($idx);

		return $log->exists() && $log->getDirection() == \diMigration::UP;
	}

	public function whenExecuted($idx)
	{
		$log = $this->getLastLogByIdx($idx);

		return $log->exists() && $log->getDirection() == \diMigration::UP
			? $log->getDate()
			: null;
	}
}