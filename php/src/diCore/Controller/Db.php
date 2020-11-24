<?php

namespace diCore\Controller;

use diCore\Data\Config;
use diCore\Database\Engine;
use diCore\Traits\Admin\DumpActions;

class Db extends \diBaseAdminController
{
    use DumpActions;

	protected $folderId;

	const MAX_TIMEOUT = 25;
	const MYSQL_SYSTEM_HOST = null;

	const CHMOD_FILE = 0664;

	const FOLDER_LOCAL = 1;
	const FOLDER_CORE_SQL = 2;

	public static $foldersIdsAr = [
		self::FOLDER_LOCAL,
		self::FOLDER_CORE_SQL,
	];

	public function __construct($params = [])
	{
	    parent::__construct($params);

		$this->file = \diRequest::get("file", "");
		$this->folderId = \diRequest::get("folderId", 1);
		$this->folder = $this->getFolderById($this->folderId);
	}

	/**
	 * @param $id integer
	 * @return string
	 * @throws \Exception
	 */
	public static function getFolderById($id)
	{
		switch ($id) {
			case self::FOLDER_LOCAL:
				return static::getDumpsFolder();

			case self::FOLDER_CORE_SQL:
				return static::getCoreSqlFolder();

			default:
				throw new \Exception("Undefined folder id#$id");
		}
	}

	public static function getDumpsFolder()
	{
		return Config::getDatabaseDumpPath();
	}

	public static function getCoreSqlFolder()
	{
		return dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/sql/';
	}

	/**
	 * @param $db \diDB
	 * @return array
	 */
	public static function getTablesList($db)
	{
		$ar = [
			"tablesForSelectAr" => [],
			"totalSize" => 0,
			"totalIndexSize" => 0,
		];

		$tables = $db->getTablesInfo();
		foreach ($tables as $table) {
			$size = size_in_bytes($table['size']);
			$indexSize = size_in_bytes($table['index_size']);

			$ar["totalSize"] += $table['size'];
			$ar["totalIndexSize"] += $table['index_size'];

			$rows = "";

			if ($table['is_view']) {
                $size_str = ' [view]';
            } elseif ($table['size'] === null) {
                $size_str = ' [DAMAGED!]';
			} else {
				$size_str = ($table['size'] ? ", {$size}" : '') .
                    ($table['index_size'] ? " (+index: {$indexSize})" : '');

				$rows = $table['rows']
                    ? ", {$table['rows']} rows"
                    : '';
			}

			$ar["tablesForSelectAr"][$table['name']] = "{$table['name']}{$size_str}{$rows}";
		}

		return $ar;
	}

	public function tablesAction()
	{
		return self::getTablesList($this->getDb());
	}

	public function uploadAction()
	{
		$ar = [
			"ok" => false,
			"code" => 0,
			"text" => "",
		];

        if (
            isset($_FILES["dump"]) &&
            trim($_FILES["dump"]["name"]) != "" &&
            $_FILES["dump"]["size"] &&
            in_array(strtolower(get_file_ext($_FILES["dump"]["name"])), ["gz", "sql"])
        ) {
            $fn = $this->folder . $_FILES["dump"]["name"];

            if (move_uploaded_file($_FILES["dump"]["tmp_name"], $fn)) {
                $ar["ok"] = 1;
            } else {
                $ar["text"] = "Unable to copy {$_FILES["dump"]["tmp_name"]} � $fn";
            }

            $ar = extend($ar, $this->getDumpInfo($fn));
        } else {
            $ar["code"] = $_FILES["dump"]["error"];
        }

		return $ar;
	}

    private function prepareString($s)
    {
        for ($i = 0; $i < count($s); $i++) {
            if ($s[$i] === null) {
                $s[$i] = 'NULL';
            } else {
                $s[$i] = addslashes($s[$i]);
                $s[$i] = str_replace("\r\n", "\\r\\n", $s[$i]);
                $s[$i] = str_replace("\r", "\\r", $s[$i]);
                $s[$i] = str_replace("\n", "\\n", $s[$i]);
                $s[$i] = "'" . $s[$i] . "'";
            }
        }

        return $s;
    }

	private function tryToFlush(&$fp, &$sql, $compress, $len = 2500000)
	{
		if (strlen($sql) > $len) {
			$bytesWritten = $compress ? gzwrite($fp, $sql) : fwrite($fp, $sql);

			$sql = "";

			if (!$compress) {
				fflush($fp);
			}
		}
	}

