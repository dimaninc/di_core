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
use diCore\Helper\ArrayHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;
use diCore\Tool\Logger;

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
    /** @var \diCore\Database\Connection | null */
    protected $connection;
    protected $logFolder = 'log/db/';
    protected $log;
    protected $execution_time = 0;

    protected $tables_ar;
    protected $debug = false;
    protected $logStackTrace = false;
    private $debugFileName;
    protected $silent = false;

    protected $transactionNestingLevel = 0;

    public $affected_rows = 0;
    public $cached_db_data = [];

    protected $lastInsertId;

    protected $ignoreReadLock = false;
    protected $ignoreWriteLock = false;

    protected static $dumpCommand = null;
    protected static $localDockerDumpCommand = null;

    public function __construct(
        $settingsOrHost,
        $username = null,
        $password = null,
        $dbname = null,
        $connection = null
    ) {
        if (
            is_array($settingsOrHost) &&
            $username === null &&
            $password === null &&
            $dbname === null
        ) {
            $settings = extend(
                [
                    'host' => null,
                    'username' => null,
                    'password' => null,
                    'dbname' => null,
                    'port' => static::DEFAULT_PORT,
                    'connection' => null,
                ],
                $settingsOrHost
            );
        } else {
            $settings = [
                'host' => $settingsOrHost,
                'username' => $username,
                'password' => $password,
                'dbname' => $dbname,
                'port' => static::DEFAULT_PORT,
                'connection' => $connection,
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
        $this->connection = $settings['connection'] ?? null;

        return $this;
    }

    public function getLink()
    {
        return $this->link;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function enableDebug()
    {
        $this->debug = true;

        $this->debugMessage([
            'op',
            'query (URL=' . \diRequest::requestUri() . ')',
            'duration, sec',
            'message (optional)',
            'explain data...',
        ]);

        return $this;
    }

    protected static function utimeStr()
    {
        return str_replace('.', ',', (string) utime());
    }

    public function ignoreReadLock($state = true)
    {
        $this->ignoreReadLock = $state;

        return $this;
    }

    public function ignoreWriteLock($state = true)
    {
        $this->ignoreWriteLock = $state;

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
                $this->debugFileName =
                    \diDateTime::format('Y-m-d-H-i-s-') . get_unique_id() . '.csv';
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

        return StringHelper::slash($this->getFullDebugLogFolder()) .
            $this->debugFileName;
    }

    public function debugMessage($message)
    {
        if (is_array($message)) {
            $message = join(
                '',
                array_map(function ($s) {
                    return '"' . str_replace('"', '\"', $s) . '";';
                }, $message)
            );
        }

        FileSystemHelper::createTree(Config::getLogFolder(), $this->logFolder, 0777);

        file_put_contents(
            $this->getDebugLogFileName(),
            $message . "\n",
            FILE_APPEND | LOCK_EX
        );

        if ($this->logStackTrace) {
            file_put_contents(
                $this->getDebugLogFileName(),
                var_export((new \Exception())->getTraceAsString(), true) . "\n",
                FILE_APPEND | LOCK_EX
            );
        }

        return $this;
    }

    protected function getCreateDatabaseQuery()
    {
        $quote = static::QUOTE_TABLE;

        $charset = Config::getDbEncoding();
        $collation = Config::getDbCollation();

        // The CHARACTER SET matters as much as the collation: without it the
        // database is created on the SERVER default while generated tables use
        // the configured charset, which is how a schema ends up with mixed
        // defaults. A blank collation is simply omitted, never `COLLATE ''`.
        $options = '';
        if ($charset) {
            $options .= " CHARACTER SET '$charset'";
        }
        if ($collation) {
            $options .= " COLLATE '$collation'";
        }

        return "CREATE DATABASE IF NOT EXISTS $quote$this->dbname$quote" .
            ($options ? " /*!40100$options */" : '');
    }

    protected function initCharset()
    {
        if (!static::CHARSET_INIT_NEEDED) {
            return $this;
        }

        $enc = Config::getDbEncoding() ?: 'UTF8';
        $collation = Config::getDbCollation();

        // On mysql/mysqli set_charset() issues its own SET NAMES and resets the
        // collation to the charset default, so it must come first or the
        // configured collation is lost — harmless on utf8mb3 (same default), not
        // on utf8mb4. (On the PDO driver it only records the name; the charset
        // rides in the DSN. On Mongo it is a no-op.)
        //
        // An unknown charset THROWS on PHP >= 8.1 (mysqli defaults to
        // MYSQLI_REPORT_ERROR|STRICT) and returns false below that, so both are
        // caught: the connection is then still usable, just on the previous
        // charset, and that must not pass silently.
        try {
            $ok = $this->set_charset($enc);
        } catch (\Throwable $e) {
            $ok = false;
        }

        if ($ok === false) {
            // Fatal, not a note in a log nobody reads: carrying on leaves the
            // CLIENT on the previous charset while the columns are on the
            // configured one, and every 4-byte character written through it is
            // silently mangled — the exact corruption this ordering exists to
            // prevent. A misconfigured charset is a broken connection, so it is
            // reported the same way an unreachable one is.
            $this->failConnectionCharset($enc);
        }

        // An empty collation would make this a syntax error; SET NAMES alone
        // still leaves the connection usable on the charset default.
        if ($collation && $this->trySetNames("SET NAMES $enc COLLATE $collation")) {
            return $this;
        }

        // The collation does not belong to the charset — the state a project
        // lands in when it raises dbEncoding to utf8mb4 and forgets
        // dbCollation. NOT fatal: set_charset() above already put the
        // connection on the configured CHARSET, so nothing can be truncated;
        // only the collation falls back to that charset's default. Failing the
        // request here would take the whole site down over a typo.
        //
        // The plain SET NAMES is still issued: set_charset() moved
        // character_set_* but a driver is free not to touch collation_connection,
        // and it must not be left over from whatever ran before.
        if ($collation) {
            $this->logConnectionProblem(
                "Connection collation $collation is not valid for charset $enc" .
                    ' — falling back to the charset default.' .
                    ' Fix dbCollation in Data\Config.'
            );
        }

        if (!$this->trySetNames("SET NAMES $enc")) {
            // The charset alone is refused too: this really is a broken
            // connection, and writing through it corrupts data.
            $this->failConnectionCharset($enc);
        }

        return $this;
    }

    /**
     * A connection whose charset could not be applied is a broken connection,
     * and it is reported exactly like an unreachable one — by throwing.
     *
     * Deliberately NOT _fatal(): that ends in die() with no status code, so the
     * browser gets HTTP 200 with the error in the body, which an nginx cache in
     * front will happily keep serving long after the fix. die() is not a
     * \Throwable either, so it walks straight past the entry-point handler a
     * consumer uses to serve its own 503 page. And this is reachable by
     * configuration alone: mysqlnd rejects the name `utf8mb3` (verified —
     * set_charset() returns false), which is the very spelling MySQL 8.0.28+
     * and CharsetConverter::MB3_NAMES call canonical, so a plausible
     * `dbEncoding` would otherwise take a whole site down uncatchably.
     */
    protected function failConnectionCharset($enc)
    {
        $message = "Unable to set connection charset to $enc";

        $this->logConnectionProblem($message);

        throw new \diDatabaseException($message);
    }

    /**
     * q() logs a failure into diDB::$log, and a non-empty log makes the next
     * dierror() anywhere in the request escalate to _fatal() with an unrelated
     * message. Connection setup must leave no such trace behind, so whatever
     * this statement added is moved out of the way and the outcome returned
     * instead.
     */
    private function trySetNames($query)
    {
        $logged = count($this->log);
        $result = $this->q($query);
        $failed = $result === false || count($this->log) > $logged;

        if ($failed) {
            $this->logConnectionProblem(
                "$query failed: " . join('; ', array_slice($this->log, $logged))
            );
            $this->truncateLog($logged);
        }

        return !$failed;
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
        return is_resource($rs) ||
            (is_object($rs) && method_exists($rs, 'fetch_object'));
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

    public function getLog()
    {
        return $this->log;
    }

    public function getLogStr($lineBreak = "\n")
    {
        return join($lineBreak, $this->log);
    }

    /** Drops everything logged after the first $count entries. */
    public function truncateLog($count)
    {
        $this->log = array_slice($this->log, 0, max(0, (int) $count));

        return $this;
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

    /**
     * Charset problems go to the file log, never to diDB::$log: a non-empty log
     * makes the next dierror() call anywhere escalate to _fatal() with someone
     * else's message. Both callers fail the connection themselves, right after —
     * this only decides WHERE the reason is recorded.
     */
    protected function logConnectionProblem($message)
    {
        try {
            \diCore\Tool\Logger::getInstance()->log($message, 'database');
        } catch (\Throwable $e) {
            // logging must never break connecting
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

    protected function time_log(
        $method,
        $duration,
        $query = '',
        $message = '',
        $explain = true
    ) {
        $this->execution_time += $duration;

        if (!$this->debug) {
            return $this;
        }

        $durationStr = str_replace('.', ',', sprintf('%.10f', $duration));

        //$this->log[] = "$message: $duration sec";

        $data = [$method, $query, $durationStr, $message ?: ''];

        $explainData =
            $explain && $query
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
        $name =
            $this->tables_ar && isset($this->tables_ar[$table])
                ? $this->tables_ar[$table]
                : $table;

        if ($escape) {
            $name = $this->escape_string($name);
        }

        return $name;
    }

    public function doesColumnExist($table, $column)
    {
        $this->lockTable('INFORMATION_SCHEMA', 'READ');
        $rs = $this->q("SELECT NULL
            FROM INFORMATION_SCHEMA.COLUMNS
           WHERE table_name = '{$table}'
             AND table_schema = '{$this->getDatabase()}'
             AND column_name = '{$column}'");
        $this->unlockTable('INFORMATION_SCHEMA', 'READ');

        return $this->count($rs) > 0;
    }

    /**
     * @deprecated
     * Use CollectionCache
     */
    public function precache_rs($table, $query_or_ids_ar = '', $fields = '*')
    {
        if (is_array($table)) {
            $realTable = $table['table'];
            $table = $table['queryTable'];
        } else {
            $realTable = $table;
        }

        if (empty($this->cached_db_data[$realTable])) {
            $this->cached_db_data[$realTable] = [];
        }

        $rs = $this->rs($table, $query_or_ids_ar, $fields);
        while ($r = $this->fetch($rs)) {
            $this->cached_db_data[$realTable][$r->id] = $r;
        }

        $this->reset($rs);

        return $rs;
    }

    /**
     * @deprecated
     * Use CollectionCache
     */
    public function precache_r($table, $id, $fields = '*', $force = true)
    {
        return $this->get_precached_r($table, $id, $fields, $force);
    }

    /**
     * @deprecated
     * Use CollectionCache
     */
    public function get_precached_r($table, $id, $fields = '*', $force = true)
    {
        if (empty($this->cached_db_data[$table])) {
            $this->cached_db_data[$table] = [];
        }

        if (empty($this->cached_db_data[$table][$id]) && $id) {
            $r = $force ? $this->r($table, $id) : null;

            if ($r && !empty($r->id)) {
                $id = $r->id;
            }
            $this->cached_db_data[$table][$id] = $r;
        }

        return $id ? $this->cached_db_data[$table][$id] : null;
    }

    /**
     * @deprecated
     * Use collectionCache
     */
    public function clear_precached($table = false)
    {
        $this->flush_precached($table);

        return $this;
    }

    /**
     * @deprecated
     * Use collectionCache
     */
    public function flush_precached($table = false)
    {
        if ($table) {
            $this->cached_db_data[$table] = [];
        } else {
            $this->cached_db_data = [];
        }

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

                return $c .
                    ' ' .
                    static::QUOTE_VALUE .
                    current($ar) .
                    static::QUOTE_VALUE;
            } else {
                $c = $positive ? ' in' : ' not in';

                return $digits_only
                    ? $c . ' (' . join(',', $ar) . ')'
                    : $c .
                            ' (' .
                            static::QUOTE_VALUE .
                            join(
                                static::QUOTE_VALUE . ',' . static::QUOTE_VALUE,
                                $ar
                            ) .
                            static::QUOTE_VALUE .
                            ')';
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
        return $this->__connect();
    }

    public function close()
    {
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
        $this->time_log('q', utime() - $time1, $q);

        $err = $this->error();

        if (!$result && $err) {
            $this->_log("Unable to exec query $q", false);
            $this->_log($err, false);
        }

        return $result;
    }

    public function rq($q, $skipTimeLog = false)
    {
        $time1 = utime();

        $result = $this->__rq($q);

        if (!$result) {
            $this->_log("Unable to exec RQ query $q");
        }

        if (!$skipTimeLog) {
            $this->time_log('rq', utime() - $time1, $q);
        }

        return $result;
    }

    public function mq($q)
    {
        $time1 = utime();

        $result = $this->__mq($q);

        if ($result === false) {
            $this->_log("Unable to exec MQ query \"$q\"");
        }

        $this->time_log('mq', utime() - $time1, $q);

        return $result;
    }

    public function getQueryForRs($table, $q_ending = '', $q_fields = '*')
    {
        if (is_numeric($q_ending)) {
            $q_ending = "WHERE id='$q_ending'" . $this->limitOffset(1);
        } elseif (is_array($q_ending)) {
            $q_ending = 'WHERE id' . $this->in($q_ending);
        }

        if (is_array($q_fields)) {
            $q_fields = join(',', $q_fields);
        }

        $t = $this->get_table_name($table);

        return "SELECT $q_fields FROM $t $q_ending";
    }

    public function getQueryForR($table, $q_ending = '', $q_fields = '*')
    {
        if (is_numeric($q_ending)) {
            $q_ending = "WHERE id = '$q_ending'";
        } elseif (is_array($q_ending)) {
            $q_ending = 'WHERE id ' . $this->in($q_ending);
        }

        if (is_array($q_fields)) {
            $q_fields = join(',', $q_fields);
        }

        $t = $this->get_table_name($table);

        return "SELECT $q_fields FROM $t $q_ending" . $this->limitOffset(1);
    }

    public function rs($table, $q_ending = '', $q_fields = '*')
    {
        $q = $this->getQueryForRs($table, $q_ending, $q_fields);

        $tablesToLock = $this->lockTable($q, 'READ');

        $time1 = utime();
        $rs = $this->__q($q);
        $this->time_log('rs', utime() - $time1, $q);

        $this->unlockTable($tablesToLock, 'READ');

        if (!$rs) {
            return $this->_log("Unable to exec RS query $q");
        }

        return $rs;
    }

    /**
     * @param string $table
     * @param mixed $q_ending
     * @param string $q_fields
     * @return object
     */
    public function r($table, $q_ending = '', $q_fields = '*')
    {
        // alias to ::fetch()
        if ((self::is_rs($table) || $table === false) && $q_ending === '') {
            return $this->fetch($table);
        }

        $q = $this->getQueryForR($table, $q_ending, $q_fields);

        $tablesToLock = $this->lockTable($q, 'READ');

        $time1 = utime();
        $rs = $this->__q($q);
        $this->time_log('r', utime() - $time1, $q);

        $this->unlockTable($tablesToLock);

        $r = $rs ? $this->__fetch($rs) : false;

        if (!$r) {
            $err = $this->error();

            if ($err) {
                $this->_log("Unable to exec R query $q", false);
                $this->_log($err, false);
            }

            return false;
        }

        return $r;
    }

    public function random_rs($table, $limit, $q_ending = '', $q_fields = '*')
    {
        $t = $this->get_table_name($table);

        /*
		$r = $this->r($t, $q_ending, "COUNT(*) AS cc");
		$count = $r ? $r->cc : 0;

		if ($count <= $limit)
			return $this->rs($table, $q_ending, $q_fields);

		$start = rand(0, $count - $limit);

		$q = "SELECT $q_fields FROM $t $q_ending " . $this->limitOffset($limit, $start);
		*/

        if (is_array($q_fields)) {
            $q_fields = join(',', $q_fields);
        }

        $limitSuffix = $this->limitOffset($limit);
        $q = "SELECT $q_fields FROM $t $q_ending ORDER BY RAND()$limitSuffix";

        $tablesToLock = $this->lockTable($q, 'READ');

        $time1 = utime();
        $rs = $this->__q($q);
        $this->time_log('random_rs', utime() - $time1, $q);

        $this->unlockTable($tablesToLock, 'READ');

        if (!$rs) {
            return $this->_log("Unable to exec random RS query $q");
        }

        return $rs;
    }

    public function random_r($table, $q_ending = '', $q_fields = '*')
    {
        $rs = $this->random_rs($table, 1, $q_ending, $q_fields);
        return $rs ? $this->__fetch($rs) : false;
    }

    public function ar($table, $q_ending = '', $q_fields = '*')
    {
        // alias to ::fetch_array()
        if ((self::is_rs($table) || $table === false) && $q_ending === '') {
            return $this->fetch_array($table);
        }

        $q = $this->getQueryForR($table, $q_ending, $q_fields);

        $tablesToLock = $this->lockTable($q, 'READ');

        $time1 = utime();
        $rs = $this->__q($q);
        $this->time_log('ar', utime() - $time1, $q);

        $this->unlockTable($tablesToLock, 'READ');

        $r = $rs ? $this->fetch_array($rs) : false;

        if (!$r) {
            $err = $this->error();

            if ($err) {
                $this->_log("Unable to exec AR query $q", false);
                $this->_log($err, false);
            }

            return false;
        }

        return $r;
    }

    public function fieldsToStringForInsert($ar)
    {
        return join(
            ',',
            array_map(function ($k) {
                if ($k && $k[0] == '*') {
                    $k = substr($k, 1);
                }

                return $this->quoteField($k);
            }, array_keys($ar))
        );
    }

    protected function getJsonFieldQuery($value)
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_array($value) || is_object($value)) {
            $value = ArrayHelper::fromObject($value);

            return $this->getJsonForStructure($value);
        }

        return $this->quoteValue($value);
    }

    /*
     * Default for Mysql
     */
    protected function getJsonForStructure($value)
    {
        $quote = function ($s) {
            if (is_null($s)) {
                return 'NULL';
            }

            if (isNumber($s)) {
                return $s;
            }

            if (is_bool($s)) {
                return $s ? 'TRUE' : 'FALSE';
            }

            if (is_array($s)) {
                return $this->getJsonForStructure($s);
            }

            return $this->escapeValue($s);
        };

        if (is_array($value) && ArrayHelper::isAssoc($value)) {
            $ar = call_user_func_array(
                'array_merge',
                array_map(null, array_keys($value), array_values($value))
            );

            return 'JSON_OBJECT(' .
                join(',', array_map(fn($i) => $quote($i), $ar)) .
                ')';
        }

        if (is_array($value) && ArrayHelper::isSequential($value)) {
            return 'JSON_ARRAY(' .
                join(',', array_map(fn($i) => $quote($i), $value)) .
                ')';
        }

        return null;
    }

    public function valuesToStringForInsert($ar)
    {
        $outAr = [];

        foreach ($ar as $field => $value) {
            if ($field[0] == '*') {
                $outAr[] = $value;
            } elseif ($value === null) {
                $outAr[] = 'NULL';
            } else {
                $outAr[] = $this->getJsonFieldQuery($value); //  ?? $this->quoteValue($v)
            }
        }

        return join(',', $outAr);
    }

    public function fieldsAndValuesToStringForUpdate($ar)
    {
        $values = [];

        $getter = function ($f, $v) {
            if ($v === null) {
                return $this->escapeField($f) . '=NULL';
            }

            if ($f[0] === '*') {
                return $this->escapeField(substr($f, 1)) . '=' . $v;
            }

            $value = $this->getJsonFieldQuery($v); //  ?? $this->quoteValue($v)

            return $this->escapeField($f) . '=' . $value;
        };

        foreach ($ar as $f => $v) {
            $values[] = $getter($f, $v);
        }

        return join(',', $values);
    }

    /*
     * enter $keyField if it differs from 'id'
     */
    protected function insertUpdateQuery(
        $fields_values,
        $keyField = null,
        $autoIncrementField = null
    ) {
        $q1 = static::insertUpdateQueryBeginning($keyField);
        $q3 =
            $this->fieldsAndValuesToStringForUpdate($fields_values) .
            static::insertUpdateQueryEnding($autoIncrementField);

        return " $q1 $q3";
    }

    protected function insertIgnoreQuery($table, $fieldsValues)
    {
        $t = $this->get_table_name($table);
        $q1 = '(' . $this->fieldsToStringForInsert($fieldsValues) . ')';
        $q2 = '(' . $this->valuesToStringForInsert($fieldsValues) . ')';

        return "INSERT IGNORE INTO $t$q1 VALUES$q2";
    }

    public static function insertUpdateQueryBeginning($keyField = null)
    {
        return 'ON DUPLICATE KEY UPDATE';
    }

    public static function insertUpdateQueryEnding($autoIncrementField = null)
    {
        return '';
    }

    public function lockTable($table)
    {
        return [$table];
    }

    public function unlockTable($table = null)
    {
        return array_filter([$table]);
    }

    public function getFullQueryForInsert($table, $records = [])
    {
        $t = $this->get_table_name($table);

        // for multi-insert
        if (!ArrayHelper::isSequential($records)) {
            $records = [$records];
        }

        $fieldsStr = "({$this->fieldsToStringForInsert(current($records))})";
        $values = [];

        foreach ($records as $rec) {
            $values[] = "({$this->valuesToStringForInsert($rec)})";
        }

        $q2 = join(',', $values);

        return "INSERT INTO $t$fieldsStr VALUES$q2;";
    }

    public function insert($table, $fieldValues = [])
    {
        $t = $this->get_table_name($table);

        $this->lockTable($t);

        $q = $this->getFullQueryForInsert($table, $fieldValues);

        $time1 = utime();
        if (!$this->__rq($q)) {
            $this->_log("Unable to insert into table $t");
            $this->unlockTable($t);

            return false;
        }
        $this->lastInsertId = $this->__insert_id();
        $this->time_log('insert', utime() - $time1, $q);

        $this->unlockTable($t);

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

    public function getFullQueryForUpdate($table, $fieldValues = [], $q_ending = '')
    {
        $t = $this->get_table_name($table);

        if (is_numeric($q_ending)) {
            $q_ending =
                'WHERE ' .
                $this->escapeFieldValue('id', $q_ending) .
                $this->getUpdateSingleLimit();
        } elseif (is_array($q_ending)) {
            $q_ending = 'WHERE ' . $this->escapeField('id') . $this->in($q_ending);
        } elseif (!$q_ending) {
            //  && $q_ending !== ''
            throw new \diDatabaseException(
                "Warning, empty Q_ENDING in update ($table)"
            );
        }

        $q = $this->fieldsAndValuesToStringForUpdate($fieldValues);

        return "UPDATE $t SET $q $q_ending";
    }

    public function update($table, $fieldValues = [], $q_ending = '')
    {
        $t = $this->get_table_name($table);
        $q = $this->getFullQueryForUpdate($table, $fieldValues, $q_ending);

        $this->lockTable($t);

        $time1 = utime();
        if (!$this->__rq($q)) {
            $this->_log("Unable to update table $t");
            $this->unlockTable($t);

            return false;
        }
        $this->time_log('update', utime() - $time1, $q);

        $this->affected_rows = $this->__affected_rows();
        $this->unlockTable($t);

        return true;
    }

    /**
     * Run a write (UPDATE/DELETE/INSERT) and return its affected-row count,
     * captured IMMEDIATELY after execution — for guarded updates like
     * `UPDATE … WHERE id=? AND status=?` where the affected count is the
     * race/guard signal.
     *
     * Why a dedicated method and not `q()` + a separate affected-rows read: `q()`
     * calls `time_log()`, which under DB debug runs an `EXPLAIN <query>` — another
     * query that overwrites the driver's native affected-row state. Reading
     * affected rows after `q()` would then see the EXPLAIN's value, not the write's.
     * This uses the raw `rq($sql, skipTimeLog: true)` path (no EXPLAIN) and reads
     * affected rows before anything else can run.
     *
     * Returns CHANGED rows, not matched — a guard whose WHERE matches but whose SET
     * changes nothing reports 0 (no MYSQLI_CLIENT_FOUND_ROWS). THROWS
     * diDatabaseException on query failure (a hard error, distinct from a 0 ==
     * no-match guard miss) — a throwing write path for new code; the legacy
     * q()/insert()/update() keep their return-false-on-failure behaviour.
     */
    public function execWrite(string $sql): int
    {
        if ($this->rq($sql, true) === false) {
            throw new \diDatabaseException('execWrite: query execution failed');
        }

        return (int) $this->__affected_rows();
    }

    public function getDeleteSingleLimit()
    {
        return $this->limitOffset(1);
    }

    public function delete($table, $q_ending = '')
    {
        $t = $this->get_table_name($table);

        // fast construction to get record by id
        if (is_numeric($q_ending)) {
            $q_ending =
                'WHERE ' .
                $this->escapeFieldValue('id', $q_ending) .
                $this->getDeleteSingleLimit();
        } elseif (is_array($q_ending)) {
            $q_ending = 'WHERE id' . $this->in($q_ending);
        } elseif (!$q_ending && $q_ending !== '') {
            $this->_log("Warning, empty Q_ENDING in delete ($table)", false);

            return false;
        }

        $q = "DELETE FROM $t $q_ending";

        $this->lockTable($t);

        $time1 = utime();
        if (!$this->__rq($q)) {
            $this->_log("Unable to delete: $q", false);

            $this->unlockTable($t);

            return false;
        }
        $this->time_log('delete', utime() - $time1, $q);

        $this->unlockTable($t);

        return true;
    }

    /*
     * enter $keyField if it differs from 'id'
     * pass $autoIncrementField to make MySQL return the affected row's id on the UPDATE path
     * via the LAST_INSERT_ID(<field>) trick. Without it MySQL returns 0 when the row was updated
     * instead of inserted, leaving the model without an id.
     */
    public function insert_or_update(
        $table,
        $fields_values = [],
        $keyField = null,
        $autoIncrementField = null
    ) {
        $t = $this->get_table_name($table);

        $q1 = '(' . $this->fieldsToStringForInsert($fields_values) . ')';
        $q2 = '(' . $this->valuesToStringForInsert($fields_values) . ')';
        $q3 = $this->insertUpdateQuery(
            $fields_values,
            $keyField,
            $autoIncrementField
        );

        $this->lockTable($t);
        $query = "INSERT INTO $t$q1 VALUES$q2$q3;";

        $time1 = utime();
        if (!$this->__rq($query)) {
            $this->_log("unable to insert/update into table $t", false);

            $this->unlockTable($t);

            return false;
        }
        $id = $this->__insert_id();
        $this->time_log('insert_or_update', utime() - $time1, $query);

        $this->unlockTable($t);

        return $id;
    }

    public function insertIgnore($table, $fieldsValues = [])
    {
        $t = $this->get_table_name($table);
        $query = $this->insertIgnoreQuery($table, $fieldsValues);

        $this->lockTable($t);

        $time1 = utime();
        if (!$this->__rq($query)) {
            $this->_log("unable to insert ignore into table $t", false);

            $this->unlockTable($t);

            return false;
        }
        $id = $this->__insert_id();
        $this->time_log('insert_ignore', utime() - $time1, $query);

        $this->unlockTable($t);

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

    public function rs_go($func, $table, $q_ending = '', $q_fields = '*')
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
        return 'START TRANSACTION;';
    }

    protected function getCommitTransactionQuery()
    {
        return 'COMMIT;';
    }

    protected function getRollbackTransactionQuery()
    {
        return 'ROLLBACK;';
    }

    protected function getSavepointQuery($name)
    {
        return "SAVEPOINT $name;";
    }

    protected function getReleaseSavepointQuery($name)
    {
        return "RELEASE SAVEPOINT $name;";
    }

    protected function getRollbackToSavepointQuery($name)
    {
        return "ROLLBACK TO SAVEPOINT $name;";
    }

    protected function getSavepointName($level)
    {
        return 'di_sp_' . $level;
    }

    protected function startTransactionInner()
    {
        if ($this->getStartTransactionQuery()) {
            $this->rq($this->getStartTransactionQuery(), true);
        }

        return $this;
    }

    protected function savepointInner($name)
    {
        if ($this->getSavepointQuery($name)) {
            $this->rq($this->getSavepointQuery($name), true);
        }

        return $this;
    }

    protected function releaseSavepointInner($name)
    {
        if ($this->getReleaseSavepointQuery($name)) {
            $this->rq($this->getReleaseSavepointQuery($name), true);
        }

        return $this;
    }

    protected function rollbackToSavepointInner($name)
    {
        if ($this->getRollbackToSavepointQuery($name)) {
            $this->rq($this->getRollbackToSavepointQuery($name), true);
        }

        return $this;
    }

    protected function commitTransactionInner()
    {
        if ($this->getCommitTransactionQuery()) {
            $this->rq($this->getCommitTransactionQuery(), true);
        }

        return $this;
    }

    protected function rollbackTransactionInner()
    {
        if ($this->getRollbackTransactionQuery()) {
            $this->rq($this->getRollbackTransactionQuery(), true);
        }

        return $this;
    }

    /**
     * Ref-counted / savepoint-based transaction nesting.
     *
     * MySQL/Postgres/SQLite have NO true nested transactions — a second
     * `START TRANSACTION` implicitly commits the first. So the real
     * BEGIN/COMMIT/ROLLBACK fire only at the OUTERMOST level; inner levels use
     * SAVEPOINTs (standard SQL, supported by all three engines — and a genuine
     * no-op on Mongo, which overrides the savepoint hooks). This lets a caller
     * wrap several `save()`s (each of which opens its own transaction) in one
     * outer transaction and have the whole thing commit or roll back atomically.
     *
     * WARNING: DDL (ALTER/CREATE/DROP/TRUNCATE) implicitly commits in MySQL and
     * destroys all savepoints — never run schema changes inside a nested
     * transaction, the "atomic" wrapper would silently become a lie.
     */
    public function startTransaction()
    {
        if ($this->transactionNestingLevel == 0) {
            $this->startTransactionInner();
        } else {
            $this->savepointInner(
                $this->getSavepointName($this->transactionNestingLevel)
            );
        }

        $this->transactionNestingLevel++;

        return $this;
    }

    public function commitTransaction()
    {
        if ($this->transactionNestingLevel) {
            $this->transactionNestingLevel--;

            if ($this->transactionNestingLevel == 0) {
                $this->commitTransactionInner();
            } else {
                // releasing the savepoint keeps the inner work in the still-open
                // outer transaction (nothing is durably committed until the
                // outermost commit)
                $this->unwindNestedSavepoint(
                    $this->getSavepointName($this->transactionNestingLevel),
                    false
                );
            }
        }

        return $this;
    }

    public function rollbackTransaction()
    {
        if ($this->transactionNestingLevel) {
            $this->transactionNestingLevel--;

            if ($this->transactionNestingLevel == 0) {
                $this->rollbackTransactionInner();
            } else {
                // partial rollback: undo only this nested level, the outer
                // transaction stays alive and can still commit
                $this->unwindNestedSavepoint(
                    $this->getSavepointName($this->transactionNestingLevel),
                    true
                );
            }
        }

        return $this;
    }

    /**
     * Release (commit path) or roll back to (rollback path) a nested savepoint,
     * tolerating the case where the server already unwound the WHOLE transaction
     * on its own — a deadlock (1213), or a lock-wait timeout under
     * innodb_rollback_on_timeout, rolls the entire transaction back and destroys
     * every savepoint. The SAVEPOINT op would then throw "SAVEPOINT … does not
     * exist" (1305) straight out of real_query() under mysqli strict mode; and
     * because rollbackTransaction() runs inside diModel::save()'s catch block,
     * that new throw would MASK the original exception the caller must see.
     *
     * A missing savepoint means the work is already gone — exactly what a
     * rollback wanted — so swallow the error and reset the nesting counter: the
     * whole transaction is void, so no outer level has anything left to release
     * or roll back, and the connection is safe to reuse (matters for long-lived
     * CLI workers that share one connection across jobs).
     */
    private function unwindNestedSavepoint($name, $rollback)
    {
        try {
            if ($rollback) {
                $this->rollbackToSavepointInner($name);
            } else {
                $this->releaseSavepointInner($name);
            }
        } catch (\Throwable $e) {
            // On the COMMIT path this is data loss the caller can't otherwise see
            // (it was told the commit succeeded while its work is gone), so leave
            // a trace. `false` = don't read the native error off a possibly-dead
            // link — we already have the message, and this catch must not throw.
            $this->_log(
                ($rollback ? 'ROLLBACK TO' : 'RELEASE') .
                    " SAVEPOINT $name failed, transaction presumed already rolled" .
                    " back by the server: {$e->getMessage()}",
                false
            );
            $this->transactionNestingLevel = 0;
        }

        return $this;
    }

    public function getTransactionNestingLevel()
    {
        return $this->transactionNestingLevel;
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
        return static::QUOTE_TABLE .
            $this->escape_string($string) .
            static::QUOTE_TABLE;
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
            $alias = $this->quoteField(substr($string, 0, $x)) . '.';
            $field = substr($string, $x + 1);
        } else {
            $alias = '';
            $field = $string;
        }

        if ($field === '*') {
            return $alias . $field;
        }

        return $alias . $this->quoteField($this->escape_string($field));
    }

    public function quoteField($string)
    {
        return static::QUOTE_FIELD . $string . static::QUOTE_FIELD;
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
        return $this->quoteValue($this->escape_string($string));
    }

    public function quoteValue($string)
    {
        return static::QUOTE_VALUE . $string . static::QUOTE_VALUE;
    }

    public function escapeFieldValue($field, $value, $operator = '=')
    {
        return "{$this->escapeField($field)} $operator {$this->escapeValue($value)}";
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

    protected function logError($q, \Exception $e)
    {
        Logger::getInstance()->variable(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        );
        Logger::getInstance()->log(
            "Error executing query `$q`: {$e->getMessage()}",
            \diRequest::requestUri()
        );

        return $this;
    }

    public static function extractTableNames($sql, $withAlias = false)
    {
        $tables = static::extractTableNamesWithAliases($sql);

        if (!$withAlias) {
            $tables = array_map(
                [static::class, 'removeAliasFromTableName'],
                static::extractTableNamesWithAliases($sql)
            );
        }

        return $tables;
    }

    public static function extractTableNamesWithAliases($sql)
    {
        $combiner = function (
            array $matches,
            int $tableIndex,
            int $aliasIndex,
            bool $filterKeywords = false
        ) {
            if (empty($matches[$tableIndex])) {
                return [];
            }

            $ar = [];

            foreach ($matches[$tableIndex] as $i => $table) {
                $alias = $matches[$aliasIndex][$i] ?? '';

                if (
                    $filterKeywords &&
                    preg_match(
                        // '/^(WHERE|GROUP\s+BY|ORDER\s+BY|LIMIT|HAVING|ON)(\s|$)/i',
                        '/^(WHERE|GROUP|BY|ORDER|LIMIT|HAVING|ON)(\s|$)/i',
                        $alias
                    )
                ) {
                    $alias = '';
                }

                $ar[] = join(' AS ', array_filter([$table, $alias]));
            }

            return $ar;
        };

        // table alias
        $pattern1 =
            '/^\s*[`"]?([a-zA-Z0-9_]+)[`"]?((\s+AS)?\s+[`"]?([a-zA-Z0-9_]+)[`"]?)?$/i';
        preg_match_all($pattern1, $sql, $matches1);

        if (array_filter($matches1[1])) {
            return $combiner($matches1, 1, 4);
        }

        // full or partial query
        $pattern =
            '/(\bFROM\b|\bJOIN\b|\bINTO\b)\s*[`"]?([a-zA-Z0-9_]+)[`"]?((\s+AS)?\s+[`"]?([a-zA-Z0-9_]+)[`"]?)?/i';

        preg_match_all($pattern, $sql, $matches);

        return $combiner($matches, 2, 5, true);
    }

    public static function removeAliasFromTableName(string $tableWithAlias)
    {
        return preg_replace('/\s+AS\s+.*|\s+.*/i', '', trim($tableWithAlias));
    }

    public function quoteTableNameWithAlias(string $tableWithAlias)
    {
        $parts = preg_split('/\s+AS\s+|\s+/i', trim($tableWithAlias), 2);

        if (!$parts) {
            return $tableWithAlias;
        }

        $tableName = $this->escapeTable($parts[0]);

        if (!isset($parts[1])) {
            return $tableName;
        }

        return "$tableName AS $parts[1]";
    }

    protected function prepareDumpCliCommandOptions($options = [])
    {
        $options = extend(
            [
                'tables' => [],
                'commandSuffix' => '',
                'filename' => '',
            ],
            $options
        );

        $options['commandSuffixWithFilename'] = $options['filename']
            ? "{$options['commandSuffix']} > {$options['filename']}"
            : '';

        return $options;
    }

    public static function setDumpCommand(string $command)
    {
        static::$dumpCommand = $command;
    }

    public static function setLocalDockerDumpCommand()
    {
        if (static::$localDockerDumpCommand) {
            static::setDumpCommand(static::$localDockerDumpCommand);
        }
    }

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
    abstract public function getDumpCliCommand($options = []);

    public function columnExists(string $table, string $column): bool
    {
        return array_key_exists($column, $this->getFields($table));
    }

    public function indexExists(string $table, string $index): bool
    {
        return in_array($index, $this->getIndexNames($table), true);
    }

    public function fkExists(string $table, string $foreignKey): bool
    {
        return in_array($foreignKey, $this->getForeignKeyNames($table), true);
    }

    /**
     * Names of the indexes defined on the table.
     *
     * Base fallback returns an empty list so indexExists() reports false on
     * engines with no index metadata to introspect (e.g. Mongo) or any custom
     * child that hasn't overridden this. SQL engines override it.
     */
    public function getIndexNames(string $table): array
    {
        return [];
    }

    /**
     * Names of the foreign-key constraints on the table. Same fallback
     * contract as getIndexNames().
     */
    public function getForeignKeyNames(string $table): array
    {
        return [];
    }
}
