<?php

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

		if (!$this->link = new mysqli($this->host, $this->username, $this->password))
		{
			return $this->_log("unable to connect to host $this->host");
		}

		if (defined("DI_CREATE_DATABASE") && DI_CREATE_DATABASE)
		{
			$this->__q($this->getCreateDatabaseQuery());
		}

		if (!$this->link->select_db($this->dbname))
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
		if (!$this->link->close())
		{
			return $this->_log("unable to close connection");
		}

		return true;
	}

	protected function __error()
	{
		return $this->link->error;
	}

	protected function __q($q)
	{
		return $this->link->query($q);
	}

	protected function __rq($q)
	{
		return $this->link->real_query($q);
	}

	protected function __mq($q)
	{
		$ar = array();

		if ($this->link->multi_query($q))
		{
			do
			{
				$a = $this->link->store_result();

				if ($a)
				{
					$ar[] = $a;

					$a->free();
				}
			}
			while ($this->link->more_results() && $this->link->next_result());
		}

		if ($this->link->errno)
		{
			return false;
		}
		else
		{
			return $ar;
		}
	}

	protected function __mq_flush()
	{
		while ($this->link->next_result()) {;}
	}

	/**
	 * @param $rs \mysqli_result
	 */
	protected function __reset(&$rs)
	{
		if ($this->count($rs))
		{
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

	public function escape_string($s)
	{
		return $this->link->escape_string($s);
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