	protected function keepDefaultGenerated()
    {
        return false;
    }

	public function createAction()
	{
		$table_case_sensitivity = false;
		//$table_case_sensitivity_str = $table_case_sensitivity ? "cs" : "ci";

		$compress = \diRequest::get("compress", 0);
		$drops = \diRequest::get("drops", 0);
		$creates = \diRequest::get("creates", 0);
		$fields = \diRequest::get("fields", 0);
		$data = \diRequest::get("data", 0);
		$multiple = \diRequest::get("multiple", 0);
		$system = \diRequest::get("system", 0);

		if (!function_exists('gzopen')) {
			$compress = 0;
		}

		$fn = preg_replace('/[^A-Za-z0-9_\-\(\)\!]/', "", $this->file);
		if (!$fn) {
			$fn = $this->getDb()->getDatabase();
		}

		$tablesAr = explode(",", \diRequest::get("tables", ""));
		$tablesList = join(" ", $tablesAr);

		$date_fn_format = "Y_m_d__H_i_s";
		$date_sql_comment = "Y/m/d H:i:s";

		$filename = $this->folder . $fn . "__dump_" . date($date_fn_format) . ".sql";
		if ($compress) {
			$filename .= ".gz";
		}

		$ar = [
			"ok" => false,
			"system" => false,
			"text" => "",
			"format" => get_file_ext($filename),
			"file" => basename($filename),
			"name" => $fn,
			'size' => null,
		];

		// trying to exec system command
		if ($system) {
			$command_suffix = $compress ? " | gzip" : "";

			$command = "mysqldump --host=" . $this->getDb()->getHost() . " --user=" . $this->getDb()->getUsername() .
				" --password=" . $this->getDb()->getPassword() . " --opt --skip-extended-insert " .
				$this->getDb()->getDatabase() . " {$tablesList} {$command_suffix} > $filename";

			system($command, $a);

			if (!$a) {
				$ar["ok"] = true;
				$ar["system"] = true;

				$ar = extend($ar, $this->getDumpInfo($filename));

				return $ar;
			}
		}
		//

		$dt = date($date_sql_comment);

		$a = self::getTablesList($this->getDb());
		$allTablesAr = array_keys($a["tablesForSelectAr"]);
		unset($a);

		if (!$tablesAr) {
			$tablesAr = $allTablesAr;
		}

		$fp = $compress ? gzopen($filename, "w9") : fopen($filename, "w");
		if (!$fp) {
			throw new \Exception("Unable to create db dump $filename");
		}

		$sql = <<<EOF
# [diCMS] database backup
# http://www.cadr25.ru
#
# Database: {$this->getDb()->getDatabase()}
# Database Server: {$this->getDb()->getHost()}
#
# Backup Date: {$dt}

EOF;

		foreach ($tablesAr as $table) {
			if (!in_array($table, $allTablesAr)) {
				continue;
			}

			$fieldsAr = [];

			if ($drops) {
				$sql .= "DROP TABLE IF EXISTS `$table`;\n";
			}

			if ($creates) {
				$sql .= "CREATE TABLE `$table` (\n";

				$createFieldsAr = [];

				$rs = $this->getDb()->q("SHOW FIELDS FROM `$table`");
				while ($r = $this->getDb()->fetch($rs)) {
					if ($r->Default != NULL) {
						if (!in_array($r->Default, ["CURRENT_TIMESTAMP"])) {
							$r->Default = "'$r->Default'";
						}
					}

					$name = $r->Field;
					$type = $r->Type;
					$null = $r->Null == "YES" ? " NULL" : " NOT NULL";
					$def  = $r->Default != NULL ? " DEFAULT ".$r->Default."" : "";
					$extra = $r->Extra ? " " . $r->Extra : "";

					if (!$this->keepDefaultGenerated()) {
					    $extra = trim(str_replace('DEFAULT_GENERATED', '', $extra));
					    if ($extra) {
					        $extra = ' ' . $extra;
                        }
                    }

					$fieldsAr[$r->Field] = $r->Type;
					$createFieldsAr[] = "\t`" . $name . "` " . $type . $null . $def . $extra;
				}

				$sql .= join(",\n", $createFieldsAr);
				unset($createFieldsAr);

				// get keys list
				$indexAr = array();

				$rs_keys = $this->getDb()->q("SHOW KEYS FROM `$table`");
				while ($r_key = $this->getDb()->fetch($rs_keys)) {
					$key_name = $r_key->Key_name;

					if ($key_name != "PRIMARY" && $r_key->Non_unique == 0) {
						$key_name = "UNIQUE|" . $key_name;
					}

					if (!isset($indexAr[$key_name]) || !is_array($indexAr[$key_name])) {
						$indexAr[$key_name] = array();
					}

					$indexAr[$key_name][] = $r_key->Column_name;
				}

				$engine = substr($table, 0, 13) == "search_index_" ? "MyISAM" : "InnoDB";

				// get each key info
				foreach ($indexAr as $key_name => $columns) {
					$sql .= ",\n";
					$col_names = "`".join("`,`", $columns)."`";

					$prefix = "";

					foreach ($columns as $_c) {
						foreach ($fieldsAr as $field => $type) {
							if (strtolower($field) == strtolower($_c) && strtolower($type) == "text") {
								$prefix = "FULLTEXT ";
								$engine = "MyISAM";

								break(2);
							}
						}
					}

					if ($key_name == "PRIMARY") {
						$sql .= "\tPRIMARY KEY ($col_names)";
					} else {
						if (substr($key_name, 0, 6) == "UNIQUE") {
							$key_name = substr($key_name, 7);
						}

						$sql .= "\t" . $prefix . "KEY `$key_name`($col_names)";
					}
				}

				$sql .= "\n)\nENGINE=$engine\nDEFAULT CHARSET=" . Config::getDbEncoding() .
					"\nCOLLATE=" . Config::getDbCollation() . ";\n\n";
			}

			if ($data) {
				$rs = $this->getDb()->rs($this->getDb()->escapeTable($table));
				$rc = $this->getDb()->count($rs);

				if ($fields && empty($fieldsAr)) {
					$r = $this->getDb()->fetch($rs);

					foreach ($r as $Field => $Value) {
						$fieldsAr[$Field] = 1;
					}

					$this->getDb()->reset($rs);
				}

				$fieldsListString = $fields && $fieldsAr ? '(' . join(',', array_map(function($field) {
						return '`' . $field . '`';
					}, array_keys($fieldsAr))) . ')' : '';

				if ($multiple && $rc) {
					$sql .= "INSERT INTO `{$table}`{$fieldsListString} VALUES";
				}

				$end_symbol = $multiple ? ',' : ';';

                for ($j = 0; $j < $rc; $j++) {
                    $r = $this->getDb()->fetch_array($rs);

                    if (!$multiple) {
                        $sql .= "INSERT INTO `{$table}`{$fieldsListString} VALUES";
                    }

                    if ($j == $rc - 1) {
                        $end_symbol = ';';
                    }

                    $sql .= '(' . join(',', $this->prepareString(array_values($r))) . "){$end_symbol}\n";

                    $this->tryToFlush($fp, $sql, $compress);
                }

				$sql .= "\n";
			}
		}

		$this->tryToFlush($fp, $sql, $compress, 0);

		$compress ? gzclose($fp) : fclose($fp);

		chmod($filename, self::CHMOD_FILE);

		$ar["ok"] = true;
		$ar = extend($ar, $this->getDumpInfo($filename));

		return $ar;
	}

