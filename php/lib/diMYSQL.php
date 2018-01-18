<?php

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

		if (!$this->link = mysql_connect($this->host, $this->username, $this->password))
		{
			return $this->_log("unable to connect to host $this->host");
		}

		if (defined("DI_CREATE_DATABASE") && DI_CREATE_DATABASE)
		{
			$this->__q($this->getCreateDatabaseQuery());
		}

		if (!mysql_select_db($this->dbname, $this->link))
		{
			return $this->_log("unable to select database $this->dbname");
		}

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("connect", $time2 - $time1);

		return true;
	}

	protected function __close()
	{
		if (!mysql_close($this->link))
		{
			return $this->_log("unable to close connection");
		}

		return true;
	}

	protected function __error()
	{
		return mysql_error();
	}

	protected function __q($q)
	{
		return mysql_query($q, $this->link);
	}

	protected function __rq($q)
	{
		return $this->__q($q);
	}

	protected function __mq($q)
	{
		throw new Exception("Unable to exec multi-query in simple mysql, mysqli needed");
	}

	protected function __mq_flush()
	{
		return true;
	}

	protected function __reset(&$rs)
	{
		if ($this->count($rs))
			mysql_data_seek($rs, 0);
	}

	protected function __fetch($rs)
	{
		return mysql_fetch_object($rs);
	}

	protected function __fetch_array($rs)
	{
		return mysql_fetch_assoc($rs);
	}

	protected function __count($rs)
	{
		return mysql_num_rows($rs);
	}

	protected function __insert_id()
	{
		return mysql_insert_id();
	}

	protected function __affected_rows()
	{
		return mysql_affected_rows();
	}

	public function escape_string($s)
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

	public function getTableNames()
	{
		$ar = [];

		$tables = $this->q("SHOW TABLES");
		while ($table = $this->fetch_array($tables))
		{
			$ar[] = current($table);
		}

		return $ar;
	}
}