<?php

namespace diCore\Database\Tool;

use diCore\Data\Config;
use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Entity\DiMigrationsLog\Model;
use diCore\Entity\DiMigrationsLog\Collection;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;
use diCore\Traits\BasicCreate;

class MigrationsManager
{
    use BasicCreate;

	const logTable = 'di_migrations_log';
	const dirChmod = 0775;
	const fileChmod = 0664;

	const defaultFolder = '_cfg/migrations';
	protected static $localFolder;

	const FOLDER_LOCAL = 1;
	const FOLDER_CORE_MIGRATIONS = 2;

	public static $foldersIdsAr = [
		self::FOLDER_LOCAL,
		self::FOLDER_CORE_MIGRATIONS,
	];
    public static $customFoldersIdsAr = [];

	/** @var \diDB */
	private $db;

	public function __construct()
	{
		global $db;

		$this->db = $db;

		$this
			->initTables()
			->initFolder();
	}

    public static function getFolderIds()
    {
        return array_merge(static::$foldersIdsAr, static::$customFoldersIdsAr);
    }

	protected function initFolder()
	{
		static::$localFolder = Config::getSourcesFolder() . static::defaultFolder;

		return $this;
	}

	public static function getClassNameByIdx($idx)
	{
		return 'diMigration_' . $idx;
	}

	public static function getIdxByFileName($fn)
	{
		$name = substr(basename($fn), 0, -4);
		$idx = substr($name, 0, strpos($name, '_'));

		return $idx;
	}

	public static function getMigrationsInFolder($folder, $idx = null)
	{
		if (is_numeric($folder)) {
			$folder = static::getFolderById($folder);
		}

		return array_reverse(glob_recursive(StringHelper::slash($folder) . ($idx ?: '*') . '_*.php'));
	}

	public static function getSubFolders($folder)
	{
		if (is_numeric($folder)) {
			$folder = static::getFolderById($folder);
		}

		$contents = FileSystemHelper::folderContents($folder, true, true);

		return $contents['d'];
	}

	public function getClassFileNameByIdx($idx)
	{
		$files = [];

		foreach (static::getFolderIds() as $folderId) {
			$files = array_merge($files, static::getMigrationsInFolder($folderId, $idx));
		}

		if (count($files) == 0) {
			throw new \Exception("No migrations with idx '$idx' found");
		} elseif (count($files) > 1) {
			throw new \Exception("More that just 1 migration found with idx '$idx': " . join(", ", $files));
		}

		return $files[0];
	}