	private function getDumpInfo($filename)
	{
		return [
			"size" => filesize($filename),
			"format" => get_file_ext($filename),
			"file" => basename($filename),
		];
	}

	private function checkTimeout($time, $timeout)
	{
		return utime() - $time >= $timeout;
	}

	public function restoreAction()
	{
		$startTime = utime();
		$startFrom = false;

		// comments
		$sql_comments = ["#", "--"];
		$max_line_length = 65536;
		$ending = ';';

		$fn = $this->file;
		$start_from = \diRequest::get("start_from", 0);
		$system = \diRequest::get("system", 0);

		if (!$fn)
		{
			throw new \Exception("No file defined");
		}

		$ffn = $this->folder.$fn;

		$is_gz = get_file_ext($fn) == "gz";
		$fopen_func = $is_gz ? "gzopen" : "fopen";
		$fgets_func = $is_gz ? "gzgets" : "fgets";
		$fclose_func = $is_gz ? "gzclose" : "fclose";
		$ftell_func = $is_gz ? "gztell" : "ftell";
		$fseek_func = $is_gz ? "gzseek" : "fseek";

		if ($system)
		{
			if ($is_gz)
			{
				throw new \Exception('System method can execute non-archived SQL only');
			}

			$params = [
				'--user=' . $this->getDb()->getUsername(),
				'--password=' . $this->getDb()->getPassword(),
			];

			if (static::MYSQL_SYSTEM_HOST)
			{
				$params[] = '--host=' . static::MYSQL_SYSTEM_HOST;
			}

			$command = 'mysql ' . join(' ', $params) . ' ' . $this->getDb()->getDatabase() . ' < ' . $ffn;
			system($command, $a);

			if (!$a)
			{
				return [
					"ok" => true,
					"system" => true,
				];
			}
		}

		$errorsAr = [];

		if (is_file($ffn) && $file = $fopen_func($ffn, "r"))
		{
			simple_debug("starting $fn from $start_from", "db-restore");

			if ($start_from)
			{
				$fseek_func($file, $start_from);
			}

			$line_counter = 0;

			$in_quotes = false;
			$query = "";

			while ($line = $fgets_func($file, $max_line_length))
			{
				$line = str_replace("\r\n", "\n", $line);
				$line = str_replace("\r", "\n", $line);
				$line = trim($line);

				if (!$in_quotes)
				{
					$to_skip_line = false;

					reset($sql_comments);

					foreach($sql_comments as $sql_c)
					{
						if (!$line || substr($line, 0, strlen($sql_c)) == $sql_c)
						{
							$to_skip_line = true;
							break;
						}
					}

					if ($to_skip_line)
					{
						$line_counter++;

						continue;
					}
				}

				$line_deslashed = str_replace("\\\\", "", $line);

				$quotes_cc = substr_count($line_deslashed, "'") - substr_count($line_deslashed, "\\'");
				if ($quotes_cc % 2 != 0)
				{
					$in_quotes = !$in_quotes;
				}

				if ($query)
				{
					$query .= "\n";
				}
				$query .= $line;

				/*
				if (strtoupper(substr($line, 0, 9)) == 'DELIMITER')
				{
					$ending = trim(substr($line, 10));
				}
				*/

				$lineTerminated = substr($line, -1) == ';' && $ending == ';';

				if ($lineTerminated && !$in_quotes)
				{
					if (substr($query, 0, 12) == "/*!40101 SET")
					{
						// skipping this trash
						$query = "";
					}
					elseif (substr($query, -23) == "DEFAULT CHARSET=latin1;")
					{
						$query = substr($query, 0, -7) . Config::getDbEncoding() . ";";
					}

					$this->getDb()->resetLog();

					if ($query && !$this->getDb()->q($query))
					{
						$errorsAr[] =
							"Line: " . $line_counter . "\n" .
							"Unable to execute query \"$query\"\n" .
							"Error: " . join("", $this->getDb()->getLog()) . "";
					}

					simple_debug("line executed: $line_counter", "db-restore");

					$query = "";
				}

				$line_counter++;

				if ($this->checkTimeout($startTime, static::MAX_TIMEOUT))
				{
					$startFrom = $ftell_func($file);

					break;
				}
			}

			$fclose_func($file);
		}
		else
		{
			$errorsAr[] = "Unable to open file ".$this->folder.$fn;
		}

		$ar = [
			"ok" => !count($errorsAr),
			"errors" => $errorsAr,
			"file" => $fn,
			"startFrom" => $startFrom,
			'system' => false,
		];

		$ar = array_merge($ar, $this->getTablesList($this->getDb()));

		return $ar;
	}

    /**
     * Migrate all mysql records to mongo
     */
	public function migrateToMongoAction()
    {
        if (!\diRequest::isCli()) {
            throw new \Exception('Run the script from CLI please');
        }

        $table = $this->param(0);

        $model = \diModel::createForTable($table);

        if (!$model->modelType()) {
            throw new \Exception('Model for table "' . $table . '" not found');
        }

        if ($model->getConnectionEngine() !== Engine::MONGO) {
            throw new \Exception('Model should be mongo instance');
        }

        $counter = 0;
        $log = [];

        $rs = $this->getDb()->rs($table, 'ORDER BY id ASC');
        while ($ar = $this->getDb()->ar($rs)) {
            unset($ar['id']);
            $model = \diModel::createForTable($table, $ar);
            $model->save();
            unset($model);

            $counter++;
        }

        return [
            'counter' => $counter,
            'log' => $log,
        ];
    }
}
