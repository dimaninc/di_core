<?php

namespace diCore\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Data\Config;
use diCore\Database\Engine;
use diCore\Database\Legacy\Postgresql;
use diCore\Helper\ProcessHelper;
use diCore\Helper\StringHelper;
use diCore\Tool\Logger;
use diCore\Traits\Admin\DumpActions;

class Db extends \diBaseAdminController
{
    use DumpActions;

    protected $folderId;

    const MAX_TIMEOUT = 25;
    const MYSQL_SYSTEM_HOST = null;

    const CHMOD_FILE = 0664;

    /** лимит pg_dump/psql, секунды (дамп идёт одним запросом, без докачки) */
    const POSTGRES_CLI_TIMEOUT = 120;

    const FOLDER_LOCAL = 1;
    const FOLDER_CORE_SQL = 2;

    public static $foldersIdsAr = [self::FOLDER_LOCAL, self::FOLDER_CORE_SQL];
    public static $customFoldersIdsAr = [];

    public function __construct($params = [])
    {
        parent::__construct($params);

        $this->file = \diRequest::get('file', '');
        $this->folderId = \diRequest::get('folderId', 1);
        $this->folder = $this->getFolderById($this->folderId);
    }

    public static function getFolderIds()
    {
        return array_merge(static::$foldersIdsAr, static::$customFoldersIdsAr);
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
                throw HttpException::notFound("Undefined folder id#$id");
        }
    }

    public static function getMainEngine()
    {
        return \diCore\Database\Connection::get(Config::getMainDatabase())::getEngine();
    }

    public static function getDumpsFolder()
    {
        return Config::getDatabaseDumpPath();
    }

    public static function getCoreSqlFolder()
    {
        return dirname(__FILE__, 5) . '/sql/';
    }

    /**
     * @param $db \diDB
     * @return array
     */
    public static function getTablesList($db)
    {
        $ar = [
            'tablesForSelectAr' => [],
            'totalSize' => 0,
            'totalIndexSize' => 0,
        ];

        $tables = $db->getTablesInfo();
        foreach ($tables as $table) {
            $size = size_in_bytes($table['size']);
            $indexSize = size_in_bytes($table['index_size']);

            $ar['totalSize'] += $table['size'];
            $ar['totalIndexSize'] += $table['index_size'];

            $rows = '';

            if ($table['is_view']) {
                $size_str = ' [view]';
            } elseif ($table['size'] === null) {
                $size_str = ' [DAMAGED!]';
            } else {
                $size_str =
                    ($table['size'] ? ", {$size}" : '') .
                    ($table['index_size'] ? " (+index: {$indexSize})" : '');

                $rows = $table['rows'] ? ", {$table['rows']} rows" : '';
            }

            $ar['tablesForSelectAr'][
                $table['name']
            ] = "{$table['name']}{$size_str}{$rows}";
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
            'ok' => false,
            'code' => 0,
            'text' => '',
        ];

        if (
            isset($_FILES['dump']) &&
            trim($_FILES['dump']['name']) != '' &&
            $_FILES['dump']['size'] &&
            in_array(
                strtolower(StringHelper::fileExtension($_FILES['dump']['name'])),
                ['gz', 'sql']
            )
        ) {
            $fn = $this->folder . $_FILES['dump']['name'];

            if (move_uploaded_file($_FILES['dump']['tmp_name'], $fn)) {
                $ar['ok'] = 1;
            } else {
                $ar['text'] = "Unable to copy {$_FILES['dump']['tmp_name']} � $fn";
            }

            $ar = extend($ar, $this->getDumpInfo($fn));
        } else {
            $ar['code'] = $_FILES['dump']['error'];
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

            $sql = '';

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
        //$table_case_sensitivity = false;
        //$table_case_sensitivity_str = $table_case_sensitivity ? "cs" : "ci";

        $a = self::getTablesList($this->getDb());
        $allTablesAr = array_keys($a['tablesForSelectAr']);
        unset($a);

        $compress = \diRequest::get('compress', 0);
        $drops = \diRequest::get('drops', 0);
        $creates = \diRequest::get('creates', 0);
        $fields = \diRequest::get('fields', 0);
        $data = \diRequest::get('data', 0);
        $multiple = \diRequest::get('multiple', 0);
        $system = \diRequest::get('system', 0);

        if (!function_exists('gzopen')) {
            $compress = 0;
        }

        $fn = preg_replace('/[^A-Za-z0-9_\-\(\)\!]/', '', $this->file);
        if (!$fn) {
            $fn = $this->getDb()->getDatabase();
        }

        $tablesAr = explode(',', \diRequest::get('tables', '')) ?: $allTablesAr;
        $dateFnFormat = 'Y_m_d__H_i_s';
        $dateCommentFormat = 'Y/m/d H:i:s';
        $filename = $this->folder . $fn . '__dump_' . date($dateFnFormat) . '.sql';

        if ($compress) {
            $filename .= '.gz';
        }

        $ar = [
            'ok' => false,
            'system' => false,
            'text' => '',
            'format' => StringHelper::fileExtension($filename),
            'file' => basename($filename),
            'name' => $fn,
            'size' => null,
        ];

        // Построчный дампер ниже знает только MySQL (SHOW ... FROM `t`)
        if ($this->getDb() instanceof Postgresql) {
            return $this->createPostgresDump(
                $ar,
                $filename,
                array_values(array_intersect($tablesAr, $allTablesAr)),
                $compress
            );
        }

        // trying to exec system command
        if ($system) {
            $commandSuffix = $compress ? ' | gzip' : '';

            $command = $this->getDb()->getDumpCliCommand([
                'commandSuffix' => $commandSuffix,
                'filename' => $filename,
                'tables' => $tablesAr,
            ]);

            system($command, $a);

            if (!$a) {
                $ar['ok'] = true;
                $ar['system'] = true;

                $ar = extend($ar, $this->getDumpInfo($filename));

                return $ar;
            } else {
                Logger::getInstance()->log($command, 'Db');
                Logger::getInstance()->log($a, 'Db');
            }
        }

        $dt = date($dateCommentFormat);

        $fp = $compress ? gzopen($filename, 'w9') : fopen($filename, 'w');
        if (!$fp) {
            throw HttpException::internalServerError(
                "Unable to create db dump $filename"
            );
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

                $tableCharset = $this->getTableCharsetInfo($table);

                // FULL, so the per-column Collation comes with it: a `_bin`
                // column dumped without one comes back case-insensitive, which
                // collides values differing only in case under a UNIQUE index.
                $rs = $this->getDb()->q("SHOW FULL COLUMNS FROM `$table`");
                while ($r = $this->getDb()->fetch($rs)) {
                    if ($r->Default != null) {
                        if (!in_array($r->Default, ['CURRENT_TIMESTAMP'])) {
                            $r->Default = "'$r->Default'";
                        }
                    }

                    $name = $r->Field;
                    $type = $r->Type;
                    $null = $r->Null == 'YES' ? ' NULL' : ' NOT NULL';
                    $def = $r->Default != null ? ' DEFAULT ' . $r->Default . '' : '';
                    $extra = $r->Extra ? ' ' . $r->Extra : '';
                    $collate = $this->getColumnCollateClause($r, $tableCharset);

                    if (!$this->keepDefaultGenerated()) {
                        $extra = trim(str_replace('DEFAULT_GENERATED', '', $extra));
                        if ($extra) {
                            $extra = ' ' . $extra;
                        }
                    }

                    $fieldsAr[$r->Field] = $r->Type;
                    $createFieldsAr[] =
                        "\t`" .
                        $name .
                        '` ' .
                        $type .
                        $collate .
                        $null .
                        $def .
                        $extra;
                }

                $sql .= join(",\n", $createFieldsAr);
                unset($createFieldsAr);

                // get keys list
                $indexAr = [];

                $rs_keys = $this->getDb()->q("SHOW KEYS FROM `$table`");
                while ($r_key = $this->getDb()->fetch($rs_keys)) {
                    $key_name = $r_key->Key_name;

                    if ($key_name != 'PRIMARY' && $r_key->Non_unique == 0) {
                        $key_name = 'UNIQUE|' . $key_name;
                    }

                    if (
                        !isset($indexAr[$key_name]) ||
                        !is_array($indexAr[$key_name])
                    ) {
                        $indexAr[$key_name] = [];
                    }

                    $indexAr[$key_name][] = $r_key->Column_name;
                }

                $engine =
                    substr($table, 0, 13) == 'search_index_' ? 'MyISAM' : 'InnoDB';

                // get each key info
                foreach ($indexAr as $key_name => $columns) {
                    $sql .= ",\n";
                    $colNames = '`' . join('`,`', $columns) . '`';

                    $prefix = '';

                    foreach ($columns as $_c) {
                        foreach ($fieldsAr as $field => $type) {
                            if (
                                strtolower($field) == strtolower($_c) &&
                                strtolower($type) == 'text'
                            ) {
                                $prefix = 'FULLTEXT ';
                                $engine = 'MyISAM';

                                break 2;
                            }
                        }
                    }

                    if ($key_name == 'PRIMARY') {
                        $sql .= "\tPRIMARY KEY ($colNames)";
                    } else {
                        $unique = '';

                        if (substr($key_name, 0, 6) == 'UNIQUE') {
                            $key_name = substr($key_name, 7);
                            // The marker was being stripped and then dropped, so
                            // every unique index came back as a plain one and the
                            // restored table accepted duplicates. FULLTEXT wins if
                            // both are set — it cannot be unique anyway.
                            $unique = $prefix ? '' : 'UNIQUE ';
                        }

                        $sql .=
                            "\t" . $prefix . $unique . "KEY `$key_name`($colNames)";
                    }
                }

                // The TABLE's own charset, not the configured one. Since the
                // shipped dumps went utf8mb4 while Config::dbEncoding stayed
                // mb3, the two legitimately differ — and writing the config
                // value here would describe an mb4 table as mb3, so restoring
                // the dump would silently narrow it and truncate every 4-byte
                // character written afterwards.
                $sql .=
                    "\n)\nENGINE=$engine" .
                    $this->getRowFormatClause($engine, $tableCharset) .
                    "\n" .
                    $this->getTableCharsetClause($tableCharset) .
                    ";\n\n";
            }

            if ($data) {
                $col = null;
                $rs = null;

                try {
                    $col = \diCollection::createForTable($table);
                    $rc = $col->count();
                } catch (\Exception $e) {
                    // if no collection exists for table
                    $rs = $this->getDb()->rs($this->getDb()->escapeTable($table));
                    $rc = $this->getDb()->count($rs);
                }

                if ($fields && empty($fieldsAr)) {
                    $r = $col
                        ? $col->getModelByIdx()->get()
                        : $this->getDb()->fetch_ar($rs);

                    foreach ($r as $Field => $Value) {
                        $fieldsAr[$Field] = 1;
                    }

                    if ($rs) {
                        $this->getDb()->reset($rs);
                    }
                }

                $fieldsListString =
                    $fields && $fieldsAr
                        ? '(' .
                            join(
                                ',',
                                array_map(function ($field) {
                                    return '`' . $field . '`';
                                }, array_keys($fieldsAr))
                            ) .
                            ')'
                        : '';

                if ($multiple && $rc) {
                    $sql .= "INSERT INTO `$table`$fieldsListString VALUES";
                }

                $end_symbol = $multiple ? ',' : ';';

                for ($j = 0; $j < $rc; $j++) {
                    $r = $col
                        ? $col->getModelByIdx($j)->get()
                        : $this->getDb()->fetch_ar($rs);

                    if (!$multiple) {
                        $sql .= "INSERT INTO `$table`$fieldsListString VALUES";
                    }

                    if ($j == $rc - 1) {
                        $end_symbol = ';';
                    }

                    $values = array_map(function ($k) use ($r) {
                        return $r[$k] ?? null;
                    }, array_keys($fieldsAr));

                    $sql .=
                        '(' .
                        join(',', $this->prepareString($values)) .
                        "){$end_symbol}\n";

                    $this->tryToFlush($fp, $sql, $compress);
                }

                $sql .= "\n";
            }
        }

        $this->tryToFlush($fp, $sql, $compress, 0);

        $compress ? gzclose($fp) : fclose($fp);

        chmod($filename, self::CHMOD_FILE);

        $ar['ok'] = true;
        $ar = extend($ar, $this->getDumpInfo($filename));

        return $ar;
    }

    private function getDumpInfo($filename)
    {
        return [
            'size' => filesize($filename),
            'format' => StringHelper::fileExtension($filename),
            'file' => basename($filename),
        ];
    }

    /**
     * Charset, collation and row format of a real table, or nulls when they
     * cannot be read (a non-MySQL connection, an account without access to
     * information_schema) — in which case the callers fall back to the
     * configured values, i.e. to the behaviour this used to have.
     */
    private function getTableCharsetInfo($table)
    {
        $empty = ['charset' => null, 'collation' => null, 'rowFormat' => null];

        try {
            $rs = $this->getDb()->q(
                "SELECT t.TABLE_COLLATION AS collation, t.ROW_FORMAT AS rowFormat,
                        c.CHARACTER_SET_NAME AS charset
                 FROM information_schema.TABLES t
                 LEFT JOIN information_schema.COLLATIONS c
                        ON c.COLLATION_NAME = t.TABLE_COLLATION
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND t.TABLE_NAME = '" .
                    $this->getDb()->escape_string($table) .
                    "'"
            );
            $r = $rs ? $this->getDb()->fetch($rs) : null;
        } catch (\Exception $e) {
            $r = null;
        }

        return $r
            ? [
                'charset' => $r->charset ?: null,
                'collation' => $r->collation ?: null,
                'rowFormat' => $r->rowFormat ?: null,
            ]
            : $empty;
    }

    private function getTableCharsetClause(array $info)
    {
        if (!$info['charset']) {
            return Config::getDbCharsetClause();
        }

        return "DEFAULT CHARSET={$info['charset']}" .
            ($info['collation'] ? "\nCOLLATE={$info['collation']}" : '');
    }

    /**
     * An indexed varchar(255) is 1020 bytes in mb4, over the 767-byte cap of
     * InnoDB's older COMPACT format — so a dump that does not carry the row
     * format cannot be restored where innodb_default_row_format is still
     * COMPACT. Only stated for InnoDB, and only as the table actually has it.
     */
    private function getRowFormatClause($engine, array $info)
    {
        $format = strtoupper((string) $info['rowFormat']);

        return $engine === 'InnoDB' &&
            in_array($format, ['DYNAMIC', 'COMPRESSED'], true)
            ? "\nROW_FORMAT=$format"
            : '';
    }

    /**
     * `CHARACTER SET … COLLATE …` for a column whose collation differs from the
     * table default — most importantly a `_bin` one, whose case sensitivity is
     * lost the moment the column inherits the table's `_ci` default instead.
     */
    private function getColumnCollateClause($column, array $info)
    {
        $collation = $column->Collation ?? null;
        $underscore = $collation ? strpos($collation, '_') : false;

        if (!$collation || $collation === $info['collation'] || !$underscore) {
            return '';
        }

        // A collation always names its charset in its prefix.
        $charset = substr($collation, 0, $underscore);

        return " CHARACTER SET $charset COLLATE $collation";
    }

    /** Переопределяется в контроллере проекта, если дампу нужно больше времени */
    protected function getPostgresCliTimeout()
    {
        return static::POSTGRES_CLI_TIMEOUT;
    }

    /**
     * Дамп PostgreSQL штатным pg_dump: массив аргументов без шелла, пароль в PGPASSWORD.
     */
    protected function createPostgresDump(array $ar, $filename, array $tablesAr, $compress)
    {
        /** @var Postgresql $db */
        $db = $this->getDb();
        $plain = $compress ? substr($filename, 0, -3) : $filename;
        $creates = \diRequest::get('creates', 0);
        $data = \diRequest::get('data', 0);

        $res = ProcessHelper::run(
            $db->getDumpArgv([
                'tables' => $tablesAr,
                'drops' => \diRequest::get('drops', 0),
                'schemaOnly' => $creates && !$data,
                'dataOnly' => $data && !$creates,
                'columnInserts' => \diRequest::get('fields', 0),
            ]),
            $this->getPostgresCliTimeout(),
            $db->getCliEnv(),
            ['stdout' => $plain]
        );

        if ($res['code'] !== 0) {
            @unlink($plain);
            Logger::getInstance()->log(
                'pg_dump failed (' . $res['code'] . '): ' . join("\n", $res['output']),
                'Db'
            );
            $ar['text'] = $res['timedOut']
                ? 'pg_dump timed out'
                : 'pg_dump failed: ' . join("\n", $res['output']);

            return $ar;
        }

        // Нулевой код без файла – не дамп (например, docker-команда пишет внутрь контейнера)
        if (!is_file($plain) || !filesize($plain)) {
            @unlink($plain);
            $ar['text'] = 'pg_dump finished but produced no dump file';
            Logger::getInstance()->log($ar['text'], 'Db');

            return $ar;
        }

        if ($compress) {
            try {
                static::gzipFile($plain, $filename);
            } catch (\Throwable $e) {
                // Несжатый дамп остаётся: он целый, а сломан только архив
                @unlink($filename);
                $ar['text'] = 'Unable to compress the dump: ' . $e->getMessage();
                Logger::getInstance()->log($ar['text'], 'Db');

                return $ar;
            }

            unlink($plain);
        }

        chmod($filename, self::CHMOD_FILE);

        $ar['ok'] = true;
        $ar['system'] = true;

        return extend($ar, $this->getDumpInfo($filename));
    }

    /**
     * @throws \RuntimeException
     */
    protected static function gzipFile($src, $dst)
    {
        $in = is_file($src) ? @fopen($src, 'rb') : false;
        $out = $in ? @gzopen($dst, 'w9') : false;

        if (!$in || !$out) {
            $in && fclose($in);

            throw new \RuntimeException("Unable to open $src or $dst");
        }

        while (!feof($in)) {
            $chunk = fread($in, 1 << 20);

            if ($chunk === false || ($chunk !== '' && !gzwrite($out, $chunk))) {
                fclose($in);
                gzclose($out);

                throw new \RuntimeException("Unable to write $dst");
            }
        }

        fclose($in);

        if (!gzclose($out)) {
            throw new \RuntimeException("Unable to close $dst");
        }
    }

    /**
     * Потоково, без чтения файла в память. Обрезанный и битый архив ловятся тем, что поток
     * не дошёл до конца (CRC zlib проверяет в конце потока).
     *
     * @throws \RuntimeException
     */
    protected static function gunzipFile($src, $dst)
    {
        $in = is_file($src) ? @fopen($src, 'rb') : false;

        if (!$in) {
            throw new \RuntimeException("Unable to read $src");
        }

        $out = @fopen($dst, 'wb');
        $ctx = inflate_init(ZLIB_ENCODING_GZIP);

        try {
            if (!$out) {
                throw new \RuntimeException("Unable to write $dst");
            }

            while (!feof($in)) {
                $chunk = fread($in, 1 << 20);

                if ($chunk === false) {
                    throw new \RuntimeException("Unable to read $src");
                }

                $data = $chunk === '' ? '' : @inflate_add($ctx, $chunk);

                if ($data === false) {
                    throw new \RuntimeException("$src is not a valid gzip archive");
                }

                if ($data !== '' && fwrite($out, $data) !== strlen($data)) {
                    throw new \RuntimeException("Unable to write $dst");
                }
            }

            if (inflate_get_status($ctx) !== ZLIB_STREAM_END) {
                throw new \RuntimeException("$src is truncated");
            }

            if (!fclose($out)) {
                $out = null;

                throw new \RuntimeException("Unable to write $dst");
            }

            $out = null;
        } finally {
            fclose($in);
            $out && fclose($out);
        }
    }

    /**
     * @return array|null Результат restoreAction; null – psql на хосте нет, нужен запасной путь.
     *   Сбой psql – ошибка, а не повтор парсером: single-transaction ничего не применил бы
     *   наполовину, но повтор дал бы дубли, если psql упал уже после применения.
     */
    protected function restorePostgres($ffn, $fn, $isGz)
    {
        /** @var Postgresql $db */
        $db = $this->getDb();
        $file = $ffn;

        if ($isGz) {
            // psql gz не читает; битый архив – ошибка, а не «успешно применённый» огрызок
            $file = tempnam(sys_get_temp_dir(), 'dbrestore');

            try {
                static::gunzipFile($ffn, $file);
            } catch (\Throwable $e) {
                @unlink($file);
                Logger::getInstance()->log($e->getMessage(), 'Db');

                return array_merge(
                    [
                        'ok' => false,
                        'errors' => [$e->getMessage()],
                        'file' => $fn,
                        'startFrom' => false,
                        'system' => true,
                    ],
                    static::getTablesList($db)
                );
            }
        }

        try {
            $res = ProcessHelper::run(
                $db->getRestoreArgv(),
                $this->getPostgresCliTimeout(),
                $db->getCliEnv(),
                ['stdin' => $file]
            );
        } finally {
            if ($isGz) {
                @unlink($file);
            }
        }

        // 127 – «не найдено» при запуске через шелл
        if (in_array($res['code'], [ProcessHelper::EXIT_NOT_STARTED, 127], true)) {
            Logger::getInstance()->log('psql is not available, using the line parser', 'Db');

            return null;
        }

        $ok = $res['code'] === 0;

        if (!$ok) {
            Logger::getInstance()->log(
                'psql failed (' . $res['code'] . '): ' . join("\n", $res['output']),
                'Db'
            );
        }

        return array_merge(
            [
                'ok' => $ok,
                'errors' => $ok
                    ? []
                    : [
                        $res['timedOut']
                            ? 'psql timed out'
                            : 'psql failed: ' . join("\n", $res['output']),
                    ],
                'file' => $fn,
                'startFrom' => false,
                'system' => true,
            ],
            static::getTablesList($db)
        );
    }

    /**
     * Строка целиком вместе с переводом строки, любой длины: fgets($max) режет длинную
     * строку на куски, и склейка добавляла бы лишний "\n".
     *
     * @return string|false
     */
    private function readFullLine($file, $fgets_func, $feof_func, $chunk)
    {
        $line = $fgets_func($file, $chunk);

        while ($line !== false && substr($line, -1) !== "\n" && !$feof_func($file)) {
            $more = $fgets_func($file, $chunk);

            if ($more === false) {
                break;
            }

            $line .= $more;
        }

        return $line;
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
        // обратный слэш в начале строки – метакоманды psql (\restrict в дампах pg_dump), не SQL
        $sql_comments = ['#', '--', '\\'];
        $max_line_length = 65536;
        $ending = ';';

        $fn = $this->file;
        $start_from = \diRequest::get('start_from', 0);
        $system = \diRequest::get('system', 0);

        if (!$fn) {
            throw HttpException::badRequest('No file defined');
        }

        $ffn = $this->folder . $fn;

        $is_gz = StringHelper::fileExtension($fn) == 'gz';
        $fopen_func = $is_gz ? 'gzopen' : 'fopen';
        $fgets_func = $is_gz ? 'gzgets' : 'fgets';
        $fclose_func = $is_gz ? 'gzclose' : 'fclose';
        $ftell_func = $is_gz ? 'gztell' : 'ftell';
        $fseek_func = $is_gz ? 'gzseek' : 'fseek';
        $feof_func = $is_gz ? 'gzeof' : 'feof';

        $isPostgres = $this->getDb() instanceof Postgresql;

        // psql – только по явному system=1 (как mysql); без него и там, где psql на хосте нет,
        // работает построчный парсер (он не понимает $$-тела функций и триггеров)
        if ($system && $isPostgres && ($result = $this->restorePostgres($ffn, $fn, $is_gz))) {
            return $result;
        }

        if ($system && !$isPostgres) {
            if ($is_gz) {
                throw HttpException::badRequest(
                    'System method can execute non-archived SQL only'
                );
            }

            $params = [
                '--user=' . $this->getDb()->getUsername(),
                '--password=' . $this->getDb()->getPassword(),
            ];

            if (static::MYSQL_SYSTEM_HOST) {
                $params[] = '--host=' . static::MYSQL_SYSTEM_HOST;
            }

            // todo: add other engines support
            $command =
                'mysql ' .
                join(' ', $params) .
                ' ' .
                $this->getDb()->getDatabase() .
                ' < ' .
                $ffn;
            system($command, $a);

            if (!$a) {
                return [
                    'ok' => true,
                    'system' => true,
                ];
            }
        }

        $errorsAr = [];

        if (is_file($ffn) && ($file = $fopen_func($ffn, 'r'))) {
            simple_debug("starting $fn from $start_from", 'db-restore');

            if ($start_from) {
                $fseek_func($file, $start_from);
            }

            $line_counter = 0;

            $in_quotes = false;
            $query = '';
            // Что вставить между предыдущей строкой и следующей: внутри кавычек – её собственный
            // перевод строки (данные не меняем), снаружи – "\n"
            $glue = "\n";

            while (($raw = $this->readFullLine($file, $fgets_func, $feof_func, $max_line_length)) !== false) {
                $startInQuotes = $in_quotes;
                // Снаружи кавычек – прежняя нормализация (trim, CR → LF)
                $line = trim(str_replace(["\r\n", "\r"], "\n", $raw));

                if (!$startInQuotes) {
                    $to_skip_line = false;

                    reset($sql_comments);

                    foreach ($sql_comments as $sql_c) {
                        if (!$line || substr($line, 0, strlen($sql_c)) == $sql_c) {
                            $to_skip_line = true;
                            break;
                        }
                    }

                    if ($to_skip_line) {
                        $line_counter++;

                        continue;
                    }
                }

                $body = preg_replace('/\r?\n\z/', '', $raw);

                if ($isPostgres) {
                    // standard_conforming_strings: обратный слэш в строке обычный символ
                    $quotes_cc = substr_count($body, "'");
                } else {
                    $line_deslashed = str_replace('\\\\', '', $body);

                    $quotes_cc =
                        substr_count($line_deslashed, "'") -
                        substr_count($line_deslashed, "\\'");
                }
                if ($quotes_cc % 2 != 0) {
                    $in_quotes = !$in_quotes;
                }

                // Пробелы и перевод строки внутри строкового литерала – это данные
                if ($in_quotes) {
                    $line = $startInQuotes ? $raw : ltrim($raw);
                    $nextGlue = '';
                } elseif ($startInQuotes) {
                    $line = rtrim($body);
                    $nextGlue = "\n";
                } else {
                    $nextGlue = "\n";
                }

                if ($query !== '') {
                    $query .= $glue;
                }
                $glue = $nextGlue;
                $query .= $line;

                /*
				if (strtoupper(substr($line, 0, 9)) == 'DELIMITER')
				{
					$ending = trim(substr($line, 10));
				}
				*/

                $lineTerminated = substr($line, -1) == ';' && $ending == ';';

                if ($lineTerminated && !$in_quotes) {
                    if (substr($query, 0, 12) == '/*!40101 SET') {
                        // skipping this trash
                        $query = '';
                    } elseif (substr($query, -23) == 'DEFAULT CHARSET=latin1;') {
                        $query =
                            substr($query, 0, -7) . Config::getDbEncoding() . ';';
                    }

                    $this->getDb()->resetLog();

                    if ($query && !$this->getDb()->q($query)) {
                        $errorsAr[] =
                            'Line: ' .
                            ($line_counter + 1) .
                            "\n" .
                            "Unable to execute query \"$query\"\n" .
                            'Error: ' .
                            join('', $this->getDb()->getLog()) .
                            '';

                        // В CLI (миграции) дальше не идём: следующие операторы опираются на
                        // упавший, и файл оказался бы применён вразнобой. Всё до этой строки
                        // уже применено – это и сказано в ошибке.
                        if (static::isCli()) {
                            $errorsAr[] =
                                'Restore stopped at line ' .
                                ($line_counter + 1) .
                                '; statements before it are already applied';

                            break;
                        }
                    }

                    simple_debug("line executed: $line_counter", 'db-restore');

                    $query = '';
                }

                $line_counter++;

                // Лимит – для страницы «Дампы базы данных»: браузер продолжает с startFrom
                // следующим запросом. В CLI (миграции) продолжать некому – файл дочитывается.
                // Только на границе оператора: возобновление начинается с пустого $query и
                // без кавычек, посреди оператора оно разорвало бы его.
                if (
                    !static::isCli() &&
                    $query === '' &&
                    $this->checkTimeout($startTime, static::MAX_TIMEOUT)
                ) {
                    $startFrom = $ftell_func($file);

                    break;
                }
            }

            // Файл дочитан, а оператор не закрыт: хвост иначе пропал бы молча при ok=true
            if ($startFrom === false && !$errorsAr && trim($query) !== '') {
                $errorsAr[] =
                    'Unparsed tail at the end of file (unterminated statement or quote): ' .
                    mb_substr(trim($query), 0, 200);
            }

            $fclose_func($file);

            if ($isPostgres) {
                // Заголовок pg_dump оставил search_path='' и др. на соединении приложения
                $this->getDb()->resetSession();
            }
        } else {
            $errorsAr[] = 'Unable to open file ' . $this->folder . $fn;
        }

        $ar = [
            'ok' => !count($errorsAr),
            'errors' => $errorsAr,
            'file' => $fn,
            'startFrom' => $startFrom,
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
            throw HttpException::badRequest('Run the script from CLI please');
        }

        $table = $this->param(0);

        $model = \diModel::createForTable($table);

        if (!$model->modelType()) {
            throw HttpException::notFound("Model for table '$table' not found");
        }

        if ($model->getConnectionEngine() !== Engine::MONGO) {
            throw HttpException::badRequest('Model should be mongo instance');
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
