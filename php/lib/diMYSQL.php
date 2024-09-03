<?php

use diCore\Data\Config;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.02.16
 * Time: 12:30
 */
class diMYSQL extends diDB
{
    const DEFAULT_PORT = 3306;

    protected function __connect()
    {
        $time1 = utime();

        if (
            !($this->link = mysql_connect(
                $this->host,
                $this->username,
                $this->password
            ))
        ) {
            $message = "diMySQL: Unable to connect to host $this->host";

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        if (Config::isInitiating()) {
            $this->__q($this->getCreateDatabaseQuery());
        }

        if (!mysql_select_db($this->dbname, $this->link)) {
            $message = "diMySQL: unable to select database $this->dbname";

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        $time2 = utime();
        $this->execution_time += $time2 - $time1;

        $this->time_log('connect', $time2 - $time1);

        return true;
    }

    protected function __close()
    {
        if (!mysql_close($this->link)) {
            $message = 'unable to close connection';

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        return true;
    }

    protected function __error()
    {
        return mysql_error();
    }

    protected function __q($q)
    {
        $res = mysql_query($q, $this->link);
        $this->lastInsertId = $this->__insert_id() ?: $this->lastInsertId;

        return $res;
    }

    protected function __rq($q)
    {
        return $this->__q($q);
    }

    protected function __mq($q)
    {
        throw new \Exception(
            'Unable to exec multi-query in simple mysql, mysqli needed'
        );
    }

    protected function __mq_flush()
    {
        return true;
    }

    protected function __reset(&$rs)
    {
        if ($this->count($rs)) {
            mysql_data_seek($rs, 0);
        }
    }

    protected function __fetch($rs)
    {
        return $rs ? mysql_fetch_object($rs) : false;
    }

    protected function __fetch_array($rs)
    {
        return $rs ? mysql_fetch_assoc($rs) : false;
    }

    protected function __count($rs)
    {
        return $rs ? mysql_num_rows($rs) : false;
    }

    protected function __insert_id()
    {
        return mysql_insert_id();
    }

    protected function __affected_rows()
    {
        return mysql_affected_rows();
    }

    public function escape_string($s, $binary = false)
    {
        return mysql_real_escape_string($s, $this->link);
    }

    protected function __set_charset($name)
    {
        return mysql_set_charset($name, $this->link);
    }

    protected function __get_charset()
    {
        return mysql_client_encoding($this->link);
    }

    public function getTablesInfo()
    {
        $ar = [];

        $rs = $this->q('SHOW TABLE STATUS');
        while ($r = $this->fetch($rs)) {
            $ar[] = [
                'name' => $r->Name,
                'is_view' => $r->Data_length === null && $r->Comment == 'VIEW',
                'rows' => $r->Rows,
                'size' => $r->Data_length,
                'index_size' => $r->Index_length,
            ];
        }

        return $ar;
    }

    public function getTableNames()
    {
        $ar = [];

        $tables = $this->q('SHOW TABLES');
        while ($table = $this->fetch_array($tables)) {
            $ar[] = current($table);
        }

        return $ar;
    }

    public function getFields($table)
    {
        $fields = [];

        $rs = $this->q('SHOW FIELDS FROM ' . $this->escapeTable($table));
        while ($r = $this->fetch($rs)) {
            $fields[$r->Field] = $r->Type;
        }

        return $fields;
    }

    public static function insertUpdateQueryEnding()
    {
        return ',id = LAST_INSERT_ID(id)';
    }

    public function lockTable($table, $mode = 'WRITE')
    {
        $this->__q("LOCK TABLES $table $mode");

        return $this;
    }

    public function unlockTable($table = null)
    {
        $this->__q('UNLOCK TABLES');

        return $this;
    }
}
