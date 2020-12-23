<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.12.2020
 * Time: 09:33
 */

namespace diCore\Database\Legacy;

class Postgresql extends Pdo
{
    protected $driver = 'pgsql';
    const CHARSET_INIT_NEEDED = false;

    const QUOTE_TABLE = '"';
    const QUOTE_FIELD = '"';
    const QUOTE_VALUE = "'";

    protected function getDSN()
    {
        return $dsn = "{$this->driver}:host={$this->host};port={$this->port};dbname={$this->dbname};user={$this->username};password={$this->password}";
    }

    protected function databaseCreationAllowed()
    {
        return false;
    }

    protected function __connect()
    {
        $res = parent::__connect();

        $this->link->setAttribute(\PDO::ATTR_AUTOCOMMIT,1);

        return $res;
    }

    public function getTablesInfo()
    {
        $ar = [];

        $tables = $this->q("SELECT schemaname,relname,n_live_tup 
FROM pg_stat_user_tables 
ORDER BY relname ASC");
        while ($tables && $table = $this->fetch_array($tables)) {
            $tableName = $table['relname'];
            $size = $this->fetch_ar($this->q("select pg_relation_size('{$tableName}')"));
            $indexSize = $this->fetch_ar($this->q("select pg_indexes_size('{$tableName}')"));

            $ar[] = [
                'name' => $tableName,
                'is_view' => false,
                'rows' => $table['n_live_tup'],
                'size' => $size['pg_relation_size'],
                'index_size' => $indexSize['pg_indexes_size'],
            ];
        }

        return $ar;
    }

    public function getTableNames()
    {
        $ar = [];

        $tables = $this->q("select relname from pg_stat_user_tables order by relname");
        while ($tables && $table = $this->fetch_array($tables)) {
            $ar[] = $table['relname'];
        }

        return $ar;
    }

    public function getFields($table)
    {
        $fields = [];

        $rs = $this->q("SELECT column_name, data_type, character_maximum_length
FROM information_schema.columns
WHERE table_name = '{$table}'");
        while ($r = $this->fetch_array($rs)) {
            $fields[$r['column_name']] = $r['data_type'];
        }

        return $fields;
    }

    public static function insertUpdateQueryBeginning()
    {
        return 'ON CONFLICT (id) DO UPDATE SET';
    }

    public function getUpdateSingleLimit()
    {
        return '';
    }

    public function getDeleteSingleLimit()
    {
        return '';
    }

    protected function getStartTransactionQuery()
    {
        return '';
    }

    protected function getCommitTransactionQuery()
    {
        return '';
    }

    protected function getRollbackTransactionQuery()
    {
        return '';
    }

    public function lockTable($table)
    {
        return $this;
    }

    public function unlockTable($table = null)
    {
        return $this;
    }
}