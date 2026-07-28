<?php

namespace diCore\Database\Tool;

/**
 * Converts a MySQL schema to another character set and collation.
 *
 * Per-column MODIFY rather than `ALTER TABLE … CONVERT TO CHARACTER SET`, which
 * would widen types to preserve capacity (text -> mediumtext -> longtext) and
 * force one collation on every column, flattening any _bin column to
 * case-insensitive — enough to collide values differing only in case under a
 * UNIQUE index.
 *
 * PHP 7.4 compatible on purpose: the package supports it, so no promotion,
 * no union types, no match().
 */
class CharsetConverter
{
    /**
     * information_schema reports the mb3 charset as 'utf8' before MySQL 8.0.28
     * and 'utf8mb3' from 8.0.28 on. Matching one spelling only would convert
     * nothing and report success.
     */
    const MB3_NAMES = ['utf8mb3', 'utf8'];

    /** @var \diDB */
    private $db;

    /** @var string */
    private $charset;

    /** @var string */
    private $collation;

    /** @var bool */
    private $preflighted = false;

    public function __construct(\diDB $db, string $charset, string $collation)
    {
        $this->db = $db;
        $this->charset = $charset;
        $this->collation = $collation;

        $this->resolveTarget();
    }

    /** MySQL-only: everything below speaks information_schema and MySQL DDL. */
    public static function supports(\diDB $db): bool
    {
        return $db instanceof \diMYSQLi || $db instanceof \diMYSQL;
    }

    /**
     * @param array|null $tables Restrict to these; null = every base table.
     * @return self
     */
    public function convertTables($tables = null)
    {
        $this->preflight();

        foreach ($this->tablesToConvert($tables) as $table) {
            $this->convertTable($table);
        }

        return $this;
    }

    /**
     * Everything that can make the run impossible, checked before the first
     * ALTER. DDL does not roll back, so finding any of this halfway through
     * leaves a half-converted schema — and a dropped trigger cannot be restored
     * at all. Runs once; both entry points call it.
     *
     * @return self
     */
    public function preflight()
    {
        if ($this->preflighted) {
            return $this;
        }
        $this->preflighted = true;

        $this->assertColumnsConvertible();
        $this->assertStoredProgramsRecreatable();
        $this->assertStoredProgramCharsetsKnown();

        return $this;
    }

    /**
     * Columns the whitelist rebuild in modifyClause() cannot reproduce —
     * generated columns, expression defaults, INVISIBLE — all of which show up
     * as a non-empty EXTRA.
     */
    private function assertColumnsConvertible(): void
    {
        $bad = $this->column(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME, ' (', EXTRA, ')') AS name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLLATION_NAME IS NOT NULL
               AND EXTRA <> ''
               AND " .
                $this->wrongCollationCondition('COLLATION_NAME')
        );

