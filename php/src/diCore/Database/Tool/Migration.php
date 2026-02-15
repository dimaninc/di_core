<?php

namespace diCore\Database\Tool;

use diCore\Controller\Db;
use diCore\Data\Config;
use diCore\Database\Connection;
use diCore\Helper\StringHelper;

abstract class Migration
{
    const UP = 1;
    const DOWN = 0;

    const DB_FOLDER = 'db/dump/';
    const CONNECTION_NAME = null;

    public static $idx;
    public static $name;

    abstract public function up();
    abstract public function down();

    protected function upWrapper()
    {
        return $this->up();
    }

    protected function downWrapper()
    {
        return $this->down();
    }

    public function run($state)
    {
        $this->getDb()->resetLog();

        $result = $state ? $this->upWrapper() : $this->downWrapper();

        if ($this->getDb()->getLog() || $result === false) {
            $idx = static::$idx;
            throw new \Exception(
                "Error during migration#$idx: {$this->getDb()->getLogStr()}"
            );
        }

        return $this;
    }

    protected function executeSql(string $query)
    {
        $this->getDb()->q($query);

        return $this;
    }

    protected function executeSqlFile($files, $folder = null)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        if ($folder === null) {
            $folder = Config::getDatabaseDumpFolder() . static::DB_FOLDER;
        }

        $folderId = null;

        foreach (Db::$foldersIdsAr as $id) {
            if (StringHelper::startsWith($folder, Db::getFolderById($id))) {
                $folderId = $id;
                $folder = mb_substr($folder, mb_strlen(Db::getFolderById($id)));

                break;
            }
        }

        foreach ($files as $file) {
            if ($folderId !== null) {
                $_GET['file'] = $folder . $file;
                $_GET['folderId'] = $folderId;
                \diBaseController::autoCreate('db', 'restore', [], true);
            } else {
                $this->getDb()->q(
                    file_get_contents(StringHelper::slash($folder) . $file)
                );
            }
        }

        return $this;
    }

    protected function getDb()
    {
        return Connection::get(static::CONNECTION_NAME)->getDb();
    }
}
