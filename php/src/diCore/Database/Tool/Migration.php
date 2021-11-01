<?php

namespace diCore\Database\Tool;

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

    public function run($state)
    {
        $method = $state ? 'up' : 'down';

        $this->getDb()
            ->resetLog()
            ->startTransaction();

        try {
            $result = $this->$method();

            if ($this->getDb()->getLog() || $result === false) {
                $this->getDb()->rollbackTransaction();

                throw new \Exception('Error(s) during migration: ' . $this->getDb()->getLogStr());
            } else {
                $this->getDb()->commitTransaction();
            }
        } catch (\Exception $e) {
            $this->getDb()->rollbackTransaction();

            throw $e;
        }

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

        foreach ($files as $file) {
            $this->getDb()->q(file_get_contents(StringHelper::slash($folder) . $file));
        }

        return $this;
    }

    protected function getDb()
    {
        return Connection::get(static::CONNECTION_NAME)->getDb();
    }
}