        if ($bad) {
            throw new \Exception(
                'Cannot convert columns carrying EXTRA: ' .
                    implode(', ', $bad) .
                    ' — convert them by hand'
            );
        }
    }

    /**
     * retarget() only knows how to rewrite the mb3 spellings, so a stored
     * program declaring some other non-target charset (cp1251, latin1) would be
     * left mangling text after its tables were converted. Refuse up front rather
     * than rewrite a charset that may well be deliberate.
     */
    private function assertStoredProgramCharsetsKnown(): void
    {
        $pattern =
            '/\b(?:CHARACTER SET|CHARSET|USING)\s+([A-Za-z0-9_]+)/i';
        $known = array_merge(self::MB3_NAMES, [
            strtolower($this->charset),
            'binary',
        ]);
        $offenders = [];

        foreach ($this->storedProgramDefinitions() as $label => $ddl) {
            preg_match_all($pattern, $ddl, $m);
            foreach (array_unique($m[1]) as $name) {
                if (!in_array(strtolower($name), $known, true)) {
                    $offenders[] = "$label ($name)";
                }
            }
        }

        if ($offenders) {
            throw new \Exception(
                'Stored programs declare a charset this converter will not ' .
                    'rewrite: ' .
                    implode(', ', array_unique($offenders)) .
                    ' — convert them by hand'
            );
        }
    }

    /** label => DDL, for every routine, view and trigger in the schema. */
    private function storedProgramDefinitions(): array
    {
        $out = [];

        foreach ($this->routines() as $routine) {
            $word = $routine['type'] === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
            $out[strtolower($word) . ' ' . $routine['name']] = $this->showCreate(
                "$word `{$routine['name']}`",
                'Create ' . ucfirst(strtolower($word))
            );
        }

        foreach ($this->viewNames() as $name) {
            $out["view $name"] = $this->showCreate("VIEW `$name`", 'Create View');
        }

        foreach ($this->triggerNames() as $name) {
            $out["trigger $name"] = $this->showCreate(
                "TRIGGER `$name`",
                'SQL Original Statement'
            );
        }

        return $out;
    }

    /**
     * MyISAM caps a key at 1000 bytes where InnoDB allows 3072, so an indexed
     * varchar(255) (1020 bytes in mb4) cannot be converted in place. FULLTEXT
     * tables are left alone — InnoDB tokenizes differently, and their keys are
     * not subject to the cap anyway.
     *
     * Call BEFORE converting: at the old charset the oversized index still fits.
     *
     * @param array|null $tables
     * @return self
     */
    public function moveMyisamTablesToInnoDb($tables = null)
    {
        $sql =
            "SELECT t.TABLE_NAME AS name
             FROM information_schema.TABLES t
             WHERE t.TABLE_SCHEMA = DATABASE()
               AND t.ENGINE = 'MyISAM'
               AND NOT EXISTS (
                 SELECT 1 FROM information_schema.STATISTICS s
                 WHERE s.TABLE_SCHEMA = t.TABLE_SCHEMA
                   AND s.TABLE_NAME = t.TABLE_NAME
                   AND s.INDEX_TYPE = 'FULLTEXT'
               )" .
            $this->tableFilter('t.TABLE_NAME', $tables) .
            ' ORDER BY t.TABLE_NAME';

        foreach ($this->column($sql) as $table) {
            $this->exec("ALTER TABLE `$table` ENGINE = InnoDB", $table);
        }

        return $this;
    }

    /**
     * Without this the schema default is left behind and any later CREATE TABLE
     * with no explicit charset silently inherits the old one — or, on MySQL 8,
     * the server default utf8mb4_0900_ai_ci, which then collides with the
     * columns on a join.
     *
     * @return self
     */
    public function setDatabaseCharset()
    {
        $this->exec(
            "ALTER DATABASE `{$this->db->getDatabase()}`" .
                " CHARACTER SET {$this->charset} COLLATE {$this->collation}",
            'database default'
        );

        return $this;
    }

    /**
     * Procedures, functions, views and triggers store the charset context they
     * were created under, and a routine's parameters keep the charset written
     * into their declaration — so a procedure called from a trigger goes on
     * mangling 4-byte characters long after every table was converted.
     *
     * Known limit: a view column produced by an explicit `CONVERT(x USING …)`
     * takes the new charset's DEFAULT collation, not the configured one, since
     * the expression names no collation. Rare, and it only matters if such a
     * column is then compared with a table column.
     *
     * @return self
     */
    public function rebuildStoredPrograms()
    {
        $this->preflight();

        foreach ($this->routines() as $routine) {
            $this->rebuildRoutine($routine['name'], $routine['type']);
        }

        foreach ($this->viewNames() as $name) {
            $this->rebuildView($name);
        }

        foreach ($this->triggerNames() as $name) {
            $this->rebuildTrigger($name);
        }

        return $this;
    }

    /**
     * Runs $work with foreign keys off (an FK needs both sides on the same
     * charset, impossible mid-conversion) and the zero-date flags dropped: ALTER
     * re-validates EVERY column, so a legacy '0000-00-00' default aborts the
     * rebuild of a table that has nothing else wrong with it.
     *
     * $strict makes a value that no longer fits an error instead of a silent
     * truncation. Wanted when NARROWING (mb4 -> mb3), where the alternative is
     * sweeping every 4-byte character out of the database and reporting success;
     * not when widening, where nothing can be lost and strictness could only
     * reject legacy data.
     *
     * @return self
     */
    public function inPreparedSession(bool $strict, callable $work)
    {
        $fkChecks = $this->sessionVar('foreign_key_checks');
        $sqlMode = $this->sessionVar('sql_mode');

        $this->db->q('SET FOREIGN_KEY_CHECKS = 0');
        if ($sqlMode !== null) {
            $this->applySqlMode($this->relaxedFlags($sqlMode, $strict));
        }

        try {
            $work($this);
        } finally {
            // Restore what was there, not an assumed default — this process may
            // go on to run further migrations that rely on the original mode.
            if ($sqlMode !== null) {
                $this->applySqlMode(explode(',', $sqlMode));
            }
            $this->db->q(
                'SET FOREIGN_KEY_CHECKS = ' . ($fkChecks === '0' ? '0' : '1')
            );
        }

        return $this;
    }

    /** Whichever of the two mb3 spellings this server knows, or null. */
    public static function mb3NameFor(\diDB $db)
    {
        foreach (self::MB3_NAMES as $name) {
            $rs = $db->q(
                "SELECT 1 AS ok FROM information_schema.CHARACTER_SETS
                 WHERE CHARACTER_SET_NAME = '$name'"
            );
            if ($rs && $db->fetch($rs)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Both spellings of the same charset ('utf8' / 'utf8mb3') compare equal, so
     * a caller can hold either without a false mismatch. Used by the connection
     * charset test.
     */
    public static function sameCharset(string $a, string $b): bool
    {
        $normalise = function ($name) {
            return in_array(strtolower($name), self::MB3_NAMES, true)
                ? 'utf8mb3'
                : strtolower($name);
        };

        return $normalise($a) === $normalise($b);
    }

    /**
     * Rewrites the requested target to the spelling THIS server uses, then
     * verifies it exists.
     *
     * Load-bearing for mb3: a project configured as `utf8` / `utf8_general_ci`
     * finds neither in information_schema from MySQL 8.0.28 on, where they are
     * `utf8mb3` / `utf8mb3_general_ci` — so an unresolved name both fails this
     * check and, worse, makes every comparison below treat correctly-converted
     * tables as wrong and rebuild the lot for nothing.
     *
     * A typo, on the other hand, must still fail here rather than as a syntax
     * error halfway through, with the schema already part converted.
     */
    private function resolveTarget(): void
    {
        foreach ($this->charsetSpellings($this->charset) as $charset) {
            $collation = $this->collationFor($this->collation, $charset);

            if ($this->collationExists($charset, $collation)) {
                $this->charset = $charset;
                $this->collation = $collation;

                return;
            }
        }

        throw new \Exception(
            "Unknown charset/collation {$this->charset}/{$this->collation}"
        );
    }

    /** Both names of the mb3 charset; anything else has only itself. */
    private function charsetSpellings(string $charset): array
    {
        return in_array(strtolower($charset), self::MB3_NAMES, true)
            ? self::MB3_NAMES
            : [$charset];
    }

    /** utf8_general_ci + utf8mb3 -> utf8mb3_general_ci */
    private function collationFor(string $collation, string $charset): string
    {
        foreach (self::MB3_NAMES as $name) {
            if (stripos($collation, $name . '_') === 0) {
                return $charset . '_' . substr($collation, strlen($name) + 1);
            }
        }

        return $collation;
    }

    private function collationExists(string $charset, string $collation): bool
    {
        $rs = $this->db->q(
            "SELECT 1 AS ok FROM information_schema.COLLATIONS
             WHERE COLLATION_NAME = '{$this->quoted($collation)}'
               AND CHARACTER_SET_NAME = '{$this->quoted($charset)}'"
        );

        return (bool) ($rs && $this->db->fetch($rs));
    }

    private function sessionVar(string $name)
    {
        $rs = $this->db->q("SELECT @@SESSION.$name AS v");
        $r = $rs ? $this->db->fetch($rs) : null;

        return $r ? (string) $r->v : null;
    }

    private function relaxedFlags(string $sqlMode, bool $strict): array
    {
        // array_filter drops the empty element an empty mode explodes into —
        // otherwise appending a flag yields a leading comma and invalid SQL.
        $flags = array_filter(
            array_diff(explode(',', $sqlMode), [
                'NO_ZERO_DATE',
                'NO_ZERO_IN_DATE',
            ])
        );

        if ($strict) {
            $flags[] = 'STRICT_ALL_TABLES';
        }

        return $flags;
    }

    private function applySqlMode(array $flags): void
    {
        $this->db->q(
            "SET SESSION sql_mode = '" .
                $this->db->escape_string(
                    implode(',', array_unique(array_filter($flags)))
                ) .
                "'"
        );
    }

    /**
     * Anything whose table default or any column is not already on the TARGET
     * COLLATION — deliberately not "is one of the old charsets", which misses a
     * table on some third one (cp1251, latin1), and deliberately not charset
     * alone, which misses the common case of a table already on utf8mb4 but with
     * MySQL 8's default utf8mb4_0900_ai_ci: it would keep colliding with the
     * converted tables on a join, which is the very thing being fixed.
     */
    private function tablesToConvert($only): array
    {
        return $this->column(
            "SELECT t.TABLE_NAME AS name
             FROM information_schema.TABLES t
             WHERE t.TABLE_SCHEMA = DATABASE()
               AND t.TABLE_TYPE = 'BASE TABLE'
               AND (
                 t.TABLE_COLLATION <> '{$this->quoted($this->collation)}'
                 OR EXISTS (
                   SELECT 1 FROM information_schema.COLUMNS c
                   WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA
                     AND c.TABLE_NAME = t.TABLE_NAME
                     AND c.COLLATION_NAME IS NOT NULL
                     AND " .
                $this->wrongCollationCondition('c.COLLATION_NAME') .
                "
                 )
               )" .
                $this->tableFilter('t.TABLE_NAME', $only) .
                ' ORDER BY t.TABLE_NAME'
        );
    }

    /** A column is fine only on the target collation, or its _bin sibling. */
    private function wrongCollationCondition(string $field): string
    {
        return "$field NOT IN (" .
            "'{$this->quoted($this->collation)}', " .
            "'{$this->quoted($this->charset . '_bin')}')";
    }

    /** One ALTER per table: each is a full rebuild, so batch the columns. */
    private function convertTable(string $table): void
    {
        $parts = [];

        foreach ($this->columnsToConvert($table) as $column) {
            $parts[] = $this->modifyClause($column);
        }

        // Table default too, so columns added later inherit the new charset.
        $parts[] =
            "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collation}";

        $this->exec("ALTER TABLE `$table` " . implode(', ', $parts), $table);
    }

    private function columnsToConvert(string $table): array
    {
        $rs = $this->db->q(
            "SELECT COLUMN_NAME, COLUMN_TYPE, COLLATION_NAME, IS_NULLABLE,
                    COLUMN_DEFAULT, COLUMN_COMMENT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$this->quoted($table)}'
               AND COLLATION_NAME IS NOT NULL
               AND " .
                $this->wrongCollationCondition('COLLATION_NAME') .
                ' ORDER BY ORDINAL_POSITION'
        );

        $columns = [];
        while ($r = $this->db->fetch($rs)) {
            $columns[] = $r;
        }

        return $columns;
    }

    /**
     * Rebuilds the column from a deliberate whitelist of attributes (type,
     * charset, nullability, default, comment). Anything outside it — INVISIBLE,
     * SRID, inline CHECK — would be dropped, hence the EXTRA guard.
     */
    private function modifyClause($column): string
    {
        // COLUMN_DEFAULT doesn't round-trip expression defaults; bail loudly
        // rather than silently rewrite one wrong.
        if ($column->EXTRA !== '') {
            throw new \Exception(
                "Column {$column->COLUMN_NAME} has EXTRA '{$column->EXTRA}';" .
                    ' convert it by hand'
            );
        }

        $sql =
            "MODIFY `{$column->COLUMN_NAME}` {$column->COLUMN_TYPE}" .
            " CHARACTER SET {$this->charset} COLLATE " .
            $this->targetCollation($column->COLLATION_NAME);

        $sql .= $column->IS_NULLABLE === 'NO' ? ' NOT NULL' : ' NULL';

        if ($column->COLUMN_DEFAULT !== null) {
            $sql .=
                " DEFAULT '" . $this->quoted($column->COLUMN_DEFAULT) . "'";
        }

        if ($column->COLUMN_COMMENT !== '') {
            $sql .=
                " COMMENT '" . $this->quoted($column->COLUMN_COMMENT) . "'";
        }

        return $sql;
    }

    /** Keep the collation family: …_bin stays binary, everything else as given. */
    private function targetCollation($current): string
    {
        return substr((string) $current, -4) === '_bin'
            ? $this->charset . '_bin'
            : $this->collation;
    }

    private function routines(): array
    {
        $rs = $this->db->q(
            "SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()
               AND ROUTINE_TYPE IN ('PROCEDURE', 'FUNCTION')
             ORDER BY ROUTINE_NAME"
        );

        $out = [];
        while ($r = $this->db->fetch($rs)) {
            $out[] = ['name' => $r->name, 'type' => strtoupper($r->type)];
        }

        return $out;
    }

    private function viewNames(): array
    {
        return $this->column(
            "SELECT TABLE_NAME AS name FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
        );
    }

    private function triggerNames(): array
    {
        return $this->column(
            "SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME"
        );
    }

    /**
     * Every stored program is recreated with its original DEFINER, which the
     * server only allows for that account itself or for one holding SET_USER_ID
     * / SUPER. Checked before the first DROP, because there is no rolling a
     * dropped trigger back.
     */
    private function assertStoredProgramsRecreatable(): void
    {
        $rs = $this->db->q('SELECT CURRENT_USER() AS u');
        $r = $rs ? $this->db->fetch($rs) : null;
        $current = $r ? (string) $r->u : '';

        $foreign = $this->column(
            "SELECT DISTINCT DEFINER AS name FROM (
                SELECT DEFINER FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE()
                UNION ALL
                SELECT DEFINER FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE()
                UNION ALL
                SELECT DEFINER FROM information_schema.VIEWS
                 WHERE TABLE_SCHEMA = DATABASE()
             ) d WHERE DEFINER <> '{$this->quoted($current)}'"
        );

        if (!$foreign || $this->canSetForeignDefiner()) {
            return;
        }

        throw new \Exception(
            'Cannot recreate stored programs owned by ' .
                implode(', ', $foreign) .
                " as $current: SET_USER_ID or SUPER required"
        );
    }

    /**
     * SHOW GRANTS first: it lists privileges reaching the account through an
     * activated role, which information_schema.USER_PRIVILEGES does not, so
     * checking only the latter refuses a run that would have worked.
     */
    private function canSetForeignDefiner(): bool
    {
        $rs = $this->db->q('SHOW GRANTS FOR CURRENT_USER()');

        while ($rs && ($r = $this->db->fetch($rs))) {
            foreach (get_object_vars($r) as $grant) {
                if (
                    preg_match(
                        '/\b(ALL PRIVILEGES|SUPER|SET_USER_ID)\b/i',
                        (string) $grant
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validated under a throwaway name first: a routine has to be dropped before
     * it can be recreated, and DDL does not roll back, so a definition that
     * fails to compile would otherwise leave the schema without it.
     */
    private function rebuildRoutine(string $name, string $type): void
    {
        $word = $type === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
        $ddl = $this->retarget(
            $this->showCreate(
                "$word `$name`",
                'Create ' . ucfirst(strtolower($word))
            )
        );
        $probe = substr('zz_probe_' . md5($name), 0, 60);

        $this->exec(
            $this->renameRoutineInDdl($ddl, $word, $name, $probe),
            "$word $name (probe)"
        );
        $this->exec("DROP $word IF EXISTS `$probe`", "$word $name (probe)");

        $this->exec("DROP $word IF EXISTS `$name`", "$word $name");
        $this->exec($ddl, "$word $name");
    }

    private function renameRoutineInDdl(
        string $ddl,
        string $word,
        string $from,
        string $to
    ): string {
        return preg_replace(
            '/(\b' . $word . '\s+)`?' . preg_quote($from, '/') . '`?/i',
            '$1`' . $to . '`',
            $ddl,
            1
        );
    }

    /** CREATE OR REPLACE — no window where the view is missing. */
    private function rebuildView(string $name): void
    {
        $ddl = $this->showCreate("VIEW `$name`", 'Create View');

        // retarget here too: a view body may carry its own CONVERT(… USING utf8)
        // or an explicit COLLATE.
        $this->exec(
            preg_replace(
                '/^CREATE /i',
                'CREATE OR REPLACE ',
                $this->retarget($ddl),
                1
            ),
            "view $name"
        );
    }

    private function rebuildTrigger(string $name): void
    {
        $ddl = $this->showCreate("TRIGGER `$name`", 'SQL Original Statement');

        // No probe pass: a second trigger for the same event would fire on any
        // write in between. The DEFINER pre-flight is what guards this path.
        $this->exec("DROP TRIGGER IF EXISTS `$name`", "trigger $name");
        $this->exec($this->retarget($ddl), "trigger $name");
    }

    /**
     * Never returns null: MySQL answers with a row whose DDL column is NULL when
     * the account may not see the definition, and silently skipping it would
     * leave that program on the old charset, still mangling text — the exact
     * failure this class exists to prevent.
     */
    private function showCreate(string $what, string $column): string
    {
        $rs = $this->db->q("SHOW CREATE $what");
        $r = $rs ? $this->db->fetch($rs) : null;

        if (!$r || !isset($r->$column) || $r->$column === null) {
            throw new \Exception(
                "Cannot read the definition of $what (insufficient privileges?)"
            );
        }

        return (string) $r->$column;
    }

    /**
     * Rewrites the mb3 charset/collation tokens in a stored program's
     * definition. `utf8mb4` is safe from the `utf8` alternative on its own — a
     * word boundary cannot fall between "utf8" and "mb4".
     *
     * Only mb3 is rewritten; any other non-target charset is refused up front by
     * assertStoredProgramCharsetsKnown(), since rewriting e.g. a deliberate
     * cp1251 or ascii declaration would be a guess.
     */
    private function retarget(string $ddl): string
    {
        $old = '(?:' . implode('|', self::MB3_NAMES) . ')';

        return preg_replace(
            [
                '/\b(CHARACTER SET|CHARSET|USING)\s+' . $old . '\b/i',
                '/\bCOLLATE\s+' . $old . '_(\w+)\b/i',
            ],
            ['$1 ' . $this->charset, 'COLLATE ' . $this->charset . '_$1'],
            $ddl
        );
    }

    private function tableFilter(string $field, $tables): string
    {
        if ($tables === null) {
            return '';
        }
        if (!$tables) {
            return ' AND 1 = 0';
        }

        $quoted = [];
        foreach ($tables as $t) {
            $quoted[] = "'" . $this->quoted($t) . "'";
        }

        return " AND $field IN (" . implode(', ', $quoted) . ')';
    }

    private function quoted($value): string
    {
        return $this->db->escape_string((string) $value);
    }

    private function column(string $sql): array
    {
        $rs = $this->db->q($sql);

        $out = [];
        while ($rs && ($r = $this->db->fetch($rs))) {
            $out[] = $r->name;
        }

        return $out;
    }

    /**
     * diDB::q() logs failures instead of throwing, and a migration only inspects
     * that log once up() has returned — so each statement is checked on the spot,
     * else a failing table is stepped over and the schema left half converted.
     *
     * The log is reset first, so an earlier failure isn't blamed on this one.
     * Side effect: whatever the surrounding migration had accumulated in
     * diDB::$log is discarded — acceptable, since q() only ever logs errors and
     * any of those would already have thrown here.
     */
    private function exec(string $sql, string $context): void
    {
        $this->db->resetLog();
        $this->db->q($sql);

        if ($this->db->getLog()) {
            throw new \Exception(
                "Charset conversion failed on [$context]: " .
                    $this->db->getLogStr()
            );
        }
    }
}
