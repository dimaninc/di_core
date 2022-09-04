<?php
/*
	// dimaninc

	// 2015/05/01
		* ::insert_or_update() added

	// 2015/01/21
		* multi-insert error catching added

	// 2014/02/20
		* ::insert() improved: multi-insert added

	// 2014/02/11
		* ::rs_go() added

	// 2013/10/31
		* some precache improvements

	// 2013/06/22
		* reorganized
		* dimysqli class added
		* precache added

	// 2012/11/07
		* ::reset() added

	// 2012/04/06
		* ::insert() updated: *fields (w/o '') added

	// 2010/05/31
		* ::delete() updated: direct int ID and array IDs support added

	// 2010/05/18
		* ::update() and ::ar() updated: direct int ID and array IDs support added

	// 2009/11/02
		* init method params order changed
		* silent mode added

	// 2009/05/05
		* some improvements

	// 2008/12/05
		* random methods added
		* debug added

	// 2008/10/07
		* ::ar() methods added

	// 2008/06/05
		* ::drop(), ::delete() methods added

	// 2008/04/01
		* birthday
*/

use diCore\Data\Config;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

abstract class diDB
{
	const QUOTE_TABLE = '`';
	const QUOTE_FIELD = '`';
	const QUOTE_VALUE = "'";

	const CHARSET_INIT_NEEDED = true;

	const DEFAULT_PORT = null;

	// basic db info
	protected $host;
	protected $port;
	protected $dbname;
	protected $username;
	protected $password;

	protected $link;
	protected $logFolder = 'log/db/';
	protected $log;
	protected $execution_time = 0;
	protected $execution_time_log = [];

	protected $tables_ar;
	protected $debug = false;
	protected $logStackTrace = false;
	private $debugFileName;
	protected $silent = false;

	protected $transactionNestingLevel = 0;

	public $affected_rows = 0;
	public $cached_db_data = [];

	protected $lastInsertId;

	public function __construct($settingsOrHost, $username = null, $password = null, $dbname = null)
	{
		if (
		    is_array($settingsOrHost) &&
            $username === null &&
            $password === null &&
            $dbname === null
        ) {
			$settings = extend([
				'host' => null,
				'username' => null,
				'password' => null,
				'dbname' => null,
				'port' => static::DEFAULT_PORT,
			], $settingsOrHost);
		} else {
			$settings = [
				'host' => $settingsOrHost,
				'username' => $username,
				'password' => $password,
				'dbname' => $dbname,
				'port' => static::DEFAULT_PORT,
			];
		}

		$this->populateBasicSettings($settings);

		$this->log = [];

		if ($this->debug) {
			$this->enableDebug();
		}

		if (!empty($GLOBALS['engine']['tables_ar'])) {
			$this->set_tables_ar($GLOBALS['engine']['tables_ar']);
		}

		if (!$this->connect()) {
			$this->_fatal('unable to connect to database');
		}

		$this->initCharset();
	}

	protected function populateBasicSettings($settings)
    {
        $this->host = $settings['host'];
        $this->dbname = $settings['dbname'];
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->port = $settings['port'];

        return $this;
    }

	public function getLink()
	{
		return $this->link;
	}

	public function enableDebug()
	{
		$this->debug = true;

		$this->debugMessage([
			'URL',
			\diRequest::requestUri(),
		]);

		return $this;
	}

	public function withStackTrace()
    {
        $this->logStackTrace = true;

        return $this;
    }

	private function checkDebugFilename()
	{
		if (!$this->debugFileName) {
			do {
				$this->debugFileName = \diDateTime::format('Y-m-d-H-i-s-') . get_unique_id() . '.csv';
			} while (is_file($this->getDebugLogFileName()));
		}

		return $this;
	}

	protected function getFullDebugLogFolder()
	{
		return Config::getLogFolder() . $this->logFolder;
	}

