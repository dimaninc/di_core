<?php

use diCore\Data\Config;
use diCore\Tool\Logger;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.02.16
 * Time: 12:30
 */
class diMYSQLi extends diMYSQL
{
    /** @var MySQLi */
    protected $link;

    protected function __connect()
    {
        $time1 = utime();

        $this->link = @new \mysqli(
            $this->host,
            $this->username,
            $this->password
        );

        if (!$this->link || $this->link->connect_error) {
            $message =
                "diMySQLi: Unable to connect to host $this->host: " .
                $this->link->connect_error;

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        if (Config::isInitiating()) {
            $this->__q($this->getCreateDatabaseQuery());
        }

        if (!$this->link->select_db($this->dbname)) {
            $message = "unable to select database $this->dbname";

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
        if (!$this->link->close()) {
            $message = 'unable to close connection';

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        return true;
    }

    protected function __error()
    {
        return $this->link->error ?? $this->link->connect_error;
    }

    protected function __q($q)
    {
        try {
            $res = $this->link->query($q);
        } catch (\Exception $e) {
            Logger::getInstance()->log(
                "Error executing query `$q`: {$e->getMessage()}"
            );
            $res = false;
        }
        $this->lastInsertId = $this->__insert_id() ?: $this->lastInsertId;

        return $res;
    }

    protected function __rq($q)
    {
        $res = $this->link->real_query($q);
        $this->lastInsertId = $this->__insert_id() ?: $this->lastInsertId;

        return $res;
    }

    protected function __mq($q)
    {
        $ar = [];

        if ($this->link->multi_query($q)) {
            do {
                $a = $this->link->store_result();

                if ($a) {
                    $ar[] = $a;

                    $a->free();
                }
            } while ($this->link->more_results() && $this->link->next_result());
        }

        if ($this->link->errno) {
            return false;
        } else {
            return $ar;
        }
    }

    protected function __mq_flush()
    {
        while ($this->link->next_result()) {
        }
    }

    /**
     * @param $rs \mysqli_result
     */
    protected function __reset(&$rs)
    {
        if ($this->count($rs)) {
            $rs->data_seek(0);
        }
    }

    /**
     * @param $rs \mysqli_result
     */
    protected function __fetch($rs)
    {
        return $rs ? $rs->fetch_object() : false;
    }

    /**
     * @param $rs \mysqli_result
     */
    protected function __fetch_array($rs)
    {
        return $rs ? $rs->fetch_assoc() : false;
    }

    /**
     * @param $rs \mysqli_result
     */
    protected function __count($rs)
    {
        return $rs ? $rs->num_rows : false;
    }

    protected function __insert_id()
    {
        return $this->link->insert_id;
    }

    protected function __affected_rows()
    {
        return $this->link->affected_rows;
    }

    public function escape_string($s, $binary = false)
    {
        return $s ? $this->link->escape_string($s) : $s;
    }

    protected function __set_charset($name)
    {
        return $this->link->set_charset($name);
    }

    protected function __get_charset()
    {
        return $this->link->character_set_name();
    }
}
