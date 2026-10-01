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

    protected static $dumpCommand = 'pg_dump';
    protected static $localDockerDumpCommand = 'docker exec -t postgres pg_dump';

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

    /**
     * pg_try_advisory_lock polled until the deadline: pg_advisory_lock would wait
     * forever, and lock_timeout would abort it with an error instead of a false.
     */
    public function acquireNamedLock(string $name, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + max(0, $timeoutSeconds);
        $key = $this->postgresLockKey($name);

        do {
            $rs = $this->q("SELECT pg_try_advisory_lock($key) AS l");
            $row = $rs ? $this->fetch_array($rs) : null;

            if (!$row) {
                return false;
            }

            if (in_array($row['l'], [true, 't', 1, '1'], true)) {
                return true;
            }

            usleep(50000);
        } while (microtime(true) < $deadline);

        return false;
    }

    public function releaseNamedLock(string $name): void
    {
        $this->q('SELECT pg_advisory_unlock(' . $this->postgresLockKey($name) . ')');
    }

    /**
     * A positive bigint: big-endian with the sign bit cleared, so every platform
     * derives the same key.
     */
    private function postgresLockKey(string $name): int
    {
        $bytes = substr($this->namedLockHash($name), 0, 8);
        $bytes[0] = chr(ord($bytes[0]) & 0x7f);

        return unpack('J', $bytes)[1];
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

        $this->setSessionTimeZone();

        return $res;
    }

    /**
     * The session zone is PHP's. PHP writes time as a string without an offset
     * (\diDateTime::sqlFormat()), which a timestamptz column reads in the session
     * zone, and hands it back with the session offset. Under the server's zone that
     * read is off by the difference between the two – silently, on every row. The
     * shipped sql/postgres dumps use timestamptz, so this is not optional.
     *
     * A zone Postgres refuses is a broken connection, not a warning: carrying on
     * would write wrong times instead.
     */
    protected function setSessionTimeZone()
    {
        $zone = date_default_timezone_get();

        try {
            $this->link->exec('SET TIME ZONE ' . $this->link->quote($zone));
        } catch (\PDOException $e) {
            $message = "Postgresql: unable to set session time zone '$zone': {$e->getMessage()}";

            $this->_log($message);

            throw new \diDatabaseException($message);
        }

        return $this;
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
WHERE table_name = '$table'
ORDER BY ordinal_position ASC");
        while ($r = $this->fetch_array($rs)) {
            $fields[$r['column_name']] = $r['data_type'];
        }

        return $fields;
    }

    public function getIndexNames(string $table): array
    {
        $names = [];
        $tableEsc = $this->escapeValue($table);

        // scope to the current schema (mirrors MySQL's DATABASE() filter) so a
        // same-named table in another search_path schema can't false-positive
        $rs = $this->q("SELECT indexname FROM pg_indexes
WHERE tablename = $tableEsc AND schemaname = current_schema()");
        while ($rs && ($r = $this->fetch_array($rs))) {
            $names[] = $r['indexname'];
        }

        return $names;
    }

    public function getForeignKeyNames(string $table): array
    {
        $names = [];
        $tableEsc = $this->escapeValue($table);

        $rs = $this->q("SELECT constraint_name FROM information_schema.table_constraints
WHERE table_name = $tableEsc AND table_schema = current_schema()
    AND constraint_type = 'FOREIGN KEY'");
        while ($rs && ($r = $this->fetch_array($rs))) {
            $names[] = $r['constraint_name'];
        }

        return $names;
    }

    public function getDumpCliCommand($options = [])
    {
        $options = $this->prepareDumpCliCommandOptions($options);
        $tables = $options['tables']
            ? join(' ', array_map(fn($t) => "--table $t", $options['tables']))
            : '';

        return static::$dumpCommand .
            " --host {$this->getHost()} --port {$this->getPort()} --no-owner -d {$this->getDatabase()} -U {$this->getUsername()} $tables{$options['commandSuffixWithFilename']}";
    }

    public static function insertUpdateQueryBeginning($keyField = null)
    {
        $keyField = $keyField ?: 'id';

        return "ON CONFLICT ($keyField) DO UPDATE SET";
    }

    protected function insertIgnoreQuery($table, $fieldsValues)
    {
        $t = $this->get_table_name($table);
        $q1 = '(' . $this->fieldsToStringForInsert($fieldsValues) . ')';
        $q2 = '(' . $this->valuesToStringForInsert($fieldsValues) . ')';

        return "INSERT INTO $t$q1 VALUES$q2 ON CONFLICT DO NOTHING";
    }

    public function getUpdateSingleLimit()
    {
        return '';
    }

    public function getDeleteSingleLimit()
    {
        return '';
    }

    protected function getJsonForStructure($value)
    {
        // Escaped here like in every driver: diModel doesn't pre-escape nested values
        return $this->escapeValue($this->encodeJsonStructure($value)) . '::jsonb';
    }
}
