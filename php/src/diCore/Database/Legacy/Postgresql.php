<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.12.2020
 * Time: 09:33
 */

namespace diCore\Database\Legacy;

use diCore\Helper\ArrayHelper;

class Postgresql extends Pdo
{
    protected $driver = 'pgsql';
    const CHARSET_INIT_NEEDED = false;
    const DEFAULT_PORT = 5432;

    const QUOTE_TABLE = '"';
    const QUOTE_FIELD = '"';
    const QUOTE_VALUE = "'";

    const DSN_DELIMITER = ';';

    protected function getDSN()
    {
        $ar = [
            'host' => $this->host,
            'port' => $this->port,
            'dbname' => $this->dbname,
            'user' => $this->username,
            'password' => $this->password,
        ];

        if ($this->ssl) {
            $ar['sslmode'] = 'require';

            if ($this->sslCert) {
                $ar['sslcert'] = $this->sslCert;
            }

            if ($this->sslKey) {
                $ar['sslkey'] = $this->sslKey;
            }
        }

        $ar = ArrayHelper::mapAssoc(function ($key, $value) {
            return [$key, urlencode($value ?? '')];
        }, $ar);

        $dsn = $this->driver . ':' . ArrayHelper::toString($ar, '=', ';');

        return $dsn;
    }

    protected function databaseCreationAllowed()
    {
        return false;
    }

    protected function __connect()
    {
        $res = parent::__connect();

        try {
            $this->link->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
        } catch (\Exception $e) {
            $this->_log($e->getMessage(), false);
        }

        return $res;
    }

    public function getTablesInfo()
    {
        $ar = [];

        $tables = $this->q("SELECT schemaname,relname,n_live_tup 
FROM pg_stat_user_tables 
ORDER BY relname");
        while ($tables && ($table = $this->fetch_array($tables))) {
            $tableName = $table['relname'];
            $size = $this->fetch_ar(
                $this->q("select pg_relation_size('{$tableName}')")
            );
            $indexSize = $this->fetch_ar(
                $this->q("select pg_indexes_size('{$tableName}')")
            );

            $ar[] = [
                'name' => $tableName,
                'is_view' => false,
                'rows' => $table['n_live_tup'] ?? 0,
                'size' => $size['pg_relation_size'] ?? 0,
                'index_size' => $indexSize['pg_indexes_size'] ?? 0,
            ];
        }

        return $ar;
    }

    public function getTableNames()
    {
        $ar = [];

        $tables = $this->q(
            'select relname from pg_stat_user_tables order by relname'
        );
        while ($tables && ($table = $this->fetch_array($tables))) {
            $ar[] = $table['relname'];
        }

        return $ar;
    }

    public function getFields($table)
    {
        $fields = [];

        $rs = $this->q("SELECT column_name,data_type,character_maximum_length,ordinal_position
FROM information_schema.columns
WHERE table_name = '{$table}'
ORDER BY ordinal_position ASC");
        while ($r = $this->fetch_array($rs)) {
            $fields[$r['column_name']] = $r['data_type'];
        }

        return $fields;
    }

    public static function insertUpdateQueryBeginning($keyField = null)
    {
        $keyField = $keyField ?: 'id';

        return "ON CONFLICT ($keyField) DO UPDATE SET";
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

    protected function getJsonForStructure($value)
    {
        $s = json_encode($value);

        return "'$s'::jsonb";
    }
}