	protected function getSimpleTemplate()
	{
		return <<<EOF
<?php
class %s extends \diCore\Database\Tool\Migration
{
	public static \$idx = '%s';
	public static \$name = '%s';

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

    protected function getLocalizationTemplate()
    {
        return <<<EOF
<?php
class %s extends \diCore\Database\Tool\LocalizationMigration
{
	public static \$idx = "%s";
	public static \$name = "%s";

	protected \$names = [
		'',
		'',
	];

	public function up()
	{
		// migration code here
	}
}
EOF;
    }

	protected function getTemplate($idx, $name, $folder)
	{
        switch ($folder) {
            case 'localization':
                return $this->getLocalizationTemplate();

            default:
                return $this->getSimpleTemplate();
        }
	}

	public function createMigration($idx, $name, $folder = '')
	{
		$contents = $this->getTemplate($idx, $name, $folder);

		$fullFolder = StringHelper::slash(static::$localFolder);

		if ($folder) {
			$fullFolder .= StringHelper::slash($folder);

			if (!is_dir($fullFolder)) {
				FileSystemHelper::createTree(static::$localFolder, $folder, static::dirChmod);
			}
		}

		$contents = sprintf($contents, static::getClassNameByIdx($idx), $idx, $name);
		$fn = $fullFolder . static::getMigrationFileName($idx, $name);

		file_put_contents($fn, $contents);
		chmod($fn, static::fileChmod);
	}

	public static function getMigrationFileName($idx, $name)
	{
		$s = $idx . '_' . $name;

        $s = str_replace([" ", "/", "\\"], "-", $s);
        $s = preg_replace("/\&\#?[a-z0-9]+\;/", "", $s);
		$s = transliterate_rus_to_eng($s);
		$s = preg_replace('/[^a-z0-9_-]/i', '', $s);
		$s = preg_replace('/-{2,}/', "-", $s);
		$s = preg_replace('/_{2,}/', "_", $s);

		return $s . ".php";
	}

	/**
	 * @return \diDB
	 */
	protected function getDb()
	{
		return $this->db;
	}

	/**
	 * @param $id integer
	 * return string
	 * @throws \Exception
	 */
	public static function getFolderById($id)
	{
		switch ($id) {
			case self::FOLDER_LOCAL:
				return static::getLocalFolder();

			case self::FOLDER_CORE_MIGRATIONS:
				return static::getCoreMigrationsFolder();

			default:
				throw new \Exception("Undefined folder id#$id");
		}
	}

	public static function getLocalFolder()
	{
		return static::$localFolder;
	}

	public static function getCoreMigrationsFolder()
	{
		return dirname(__FILE__, 6) . '/migrations/';
	}

	private function getCreateTableSql()
    {
        $charset = Config::getDbEncoding();
        $collation = Config::getDbCollation();

        switch (Connection::get()::getEngine()) {
            case Engine::SQLITE:
                return [
                    "CREATE TABLE IF NOT EXISTS `" . static::logTable . "`(
                        id integer not null primary key autoincrement,
                        admin_id integer,
                        idx varchar(100),
                        name varchar(250),
                        direction tinyint,
                        date timestamp default CURRENT_TIMESTAMP
                    );",
                    "CREATE INDEX IF NOT EXISTS `" . static::logTable . "_idx` ON `" . static::logTable . "` (idx);",
                ];

            default:
                return [
                    "CREATE TABLE IF NOT EXISTS `" . static::logTable . "`(
                        id bigint not null auto_increment,
                        admin_id bigint,
                        idx varchar(100),
                        name varchar(250),
                        direction tinyint,
                        date timestamp not null default CURRENT_TIMESTAMP,
                        index main_idx(idx),
                        primary key(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
                ];
        }
    }

	private function initTables()
	{
	    foreach ($this->getCreateTableSql() as $sql) {
            $res = $this->getDb()->q($sql);
        }

		if (!$res) {
			throw new \Exception("Unable to init Table: " . $this->getDb()->getLogStr());
		}

		return $this;
	}

	/**
	 * @return Migration
	 */
	private function getMigrationObject($idx)
	{
		$className = static::getClassNameByIdx($idx);
		$fn = $this->getClassFileNameByIdx($idx);

		include($fn);

		return new $className();
	}

	public function run($idx, $state)
	{
		if (!$idx) {
			throw new \Exception("Empty IDX of migration");
		}

		$executed = $this->wasExecuted($idx);

		if ($state && $executed) {
			throw new \Exception("Migration '$idx' has been already executed");
		} elseif (!$state && !$executed) {
			throw new \Exception("Migration '$idx' has not been executed yet");
		}

		$migration = $this->getMigrationObject($idx);

		if ($migration->run($state)) {
			$this->logExecution($migration, $state);
		}

		return $this;
	}

	private function logExecution(Migration $migration, $state)
	{
		$adminUser = \diAdminUser::create();

		/** @var Model $log */
		$log = \diModel::create(\diTypes::di_migrations_log);
		$log
			->setAdminId($adminUser->getModel()->getId())
			->setIdx($migration::$idx)
			->setName($migration::$name)
			->setDirection($state ? Migration::UP : Migration::DOWN)
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
		$col = Collection::create();

		if ($idx) {
			$col
				->filterByIdx($idx)
				->orderById('desc');

			return $col->getFirstItem();
		} else {
			return $col->getNewEmptyItem();
		}
	}

	public function wasExecuted($idx)
	{
		$log = $this->getLastLogByIdx($idx);

		return $log->exists() && $log->getDirection() == Migration::UP;
	}

	public function whenExecuted($idx)
	{
		$log = $this->getLastLogByIdx($idx);

		return $log->exists() && $log->getDirection() == Migration::UP
			? $log->getDate()
			: null;
	}
}
