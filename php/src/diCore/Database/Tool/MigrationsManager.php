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

    public static $foldersIdsAr = [self::FOLDER_LOCAL, self::FOLDER_CORE_MIGRATIONS];
    public static $customFoldersIdsAr = [];

    const useCache = true;
    protected static $lastLogCache = [];
    protected static $cacheAllowed = false;

    public function __construct()
    {
        $this->initTables()->initFolder();
    }

    public static function shouldUseCache()
    {
        return static::useCache && static::$cacheAllowed;
    }

    public static function setCacheAllowed($state)
    {
        self::$cacheAllowed = $state;
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

    public function getMigrationsInFolder($folder, $options = [])
    {
        $options = extend(
            [
                'idxOrWildcard' => null,
                'sort' => false, // false | 'asc' | 'desc'
                'filterNotExecuted' => false,
                'filterExecuted' => false,
            ],
            $options
        );

        if (is_numeric($folder)) {
            $folder = static::getFolderById($folder);
        }

        $wildcard = $options['idxOrWildcard'] ?: '*';
        $ar = glob_recursive(StringHelper::slash($folder) . $wildcard . '_*.php');

        if ($options['filterNotExecuted']) {
            $ar = array_filter($ar, function ($name) {
                return !$this->wasExecuted(static::getIdxByFileName($name));
            });
        }

        if ($options['filterExecuted']) {
            $ar = array_filter($ar, function ($name) {
                return $this->wasExecuted(static::getIdxByFileName($name));
            });
        }

        if ($options['sort']) {
            usort($ar, function ($a, $b) {
                return static::getIdxByFileName($a) < static::getIdxByFileName($b)
                    ? -1
                    : 1;
            });

            if (strtolower($options['sort']) === 'desc') {
                $ar = array_reverse($ar);
            }
        }

        return $ar;
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
            $files = array_merge(
                $files,
                $this->getMigrationsInFolder($folderId, [
                    'idxOrWildcard' => $idx,
                ])
            );
        }

        if (count($files) == 0) {
            throw new \Exception("No migrations with idx '$idx' found");
        } elseif (count($files) > 1) {
            throw new \Exception(
                "More that just 1 migration found with idx '$idx': " .
                    join(', ', $files)
            );
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
                FileSystemHelper::createTree(
                    static::$localFolder,
                    $folder,
                    static::dirChmod
                );
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

        $s = str_replace([' ', '/', '\\'], '-', $s);
        $s = preg_replace('/\&\#?[a-z0-9]+\;/', '', $s);
        $s = transliterate_rus_to_eng($s);
        $s = preg_replace('/[^a-z0-9_-]/i', '', $s);
        $s = preg_replace('/-{2,}/', '-', $s);
        $s = preg_replace('/_{2,}/', '_', $s);

        return $s . '.php';
    }

    protected function getConn()
    {
        return Connection::get();
    }

    /**
     * @return \diDB
     */
    protected function getDb()
    {
        return $this->getConn()->getDb();
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
        $t = static::logTable;

        // CREATE TABLE di_migrations_log
        switch ($this->getConn()::getEngine()) {
            case Engine::SQLITE:
                return [
                    "CREATE TABLE IF NOT EXISTS `$t`(
                        id integer not null primary key autoincrement,
                        admin_id integer,
                        idx varchar(100),
                        name varchar(250),
                        direction tinyint,
                        date timestamp default CURRENT_TIMESTAMP
                    );",
                    "CREATE INDEX IF NOT EXISTS `{$t}_idx` ON `$t` (idx);",
                ];

            case Engine::POSTGRESQL:
                return [
                    "CREATE TABLE IF NOT EXISTS \"$t\"(
                        id SERIAL primary key,
                        admin_id bigint,
                        idx varchar(100),
                        name varchar(250),
                        direction smallint,
                        date timestamp default CURRENT_TIMESTAMP
                    );",
                    "CREATE INDEX IF NOT EXISTS idx_$t ON $t (idx);",
                ];

            default:
                return [
                    "CREATE TABLE IF NOT EXISTS `$t`(
                        id bigint not null auto_increment,
                        admin_id bigint,
                        idx varchar(100),
                        name varchar(250),
                        direction tinyint,
                        date timestamp not null default CURRENT_TIMESTAMP,
                        index main_idx(idx),
                        primary key(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation",
                ];
        }
    }

    private function initTables()
    {
        foreach ($this->getCreateTableSql() as $sql) {
            $res = $this->getDb()->q($sql);
        }

        if (!$res) {
            throw new \Exception(
                'Unable to init Table: ' . $this->getDb()->getLogStr()
            );
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

        include $fn;

        return new $className();
    }

    public function run($idx, $state)
    {
        if (!$idx) {
            throw new \Exception('Empty IDX of migration');
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

    public function getNewList()
    {
        return $this->getMigrationsInFolder(static::FOLDER_LOCAL, [
            'sort' => 'asc',
            'filterNotExecuted' => true,
        ]);
    }

    public function upNew()
    {
        $ar = $this->getNewList();

        foreach ($ar as $name) {
            $this->run(static::getIdxByFileName($name), true);
        }

        return $ar;
    }

    public function upLastNotExecuted()
    {
        $ar = $this->getMigrationsInFolder(static::FOLDER_LOCAL, [
            'sort' => 'desc',
            'filterNotExecuted' => true,
        ]);

        $name = $ar[0] ?? null;

        if ($name) {
            $this->run(static::getIdxByFileName($name), true);
        }

        return $name;
    }

    public function downLast()
    {
        $ar = $this->getMigrationsInFolder(static::FOLDER_LOCAL, [
            'sort' => 'desc',
            'filterExecuted' => true,
        ]);

        $name = $ar[0] ?? null;

        if ($name) {
            $this->run(static::getIdxByFileName($name), false);
        }

        return $name;
    }

    private function logExecution(Migration $migration, $state)
    {
        $adminUser = \diAdminUser::create();

        Model::create()
            ->setAdminId($adminUser->getModel()->getId())
            ->setIdx($migration::$idx)
            ->setName($migration::$name)
            ->setDirection($state ? Migration::UP : Migration::DOWN)
            ->setDate(\diDateTime::sqlFormat())
            ->save();

        return $this;
    }

    /**
     * @param $idx
     * @return Model|\diModel
     * @throws \Exception
     */
    protected function getLastLogByIdx($idx)
    {
        if (isset(self::$lastLogCache[$idx])) {
            return self::$lastLogCache[$idx];
        }

        if (static::shouldUseCache()) {
            return Model::create();
        }

        $col = Collection::create();

        if (!$idx) {
            return $col->getNewEmptyItem();
        }

        return $col
            ->filterByIdx($idx)
            ->orderById('desc')
            ->getFirstItem();
    }

    public function cacheLastLogsForIdx(array $idxAr)
    {
        if (!static::useCache) {
            return $this;
        }

        static::setCacheAllowed(true);

        $db = Collection::getConnection()->getDb();
        $t = static::logTable;
        $idx = $db::in($idxAr);
        $lastRs = $db->q("SELECT t1.*
FROM `$t` t1
INNER JOIN (
    SELECT idx, MAX(id) as max_id
    FROM `$t`
    WHERE idx $idx
    GROUP BY idx
) t2 ON t1.idx = t2.idx AND t1.id = t2.max_id;");

        while ($lastRs && ($last = $db->fetch_ar($lastRs))) {
            self::$lastLogCache[$last['idx']] = Model::create(Model::type, $last, [
                'readOnly' => true,
            ]);
        }

        return $this;
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