	protected function getDebugLogFileName()
	{
		$this->checkDebugFilename();

		return StringHelper::slash($this->getFullDebugLogFolder()) . $this->debugFileName;
	}

	public function debugMessage($message)
	{
		if (is_array($message)) {
            $message = join("", array_map(function ($s) {
                return '"' . str_replace('"', '\"', $s) . '";';
            }, $message));
        }

		FileSystemHelper::createTree(Config::getLogFolder(), $this->logFolder, 0777);

		file_put_contents($this->getDebugLogFileName(), $message . "\n", FILE_APPEND | LOCK_EX);

		if ($this->logStackTrace) {
		    file_put_contents($this->getDebugLogFileName(), var_export((new \Exception)->getTraceAsString(), true) . "\n", FILE_APPEND | LOCK_EX);
        }

		return $this;
	}

	protected function getCreateDatabaseQuery()
	{
	    $quote = static::QUOTE_TABLE;

		return "CREATE DATABASE IF NOT EXISTS {$quote}{$this->dbname}{$quote} /*!40100 COLLATE '" . Config::getDbCollation() . "' */";
	}

	protected function initCharset()
	{
		if (!static::CHARSET_INIT_NEEDED) {
			return $this;
		}

		$enc = Config::getDbEncoding() ?: 'UTF8';

		$this->q('SET NAMES ' . $enc . ' COLLATE ' . Config::getDbCollation());
		$this->set_charset($enc);

		return $this;
	}

	/**
	 * @deprecated
	 * Use \diDB->escape_string() instead
	 */
	public static function _in($s)
	{
		return StringHelper::in($s);
	}

	public static function _out($s, $replaceAmp = false)
	{
		return StringHelper::out($s, $replaceAmp);
	}

	public static function is_rs($rs)
	{
		return is_resource($rs) || (is_object($rs) && method_exists($rs, 'fetch_object'));
	}

	public function getHost()
	{
		return $this->host;
	}

	public function getDatabase()
	{
		return $this->dbname;
	}

