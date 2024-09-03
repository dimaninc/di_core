<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.05.2017
 * Time: 15:37
 */

namespace diCore\Database\Legacy;

class Sqlite extends Pdo
{
    protected $driver = 'sqlite';
    const CHARSET_INIT_NEEDED = false;

    protected function getDSN()
    {
        return $dsn = "{$this->driver}:{$this->dbname}";
    }

    public function getTablesInfo()
    {
        $ar = [];

        foreach ($this->getTableNames() as $table) {
            //$res = $this->fetch_ar($this->q("SELECT SUM(\"pgsize\") FROM \"dbstat\" WHERE name = '{$table}'"));

            $ar[] = [
                'name' => $table,
                'is_view' => false, // does sqlite support views?
                'rows' => 0,
                'size' => 0,
                'index_size' => 0,
            ];
        }

        return $ar;
    }

    public function getTableNames()
    {
        $ar = [];

        $tables = $this->q(
            "SELECT * FROM sqlite_master WHERE type = 'table' ORDER BY name ASC"
        );
        while ($tables && ($table = $this->fetch_array($tables))) {
            $ar[] = $table['name'];
        }

        return $ar;
    }

    public function getFields($table)
    {
        $fields = [];

        $rs = $this->q('PRAGMA table_info(' . $this->escapeTable($table) . ')');
        while ($r = $this->fetch_array($rs)) {
            $fields[$r['name']] = $r['type'];
        }

        return $fields;
    }

    protected function getStartTransactionQuery()
    {
        return 'BEGIN';
    }

    public function getUpdateSingleLimit()
    {
        return '';
    }

    public function getDeleteSingleLimit()
    {
        return '';
    }
}
