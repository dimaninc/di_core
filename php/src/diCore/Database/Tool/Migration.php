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
                $controller = \diBaseController::autoCreate('db', 'restore', [], true);
                static::assertRestored(
                    $file,
                    $controller->getResponse()->getReturnData()
                );
            } else {
                $this->getDb()->q(
                    file_get_contents(StringHelper::slash($folder) . $file)
                );
            }
        }

        return $this;
    }

    /**
     * Db::restoreAction сбрасывает лог перед каждым оператором и копит ошибки в своём
     * результате, поэтому Migration::run по логу видит в лучшем случае ошибку последнего
     * оператора. Без этой проверки упавший файл давал «успешную» миграцию, которую
     * штатно уже не перезапустить.
     *
     * @param string $file
     * @param mixed $result результат restoreAction: ['ok' => bool, 'errors' => string[]]
     */
    public static function assertRestored($file, $result)
    {
        if (is_array($result) && array_key_exists('ok', $result) && !$result['ok']) {
            throw new \Exception(
                "SQL file '$file' failed: " .
                    join('; ', (array) ($result['errors'] ?? []))
            );
        }
    }

    protected function getDb()
    {
        return Connection::get(static::CONNECTION_NAME)->getDb();
    }

    protected function columnExists(string $table, string $column): bool
    {
        return $this->getDb()->columnExists($table, $column);
    }

    protected function indexExists(string $table, string $index): bool
    {
        return $this->getDb()->indexExists($table, $index);
    }

    protected function fkExists(string $table, string $foreignKey): bool
    {
        return $this->getDb()->fkExists($table, $foreignKey);
    }
}