	public function getUsername()
	{
		return $this->username;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getExecutionTime()
	{
		return $this->execution_time;
	}

	public function getExecutionLog()
	{
		return $this->execution_time_log;
	}

	public function getLog()
	{
		return $this->log;
	}

	public function getLogStr($lineBreak = "\n")
	{
		return join($lineBreak, $this->log);
	}

	public function resetLog()
	{
		$this->log = [];

		return $this;
	}

	public function dierror($message = '')
	{
		if ($this->silent) {
			exit(0);
		}

		if (count($this->log)) {
			$this->_fatal($message);
		}

		return $this;
	}

	protected function _log($message, $add_native_error_message = true)
	{
		$this->log[] = $message;
		if ($add_native_error_message) {
			$this->log[] = $this->error();
		}

		return false;
	}

	protected function _fatal($message)
	{
		dierror(join("\n", $this->log), DIE_WARNING);
		dierror($message, $this->silent ? DIE_WARNING : DIE_FATAL);

		return false;
	}

	protected function time_log($method, $duration, $query = '', $message = '')
	{
		if (!$this->debug) {
			return $this;
		}

		$duration = sprintf('%.10f', $duration);

		//$this->log[] = "$message: $duration sec";

        $data = [
            $method,
            $query,
            $duration,
            $message,
        ];

        $explainData = $query
			? $this->__fetch_array($this->__q("EXPLAIN $query"))
			: null;

		if ($explainData) {
			foreach ($explainData as $k => $v) {
				$data[] = "$k = $v";
			}
		}

		$this->debugMessage($data);

		return $this;
	}

	public function set_tables_ar($ar)
	{
		$this->tables_ar = $ar;

		return $this;
	}

	public function get_table_name($table, $escape = false)
	{
		$name = $this->tables_ar && isset($this->tables_ar[$table]) ? $this->tables_ar[$table] : $table;

		if ($escape) {
			$name = $this->escape_string($name);
		}

		return $name;
	}

	public function doesColumnExist($table, $column)
    {
        $rs = $this->q("SELECT NULL
            FROM INFORMATION_SCHEMA.COLUMNS
           WHERE table_name = '{$table}'
             AND table_schema = '{$this->getDatabase()}'
             AND column_name = '{$column}'");

        return $this->count($rs) > 0;
    }

	/** @deprecated  */
	public function precache_rs($table, $query_or_ids_ar = '', $fields = '*')
	{
		if (is_array($table)) {
			$realTable = $table['table'];
			$table = $table['queryTable'];
		} else {
			$realTable = $table;
		}

		if (empty($this->cached_db_data[$realTable])) {
			$this->cached_db_data[$realTable] = array();
		}

		$rs = $this->rs($table, $query_or_ids_ar, $fields);
		while ($r = $this->fetch($rs)) {
			$this->cached_db_data[$realTable][$r->id] = $r;
		}

		$this->reset($rs);

		return $rs;
	}

    /** @deprecated  */
	public function precache_r($table, $id, $fields = '*', $force = true)
	{
		return $this->get_precached_r($table, $id, $fields, $force);
	}

    /** @deprecated  */
	public function get_precached_r($table, $id, $fields = '*', $force = true)
	{
		if (empty($this->cached_db_data[$table])) {
            $this->cached_db_data[$table] = [];
        }

		if (empty($this->cached_db_data[$table][$id]) && $id) {
			$r = $force ? $this->r($table, $id) : null;

			if ($r && !empty($r->id)) $id = $r->id;
			$this->cached_db_data[$table][$id] = $r;
		}

		return $id ? $this->cached_db_data[$table][$id] : null;
	}

    /** @deprecated  */
	public function clear_precached($table = false)
	{
		$this->flush_precached($table);

		return $this;
	}

    /** @deprecated  */
	public function flush_precached($table = false)
	{
		if ($table)
			$this->cached_db_data[$table] = array();
		else
			$this->cached_db_data = array();

		return $this;
	}

	public function escape_string($s, $binary = false)
	{
		return $s;
	}

	public static function in($ar = [], $digits_only = false, $positive = true)
	{
		if (is_array($ar)) {
			if (count($ar) == 1) {
				$c = $positive ? '=' : '!=';

				return $c . ' ' . static::QUOTE_VALUE . current($ar) . static::QUOTE_VALUE;
			} else {
				$c = $positive ? ' in' : ' not in';

				return $digits_only
                    ? $c . ' (' . join(',', $ar) . ')'
                    : $c . ' (' . static::QUOTE_VALUE . join(static::QUOTE_VALUE . ',' . static::QUOTE_VALUE, $ar) . static::QUOTE_VALUE . ')';
			}
		} else {
			$c = $positive ? '=' : '!=';

			return $c . ' ' . static::QUOTE_VALUE . $ar . static::QUOTE_VALUE;
		}
	}

	public static function not_in($ar = [], $digits_only = false)
	{
		return static::in($ar, $digits_only, false);
	}

	/* main methods */

	public function connect()
	{
		$time1 = utime();

		$result = $this->__connect();

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		return $result;
	}

	public function close()
	{
		if ($this->debug) {
			$this->time_log('total', $this->execution_time);
		}

		return $this->__close();
	}

	public function error()
	{
		return $this->__error();
	}

	public function q($q)
	{
		$time1 = utime();

		$result = $this->__q($q);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$err = $this->error();

		if (!$result && $err) {
			$this->_log("unable to exec query \"$q\"", false);
			$this->_log($err, false);
		}

		$this->time_log('q', $time2 - $time1, $q);

		return $result;
	}

	public function rq($q)
	{
		$time1 = utime();

		$result = $this->__rq($q);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		if (!$result)
			$this->_log("unable to exec query \"$q\"");

		$this->time_log('rq', $time2 - $time1, $q);

		return $result;
	}

	public function mq($q)
	{
		$time1 = utime();

		$result = $this->__mq($q);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		if ($result === false)
			$this->_log("unable to exec query \"$q\"");

		$this->time_log('mq', $time2 - $time1, $q);

		return $result;
	}

	public function getQueryForRs($table, $q_ending = "", $q_fields = "*")
	{
		if (is_numeric($q_ending)) {
			$q_ending = "WHERE id='$q_ending'" . $this->limitOffset(1);
		} elseif (is_array($q_ending)) {
			$q_ending = "WHERE id" . $this->in($q_ending);
		}

		if (is_array($q_fields)) {
			$q_fields = join(",", $q_fields);
		}

		$t = $this->get_table_name($table);

		return "SELECT $q_fields FROM $t $q_ending";
	}

	public function getQueryForR($table, $q_ending = "", $q_fields = "*")
	{
		if (is_numeric($q_ending)) {
			$q_ending = "WHERE id = '$q_ending'";
		} elseif (is_array($q_ending)) {
			$q_ending = "WHERE id " . $this->in($q_ending);
		}

		if (is_array($q_fields)) {
			$q_fields = join(",", $q_fields);
		}

		$t = $this->get_table_name($table);

		return "SELECT $q_fields FROM $t $q_ending" . $this->limitOffset(1);
	}

	public function rs($table, $q_ending = "", $q_fields = "*")
	{
		$time1 = utime();

		$q = $this->getQueryForRs($table, $q_ending, $q_fields);

		$rs = $this->__q($q);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("rs", $time2 - $time1, $q);

		if (!$rs) {
            return $this->_log("unable to exec query \"$q\"");
        }

		return $rs;
	}

	/**
	 * @param string $table
	 * @param mixed $q_ending
	 * @param string $q_fields
	 * @return object
	 */
	public function r($table, $q_ending = "", $q_fields = "*")
	{
		// alias to ::fetch()
		if ((self::is_rs($table) || $table === false) && $q_ending === "") {
			return $this->fetch($table);
		}
		//

		$q = $this->getQueryForR($table, $q_ending, $q_fields);

		$time1 = utime();

		$rs = $this->__q($q);
		$r = $rs ? $this->__fetch($rs) : false;

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("r", $time2 - $time1, $q);

		if (!$r) {
			$err = $this->error();

			if ($err) {
				$this->_log("unable to exec query \"$q\"", false);
				$this->_log($err, false);
			}

			return false;
		}

		return $r;
	}

	public function random_rs($table, $limit, $q_ending = "", $q_fields = "*")
	{
		$time1 = utime();

		$t = $this->get_table_name($table);

		/*
		$r = $this->r($t, $q_ending, "COUNT(*) AS cc");
		$count = $r ? $r->cc : 0;

		if ($count <= $limit)
			return $this->rs($table, $q_ending, $q_fields);

		srand((double)microtime() * 1000000);
		$start = rand(0, $count - $limit);

		$q = "SELECT $q_fields FROM $t $q_ending " . $this->limitOffset($limit, $start);
		*/

		if (is_array($q_fields)) {
			$q_fields = join(",", $q_fields);
		}

		$q = "SELECT $q_fields FROM $t $q_ending ORDER BY RAND()" . $this->limitOffset($limit);

		$rs = $this->__q($q);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("random_rs", $time2 - $time1, $q);

		if (!$rs) {
            return $this->_log("unable to exec query $q");
        }

		return $rs;
	}

	public function random_r($table, $q_ending = "", $q_fields = "*")
	{
		$rs = $this->random_rs($table, 1, $q_ending, $q_fields);
		return $rs ? $this->__fetch($rs) : false;
	}

	public function ar($table, $q_ending = "", $q_fields = "*")
	{
		// alias to ::fetch_array()
		if ((self::is_rs($table) || $table === false) && $q_ending === "") {
			return $this->fetch_array($table);
		}
		//

		$time1 = utime();

		$q = $this->getQueryForR($table, $q_ending, $q_fields);

		$rs = $this->__q($q);
		$r = $rs ? $this->fetch_array($rs) : false;

		$time2 = utime();
		$this->execution_time += $time2 - $time1;

		$this->time_log("ar", $time2 - $time1, $q);

		if (!$r) {
			$err = $this->error();

			if ($err) {
				$this->_log("unable to exec query \"$q\"", false);
				$this->_log($err, false);
			}

			return false;
		}

		return $r;
	}

	public static function fields_to_string_for_insert($ar)
	{
		return join(',', array_map(function($k) {
			return static::QUOTE_FIELD . ($k && $k[0] == '*' ? substr($k, 1) : $k) . static::QUOTE_FIELD;
		}, array_keys($ar)));
	}

	public static function values_to_string_for_insert($ar)
	{
        $outAr = [];

        foreach ($ar as $f => $v) {
			if ($f[0] == '*') {
				$outAr[] = $v;
			} elseif ($v === null) {
			    $outAr[] = 'NULL';
            } else {
				$outAr[] = static::QUOTE_VALUE . $v . static::QUOTE_VALUE;
			}
		}

		return join(',', $outAr);
	}

	public static function fields_and_values_to_string_for_update($ar)
	{
        $q_ar = [];

        foreach ($ar as $f => $v) {
			if ($v === null) {
				$q_ar[] = static::QUOTE_FIELD . $f . static::QUOTE_FIELD . ' = NULL';

				continue;
			}

			$q_ar[] = $f[0] === '*'
				? static::QUOTE_FIELD . substr($f, 1) . static::QUOTE_FIELD . ' = ' . $v
				: static::QUOTE_FIELD . $f . static::QUOTE_FIELD . ' = ' . static::QUOTE_VALUE . $v. static::QUOTE_VALUE;
		}

		return join(',', $q_ar);
	}

    /*
     * enter $keyField if it differs from 'id'
     */
    protected static function insertUpdateQuery($fields_values, $keyField = null)
    {
        $q1 = static::insertUpdateQueryBeginning($keyField);
        $q3 = static::fields_and_values_to_string_for_update($fields_values) . static::insertUpdateQueryEnding();

        return " {$q1} {$q3}";
    }

    public static function insertUpdateQueryBeginning($keyField = null)
    {
        return 'ON DUPLICATE KEY UPDATE';
    }

    public static function insertUpdateQueryEnding()
    {
        return '';
    }

	public function lockTable($table)
    {
        $this->__q("LOCK TABLES $table WRITE");

        return $this;
    }

    public function unlockTable($table = null)
    {
        $this->__q("UNLOCK TABLES");

        return $this;
    }

	public function insert($table, $fieldValues = [])
    {
        $t = $this->get_table_name($table);

        // preparing for multi-insert
        if (!is_array(current($fieldValues))) {
            $fieldValues = [$fieldValues];
        }

        $q1 = "(" . self::fields_to_string_for_insert(current($fieldValues)) . ")";
        $q2_ar = [];

        foreach ($fieldValues as $ar) {
            $q2_ar[] = "(" . self::values_to_string_for_insert($ar) . ")";
        }

        $time1 = utime();

        $this->lockTable($t);
        if (!$this->__rq("INSERT INTO {$t}{$q1} VALUES" . join(",", $q2_ar) . ";")) {
            $this->_log("Unable to insert into table $t", true);

            $this->unlockTable($t);

            return false;
        }
        $this->lastInsertId = $this->__insert_id();
        $this->unlockTable($t);

        $time2 = utime();
        $this->execution_time += $time2 - $time1;
        $this->time_log("insert", $time2 - $time1);

        return $this->lastInsertId;
	}

	public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    public function getUpdateSingleLimit()
    {
        return $this->limitOffset(1);
    }

	public function update($table, $fieldValues = array(), $q_ending = "")
	{
		$time1 = utime();

		$t = $this->get_table_name($table);

		// fast construction to get record by id
		if (is_numeric($q_ending)) {
            $q_ending = "WHERE {$this->escapeField('id')} = " . $this->escapeValue($q_ending) . $this->getUpdateSingleLimit();
        } elseif (is_array($q_ending)) {
            $q_ending = "WHERE " . $this->escapeField('id') . $this->in($q_ending);
        } elseif (!$q_ending && $q_ending !== "") {
			$this->_log("Warning, empty Q_ENDING in update ($table)", false);

			return false;
			//$q_ending = "WHERE 1=0";
		}
		//

        $q = "UPDATE $t SET " . self::fields_and_values_to_string_for_update($fieldValues) . " $q_ending";

		$this->lockTable($t);
		if (!$this->__rq($q)) {
			$this->_log("Unable to update: $q", true);

            $this->unlockTable($t);

			return false;
		}

		$this->affected_rows = $this->__affected_rows();
        $this->unlockTable($t);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;
		$this->time_log("update", $time2 - $time1, $q);

		return true;
	}

    public function getDeleteSingleLimit()
    {
        return  $this->limitOffset(1);
    }

	public function delete($table, $q_ending = "")
	{
		$time1 = utime();

		$t = $this->get_table_name($table);

		// fast construction to get record by id
        if (is_numeric($q_ending)) {
            $q_ending = "WHERE id = '$q_ending'" . $this->getDeleteSingleLimit();
        } elseif (is_array($q_ending)) {
            $q_ending = "WHERE id" . $this->in($q_ending);
        } elseif (!$q_ending && $q_ending !== "") {
            $this->_log("Warning, empty Q_ENDING in delete ($table)", false);

            return false;
        }

		$q = "DELETE FROM $t $q_ending";

        $this->lockTable($t);
		if (!$this->__rq($q)) {
			$this->_log("Unable to delete: $q", false);

            $this->unlockTable($t);

			return false;
		}
        $this->unlockTable($t);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;
		$this->time_log("delete", $time2 - $time1, $q);

		return true;
	}

	/*
	 * enter $keyField if it differs from 'id'
	 */
	public function insert_or_update($table, $fields_values = [], $keyField = null)
	{
		$t = $this->get_table_name($table);

        $q1 = "(" . static::fields_to_string_for_insert($fields_values) . ")";
        $q2 = "(" . static::values_to_string_for_insert($fields_values) . ")";
        $q3 = static::insertUpdateQuery($fields_values, $keyField);

		$time1 = utime();

        $this->lockTable($t);
        $query = "INSERT INTO {$t}{$q1} VALUES{$q2}{$q3};";
		if (!$this->__rq($query)) {
			$this->_log("unable to insert/update into table $t", false);

            $this->unlockTable($t);

			return false;
		}
		$id = $this->__insert_id();
        $this->unlockTable($t);

		$time2 = utime();
		$this->execution_time += $time2 - $time1;
		$this->time_log("insert", $time2 - $time1);

		return $id;
	}

	public function drop($table)
	{
		$t = $this->get_table_name($table);

		if (!$this->__rq("DROP TABLE $t")) {
            return $this->_log("unable to drop table $t", false);
        }

		return true;
	}

	public function reset(&$rs)
	{
		return $this->__reset($rs);
	}

	public function fetch($rs)
	{
		return $rs ? $this->__fetch($rs) : null;
	}

	public function fetch_array($rs)
	{
		return $rs ? $this->__fetch_array($rs) : null;
	}

	public function fetch_ar($rs)
	{
		return $rs ? $this->fetch_array($rs) : null;
	}

	public function rs_go($func, $table, $q_ending = "", $q_fields = "*")
	{
		$i = 0;

		$rs = $this->rs($table, $q_ending, $q_fields);
		while ($r = $this->fetch($rs)) {
			$func($r, $i++);
		}

		return $this;
	}

	public function count($rs)
	{
		return $this->__count($rs);
	}

	public function set_charset($name)
	{
		return $this->__set_charset($name);
	}

	public function get_charset()
	{
		return $this->__get_charset();
	}

	protected function getStartTransactionQuery()
    {
        return 'START TRANSACTION';
    }

    protected function getCommitTransactionQuery()
    {
        return 'COMMIT';
    }

    protected function getRollbackTransactionQuery()
    {
        return 'ROLLBACK';
    }

	public function startTransaction()
	{
		$this->transactionNestingLevel++;

		if ($this->getStartTransactionQuery()) {
            $this->q($this->getStartTransactionQuery());
        }

		return $this;
	}

	public function commitTransaction()
	{
		if ($this->transactionNestingLevel) {
			$this->transactionNestingLevel--;

			if ($this->getCommitTransactionQuery()) {
                $this->q($this->getCommitTransactionQuery());
            }
		}

		return $this;
	}

	public function rollbackTransaction()
	{
		if ($this->transactionNestingLevel) {
			$this->transactionNestingLevel--;

			if ($this->getRollbackTransactionQuery()) {
                $this->q($this->getRollbackTransactionQuery());
            }
		}

		return $this;
	}

	public function getBetweenOperator($val1 = null, $val2 = null)
    {
        if ($val1) {
            $val1 = $this->escapeValue($val1);
        }

        if ($val2) {
            $val2 = $this->escapeValue($val2);
        }

        if ($val1 && $val2) {
            $op = "BETWEEN $val1 AND $val2";
        } elseif ($val1) {
            $op = ">= $val1";
        } elseif ($val2) {
            $op = "<= $val2";
        } else {
            $op = null;
        }

        return $op;
    }

	/**
	 * Prepares and quotes string for query as param
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public function escapeTable($string)
	{
		return static::QUOTE_TABLE . $this->escape_string($string) . static::QUOTE_TABLE;
	}

	/**
	 * Prepares and quotes string for query as param
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public function escapeField($string)
	{
		$x = strpos($string, '.');

		if ($x !== false) {
			$alias = static::QUOTE_FIELD . substr($string, 0, $x) . static::QUOTE_FIELD . '.';
			$field = substr($string, $x + 1);
		} else {
			$alias = '';
			$field = $string;
		}

		return $field !== '*'
			? $alias . static::QUOTE_FIELD . $this->escape_string($field) . static::QUOTE_FIELD
			: $alias . $field;
	}

	/**
	 * Prepares and quotes string for query as param
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public function escapeValue($string)
	{
		return static::QUOTE_VALUE . $this->escape_string($string) . static::QUOTE_VALUE;
	}

	public function limitOffset($limit = null, $offset = null)
    {
        $ar = [];

        if ($limit) {
            $ar[] = "LIMIT $limit";
        }

        if ($offset) {
            $ar[] = "OFFSET $offset";
        }

        return ' ' . join(' ', $ar);
    }

    /**
     * @param string $field
     * @param string $method get/set/has
     */
    public function getFieldMethodForModel($field, $method)
    {
        return camelize(underscore($method) . '_' . $field);
    }

	/* these methods should be overwritten */

	abstract protected function __connect();
	abstract protected function __close();
	abstract protected function __error();
	abstract protected function __q($q);
	abstract protected function __rq($q);
	abstract protected function __mq($q);
	abstract protected function __mq_flush();
	abstract protected function __reset(&$rs);
	abstract protected function __fetch($rs);
	abstract protected function __fetch_array($rs);
	abstract protected function __count($rs);
	abstract protected function __insert_id();
	abstract protected function __affected_rows();
	abstract protected function __set_charset($name);
	abstract protected function __get_charset();
    abstract public function getTablesInfo();
	abstract public function getTableNames();
    abstract public function getFields($table);
}
