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
    private $programsPreflighted = false;

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
        // diMYSQLi extends diMYSQL, so the one check covers both drivers.
        return $db instanceof \diMYSQL;
    }

    /**
     * The engine pre-check here assumes moveMyisamTablesToInnoDb() has already
     * run: it only refuses a MyISAM table kept on MyISAM by its FULLTEXT index.
     * A plain MyISAM table with a wide index, in a chain that skipped the move,
     * still fails mid-run from exec().
     *
     * @param array|null $tables Restrict to these; null = every base table.
     * @return self
     */
    public function convertTables(?array $tables = null)
    {
        // Scoped, and therefore safe to run here rather than only from
        // preflight() — which the bundled migration deliberately does not call,
        // since its stored-program checks are schema-wide. Without this the
        // MyISAM/FULLTEXT case would only surface mid-run, from exec().
        $this->assertColumnsConvertible($tables);
        $this->assertConvertibleEngines($tables);

        foreach ($this->tablesToConvert($tables) as $table) {
            $this->convertTable($table);
        }

        return $this;
    }

    /**
     * Everything that can make the run impossible, in one go — call it FIRST.
     *
     * The entry points each check only what they need: convertTables() looks at
     * columns, moveMyisamTablesToInnoDb() at nothing, and the DEFINER and
     * stored-program-charset checks happen inside rebuildStoredPrograms(). Since
     * that one is normally chained LAST, a schema whose triggers belong to
     * another account would otherwise only find out once every table and the
     * database default had already been converted — the half-converted, un-
     * rollbackable state the checks exist to prevent.
     *
     * $tables scopes the COLUMN check only; the stored-program checks are always
     * schema-wide, since a rebuild is too.
     *
     * @return self
     */
    public function preflight(?array $tables = null)
    {
        $this->assertColumnsConvertible($tables);
        $this->assertConvertibleEngines($tables);
        $this->assertStoredProgramsPreflight();

        return $this;
    }

    /**
     * A MyISAM table with a FULLTEXT index is deliberately left on MyISAM, whose
     * key cap is 1000 bytes — so if it also carries an index over a varchar wide
     * enough to exceed that once widened, its ALTER cannot succeed. Name it here
     * rather than fail halfway through the run.
     */
    private function assertConvertibleEngines($tables = null): void
    {
        // Bytes per character of the TARGET charset, from the server rather
        // than assumed. Only string columns are summed, so a composite key's
        // integer parts are not counted — the estimate is deliberately low, it
        // only has to catch the case that certainly does not fit.
        $bytes =
            (int) $this->scalar(
                "SELECT MAXLEN AS v FROM information_schema.CHARACTER_SETS
             WHERE CHARACTER_SET_NAME = '{$this->quoted($this->charset)}'"
            ) ?:
            4;
        $bad = $this->column(
            "SELECT DISTINCT CONCAT(s.TABLE_NAME, '.', s.INDEX_NAME) AS name
             FROM information_schema.STATISTICS s
             JOIN information_schema.TABLES t
               ON t.TABLE_SCHEMA = s.TABLE_SCHEMA AND t.TABLE_NAME = s.TABLE_NAME
             JOIN information_schema.COLUMNS c
               ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME
              AND c.COLUMN_NAME = s.COLUMN_NAME
             WHERE s.TABLE_SCHEMA = DATABASE()
               AND t.ENGINE = 'MyISAM'
               AND s.INDEX_TYPE <> 'FULLTEXT'
               AND c.CHARACTER_SET_NAME IS NOT NULL
               AND EXISTS (
                 SELECT 1 FROM information_schema.STATISTICS f
                 WHERE f.TABLE_SCHEMA = s.TABLE_SCHEMA
                   AND f.TABLE_NAME = s.TABLE_NAME
                   AND f.INDEX_TYPE = 'FULLTEXT'
               )
             GROUP BY s.TABLE_NAME, s.INDEX_NAME
             HAVING SUM(
               COALESCE(s.SUB_PART, c.CHARACTER_MAXIMUM_LENGTH) * $bytes
             ) > 1000" . $this->tableFilter('s.TABLE_NAME', $tables)
        );

        if ($bad) {
            throw new \Exception(
                'MyISAM FULLTEXT tables whose key would exceed the 1000-byte ' .
                    'cap once widened: ' .
                    implode(', ', $bad) .
                    ' — convert them by hand'
            );
        }
    }

    /** Only for the paths that actually rewrite stored programs. */
    private function assertStoredProgramsPreflight($names = null): void
    {
        // Memoised only for the unscoped call: a scoped one checked a different
        // set, and treating it as "already done" would let the next scope reach
        // DROP with its DEFINER, sql_mode and charsets unverified.
        if ($names === null && $this->programsPreflighted) {
            return;
        }

        $this->assertStoredProgramsRecreatable($names);
        $this->assertStoredProgramCharsetsKnown($names);
        $this->assertSqlModesSettable($names);

        // Only once they all passed: a caller that catches the exception and
        // retries must not short-circuit into DROP with nothing verified.
        if ($names === null) {
            $this->programsPreflighted = true;
        }
    }

    /**
     * Columns the whitelist rebuild in modifyClause() cannot reproduce —
     * generated columns, expression defaults, INVISIBLE — all of which show up
     * as a non-empty EXTRA.
     */
    private function assertColumnsConvertible($tables = null): void
    {
        $bad = $this->column(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME, ' (', EXTRA, ')') AS name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLLATION_NAME IS NOT NULL
               AND EXTRA <> ''
               AND " .
                $this->wrongCollationCondition('COLLATION_NAME') .
                $this->tableFilter('TABLE_NAME', $tables)
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
    private function assertStoredProgramCharsetsKnown($names = null): void
    {
        // Two patterns, not one alternation: the introducer form must require a
        // following quote exactly as retarget() does, or every underscore-prefixed
        // local variable (`DECLARE _count INT`) reads as a charset. `USING` is
        // matched too, so BTREE/HASH are whitelisted below rather than reported
        // as a baffling "unknown charset BTREE".
        $patterns = [
            '/\b(?:CHARACTER SET|CHARSET|USING)\s+([A-Za-z0-9_]+)/i',
            '/(?<![A-Za-z0-9_])_([A-Za-z0-9_]+)(?=[\x27"])/i',
            // a collation names its charset in its prefix
            '/\bCOLLATE\s+([A-Za-z0-9]+?)_[A-Za-z0-9_]+\b/i',
        ];
        $known = array_merge(self::MB3_NAMES, [
            strtolower($this->charset),
            'binary',
            'btree',
            'hash',
        ]);
        $offenders = [];

        foreach ($this->storedProgramDefinitions($names) as $label => $ddl) {
            // Literals and comments are not code: a routine building dynamic SQL
            // as a string must not be refused for what that string says.
            $code = $this->codeOnly($ddl);
            $found = [];
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $code, $m);
                $found = array_merge($found, $m[1]);
            }

            foreach (array_unique($found) as $name) {
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

    /**
     * Every sql_mode a stored program carries must be settable BEFORE the first
     * DROP: withSqlMode() sets it right before recreating, and a mode the server
     * rejects (a 5.7-era NO_AUTO_CREATE_USER, say) would otherwise be discovered
     * with the program already gone.
     */
    private function assertSqlModesSettable($names = null): void
    {
        $modes = array_unique(
            array_merge(
                array_column($this->routines($names), 'sql_mode'),
                array_column($this->triggers($names), 'sql_mode')
            )
        );

        $previous = $this->sessionVar('sql_mode');

        try {
            foreach (array_filter($modes) as $mode) {
                $this->exec(
                    "SET SESSION sql_mode = '" . $this->quoted($mode) . "'",
                    'sql_mode ' . $mode
                );
            }
        } finally {
            if ($previous !== null) {
                $this->quiet(
                    "SET SESSION sql_mode = '" . $this->quoted($previous) . "'"
                );
            }
        }
    }

    /** label => DDL, for every routine, view and trigger in the schema. */
    private function storedProgramDefinitions($names = null): array
    {
        $out = [];

        foreach ($this->routines($names) as $routine) {
            $word = $routine['type'] === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
            $out[strtolower($word) . ' ' . $routine['name']] = $this->showCreate(
                "$word `{$routine['name']}`",
                'Create ' . ucfirst(strtolower($word))
            );
        }

        foreach ($this->viewNames($names) as $name) {
            $out["view $name"] = $this->showCreate("VIEW `$name`", 'Create View');
        }

        foreach ($this->triggerNames($names) as $name) {
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
    public function moveMyisamTablesToInnoDb(?array $tables = null)
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
     * Call AFTER setDatabaseCharset(): a routine parameter declared with no
     * explicit charset inherits the DATABASE default at creation time, and
     * withSessionCharset() moves the session variables, not the schema default.
     * Rebuilding first puts those parameters straight back on the old charset.
     *
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
    public function rebuildStoredPrograms(?array $names = null)
    {
        $this->assertStoredProgramsPreflight($names);

        // MySQL stamps a stored program with the SESSION charset context it was
        // created under. Rebuilding on the old connection charset — which is
        // exactly what an existing project has until it flips its config — would
        // put every program straight back on mb3, converted tables or not.
        return $this->withSessionCharset(function () use ($names) {
            $this->rebuildStoredProgramsInner($names);

            return $this;
        });
    }

    /**
     * SET NAMES for the duration, restoring the four session variables after.
     * `SET NAMES` moves character_set_results too, so all four are captured.
     */
    private function withSessionCharset(callable $work)
    {
        $vars = [
            'character_set_client',
            'character_set_connection',
            'character_set_results',
            'collation_connection',
        ];

        $previous = [];
        foreach ($vars as $var) {
            $previous[$var] = $this->sessionVar($var);
        }

        // SET NAMES only: no matching set_charset(), so for the duration of the
        // block the client library still escapes in the connection's original
        // charset. Safe here because everything escaped inside is ASCII
        // (identifiers, collation and sql_mode names) — do not escape real data
        // in this block without adding set_charset() to both sides.
        $this->exec(
            "SET NAMES {$this->charset} COLLATE {$this->collation}",
            'session charset'
        );

        try {
            return $work();
        } finally {
            $parts = [];
            foreach ($previous as $var => $value) {
                if ($value !== null) {
                    $parts[] = "$var = '" . $this->quoted($value) . "'";
                }
            }
            if ($parts) {
                $this->quiet('SET SESSION ' . implode(', ', $parts));
            }
        }
    }

    private function rebuildStoredProgramsInner($names): void
    {
        foreach ($this->routines($names) as $routine) {
            $this->rebuildRoutine(
                $routine['name'],
                $routine['type'],
                $routine['sql_mode']
            );
        }

        foreach ($this->viewNames($names) as $name) {
            $this->rebuildView($name);
        }

        // Ordering is resolved against the FULL group, never the scoped subset:
        // a trigger recreated without a position clause is appended last, so
        // rebuilding just one of a group would silently move it. Walk every
        // trigger in firing order, rebuild only those in scope, and give each
        // its real neighbour — FOLLOWS the one before it, or PRECEDES the one
        // after when it is first in its group.
        $groups = [];
        foreach ($this->triggers() as $trigger) {
            $groups[$trigger['group']][] = $trigger;
        }

        $scoped = $names === null ? null : array_flip($names);

        foreach ($groups as $group) {
            foreach ($group as $i => $trigger) {
                if ($scoped !== null && !isset($scoped[$trigger['name']])) {
                    continue;
                }

                if ($i > 0) {
                    $position = ['FOLLOWS', $group[$i - 1]['name']];
                } elseif (isset($group[$i + 1])) {
                    $position = ['PRECEDES', $group[$i + 1]['name']];
                } else {
                    $position = null;
                }

                $this->rebuildTrigger($trigger, $position);
            }
        }
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
                // quiet: a failed restore must not mark a successful conversion
                // as a failed migration.
                $this->applySqlMode(explode(',', $sqlMode), true);
            }
            $this->quiet(
                'SET FOREIGN_KEY_CHECKS = ' . ($fkChecks === '0' ? '0' : '1')
            );
        }

        return $this;
    }

    /** Either spelling of the mb3 charset. */
    public static function isMb3Name(string $charset): bool
    {
        return in_array(strtolower($charset), self::MB3_NAMES, true);
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
            return self::isMb3Name($name) ? 'utf8mb3' : strtolower($name);
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
        return self::isMb3Name($charset) ? self::MB3_NAMES : [$charset];
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

    private function scalar(string $sql)
    {
        $rs = $this->db->q($sql);
        $r = $rs ? $this->db->fetch($rs) : null;

        return $r ? $r->v : null;
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
            array_diff(explode(',', $sqlMode), ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE'])
        );

        if ($strict) {
            $flags[] = 'STRICT_ALL_TABLES';
        }

        return $flags;
    }

    private function applySqlMode(array $flags, bool $quiet = false): void
    {
        $sql =
            "SET SESSION sql_mode = '" .
            $this->db->escape_string(
                implode(',', array_unique(array_filter($flags)))
            ) .
            "'";

        $quiet ? $this->quiet($sql) : $this->db->q($sql);
    }

    /**
     * NB the TABLE default is normalised to the target collation even if it was
     * deliberately something else (a table defaulting to _bin becomes
     * _general_ci); per-COLUMN _bin is preserved, which is where case
     * sensitivity actually lives.
     *
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

    /**
     * A column is fine on the target collation, on its _bin sibling, or on any
     * case-SENSITIVE collation of the target charset (…_as_cs and friends).
     *
     * The last exemption matters for the same reason _bin does: flattening a
     * _cs column to _general_ci makes values differing only in case equal, which
     * collides them under a UNIQUE index. Accent- and locale-sensitive _ci
     * variants are NOT exempt — normalising those onto one collation is the
     * point of the exercise, and it is documented in the README.
     */
    private function wrongCollationCondition(string $field): string
    {
        $charset = $this->quoted($this->charset);

        return "($field NOT IN (" .
            "'{$this->quoted($this->collation)}', " .
            "'{$charset}_bin')" .
            " AND $field NOT LIKE '{$charset}\\_%\\_cs')";
    }

    /** One ALTER per table: each is a full rebuild, so batch the columns. */
    private function convertTable(string $table): void
    {
        $parts = [];
        $columns = $this->columnsToConvert($table);

        foreach ($columns as $column) {
            $parts[] = $this->modifyClause($column);
        }

        // Table default too, so columns added later inherit the new charset.
        $parts[] = "DEFAULT CHARACTER SET $this->charset COLLATE $this->collation";

        // A COMPACT/REDUNDANT InnoDB table caps an index at 767 bytes, which an
        // indexed varchar(255) blows past the moment it is widened to 4 bytes
        // per character — the ALTER would abort. DYNAMIC lifts that to 3072, and
        // the table is being rebuilt anyway. COMPRESSED is left alone: it is a
        // deliberate choice and has the same 3072 limit.
        // Only when columns are actually being widened. Without that guard a
        // table whose default alone is wrong — the consuming project has one:
        // cp1251 with no string columns at all — would turn a metadata-only
        // ALTER into a full ALGORITHM=COPY rebuild under an exclusive lock.
        if (
            $columns &&
            in_array($this->rowFormat($table), ['COMPACT', 'REDUNDANT'], true)
        ) {
            $parts[] = 'ROW_FORMAT=DYNAMIC';
        }

        $this->exec("ALTER TABLE `$table` " . implode(', ', $parts), $table);
    }

    private function rowFormat(string $table): string
    {
        $rs = $this->db->q(
            "SELECT ROW_FORMAT AS v, ENGINE AS e FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '{$this->quoted($table)}'"
        );
        $r = $rs ? $this->db->fetch($rs) : null;

        return $r && strcasecmp((string) $r->e, 'InnoDB') === 0
            ? strtoupper((string) $r->v)
            : '';
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
            $sql .= " DEFAULT '" . $this->quoted($column->COLUMN_DEFAULT) . "'";
        }

        if ($column->COLUMN_COMMENT !== '') {
            $sql .= " COMMENT '" . $this->quoted($column->COLUMN_COMMENT) . "'";
        }

        return $sql;
    }

    /**
     * Keeps a collation whose SENSITIVITY the target lacks: _bin, and a _cs
     * collation already on the target charset. Everything else becomes the
     * target collation.
     *
     * A _cs collation on the OLD charset has no mechanical equivalent here
     * (utf8mb4 spells it _0900_as_cs, utf8mb3 does not spell it at all), so it
     * is normalised — and that is a semantic change worth knowing about.
     */
    private function targetCollation($current): string
    {
        $current = (string) $current;

        if (substr($current, -4) === '_bin') {
            return $this->charset . '_bin';
        }

        if (
            substr($current, -3) === '_cs' &&
            strpos($current, $this->charset . '_') === 0
        ) {
            return $current;
        }

        return $this->collation;
    }

    private function routines($names = null): array
    {
        $rs = $this->db->q(
            "SELECT ROUTINE_NAME AS name, ROUTINE_TYPE AS type,
                    SQL_MODE AS sql_mode
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE()
               AND ROUTINE_TYPE IN ('PROCEDURE', 'FUNCTION')" .
                $this->tableFilter('ROUTINE_NAME', $names) .
                ' ORDER BY ROUTINE_NAME'
        );

        $out = [];
        while ($r = $this->db->fetch($rs)) {
            $out[] = [
                'name' => $r->name,
                'type' => strtoupper($r->type),
                'sql_mode' => (string) $r->sql_mode,
            ];
        }

        return $out;
    }

    private function viewNames($names = null): array
    {
        return $this->column(
            "SELECT TABLE_NAME AS name FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE()" .
                $this->tableFilter('TABLE_NAME', $names) .
                ' ORDER BY TABLE_NAME'
        );
    }

    /**
     * Ordered the way MySQL fires them, not alphabetically: several triggers may
     * share a (table, timing, event) since 5.7, and their order is ACTION_ORDER.
     * Recreating them in another order silently changes behaviour, and
     * SHOW CREATE TRIGGER does not carry the FOLLOWS clause that would restore
     * it — so it is rebuilt from ACTION_ORDER below.
     */
    private function triggers($names = null): array
    {
        $rs = $this->db->q(
            "SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS tbl,
                    ACTION_TIMING AS timing, EVENT_MANIPULATION AS event,
                    ACTION_ORDER AS ord, SQL_MODE AS sql_mode
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE()" .
                $this->tableFilter('TRIGGER_NAME', $names) .
                " ORDER BY EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION,
                      ACTION_ORDER"
        );

        $out = [];
        while ($rs && ($r = $this->db->fetch($rs))) {
            $out[] = [
                'name' => $r->name,
                'group' => "{$r->tbl}/{$r->timing}/{$r->event}",
                'order' => (int) $r->ord,
                'sql_mode' => (string) $r->sql_mode,
            ];
        }

        return $out;
    }

    private function triggerNames($names = null): array
    {
        return array_column($this->triggers($names), 'name');
    }

    /**
     * Every stored program is recreated with its original DEFINER, which the
     * server only allows for that account itself or for one holding SET_USER_ID
     * / SUPER. Checked before the first DROP, because there is no rolling a
     * dropped trigger back.
     */
    private function assertStoredProgramsRecreatable($names = null): void
    {
        $rs = $this->db->q('SELECT CURRENT_USER() AS u');
        $r = $rs ? $this->db->fetch($rs) : null;
        $current = $r ? (string) $r->u : '';

        $foreign = $this->column(
            "SELECT DISTINCT DEFINER AS name FROM (
                SELECT DEFINER FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE()" .
                $this->tableFilter('ROUTINE_NAME', $names) .
                "
                UNION ALL
                SELECT DEFINER FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE()" .
                $this->tableFilter('TRIGGER_NAME', $names) .
                "
                UNION ALL
                SELECT DEFINER FROM information_schema.VIEWS
                 WHERE TABLE_SCHEMA = DATABASE()" .
                $this->tableFilter('TABLE_NAME', $names) .
                "
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
     * Does one GRANT line confer the right to recreate a program under someone
     * else's DEFINER? Pure, so it is unit-testable.
     *
     * `ALL PRIVILEGES` only counts when granted globally: SUPER and SET_USER_ID
     * have no database-level form, so the everyday `GRANT ALL ON mydb.* TO app`
     * does NOT confer them. Reading that as a yes would pass the pre-flight and
     * then fail after the first DROP TRIGGER — the unrecoverable state the
     * pre-flight exists to prevent.
     */
    public static function grantAllowsForeignDefiner(string $grant): bool
    {
        return (bool) (preg_match('/\b(SUPER|SET_USER_ID)\b/i', $grant) ||
            preg_match('/\bALL PRIVILEGES\b.*\bON\s+\*\.\*/i', $grant));
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
                if (self::grantAllowsForeignDefiner((string) $grant)) {
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
    private function rebuildRoutine(
        string $name,
        string $type,
        string $sqlMode
    ): void {
        $word = $type === 'FUNCTION' ? 'FUNCTION' : 'PROCEDURE';
        $ddl = $this->retarget(
            $this->showCreate(
                "$word `$name`",
                'Create ' . ucfirst(strtolower($word))
            )
        );
        $probe = substr('zz_probe_' . md5($name), 0, 60);

        $this->withSqlMode($sqlMode, function () use ($ddl, $word, $name, $probe) {
            try {
                $this->exec(
                    $this->renameRoutineInDdl($ddl, $word, $name, $probe),
                    "$word $name (probe)"
                );
            } finally {
                // Even on failure: a probe left behind is harmless but is litter.
                $this->quiet("DROP $word IF EXISTS `$probe`");
            }

            $this->exec("DROP $word IF EXISTS `$name`", "$word $name");
            $this->exec($ddl, "$word $name");
        });
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

    /**
     * $position is [FOLLOWS|PRECEDES, other trigger] — MySQL keeps the order in
     * ACTION_ORDER but SHOW CREATE TRIGGER omits the clause, so without it the
     * recreated trigger is appended last in its group.
     */
    private function rebuildTrigger(array $trigger, $position): void
    {
        $name = $trigger['name'];
        $ddl = $this->retarget(
            $this->showCreate("TRIGGER `$name`", 'SQL Original Statement')
        );

        if ($position !== null) {
            // Syntax puts the ordering clause right after FOR EACH ROW and
            // before the body — appending it at the end is a parse error.
            $ddl = preg_replace(
                '/\bFOR\s+EACH\s+ROW\b/i',
                'FOR EACH ROW ' . $position[0] . ' `' . $position[1] . '`',
                $ddl,
                1
            );
        }

        // The DROP goes INSIDE withSqlMode: setting the mode is the step that
        // can fail, and failing after the drop loses the trigger for good. No
        // probe pass here — a second trigger for the same event would fire on
        // any write in between; the DEFINER and sql_mode pre-flights guard it.
        $this->withSqlMode($trigger['sql_mode'], function () use ($ddl, $name) {
            $this->exec("DROP TRIGGER IF EXISTS `$name`", "trigger $name");
            $this->exec($ddl, "trigger $name");
        });
    }

    /**
     * A stored program remembers the sql_mode it was created under, and CREATE
     * stamps the SESSION mode instead — which inPreparedSession() has
     * deliberately altered. Without this every routine and trigger would come
     * back with a different mode, and one written under ANSI_QUOTES would not
     * even parse.
     */
    private function withSqlMode(string $mode, callable $work): void
    {
        $previous = $this->sessionVar('sql_mode');
        // exec(), not q(): a mode the server rejects (a routine carrying the
        // 5.7-era NO_AUTO_CREATE_USER, say) would otherwise fail silently and
        // the program be recreated under the relaxed session mode — the very
        // drift this method exists to prevent.
        $this->exec(
            "SET SESSION sql_mode = '" . $this->quoted($mode) . "'",
            'sql_mode ' . $mode
        );

        try {
            $work();
        } finally {
            if ($previous !== null) {
                $this->quiet(
                    "SET SESSION sql_mode = '" . $this->quoted($previous) . "'"
                );
            }
        }
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

        // isset() is already false for a NULL property, which is exactly how
        // MySQL answers when the account may not see the definition.
        if (!$r || !isset($r->$column)) {
            // A row with a NULL definition means the account may not see it; no
            // row at all means the object or its base table is gone.
            throw new \Exception(
                'Cannot read the definition of ' .
                    $what .
                    ($r
                        ? ' — not visible to this account'
                        : ' — SHOW CREATE failed, which for a VIEW usually means' .
                            ' it references a table that no longer exists; drop' .
                            ' the stale view or restore what it selects from') .
                    ($this->db->getLogStr() ? ': ' . $this->db->getLogStr() : '')
            );
        }

        return (string) $r->$column;
    }

    /**
     * Splits a definition into code and non-code (string literals, quoted
     * identifiers, comments) and applies $fn to the code parts only.
     *
     * Without this a plain regex also rewrites — or, in the pre-flight, rejects
     * — a `CHARACTER SET utf8` that merely appears inside dynamic SQL being
     * built as a string, or in a comment. That silently changes what the routine
     * does.
     */
    private function overCode(string $ddl, callable $fn): string
    {
        $parts = $this->tokenise($ddl);

        // preg_split alternates code, delimiter, code, … — even indexes are code.
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = $fn($part);
            }
        }

        return implode('', $parts);
    }

    /** [code, literal, code, …] — odd indexes are literals/comments. */
    private function tokenise(string $ddl): array
    {
        // NB the doubled backslashes: inside a single-quoted PHP string '\\'
        // is one backslash, and the regex needs a real escaped one.
        $parts = preg_split(
            '~(' .
                '\x27(?:[^\x27\\\\]|\\\\.|\x27\x27)*\x27' . // '…'
                '|"(?:[^"\\\\]|\\\\.|"")*"' . // "…"
                '|`(?:[^`]|``)*`' . // `…`
                '|/\*.*?\*/' . // /* … */
                '|--[ \t][^\n]*' . // -- … (MySQL needs the space)
                '|#[^\n]*' .
                ')~s',
            $ddl,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        if ($parts === false) {
            throw new \Exception('Cannot tokenise a stored program definition');
        }

        return $parts;
    }

    /**
     * Everything OUTSIDE literals and comments, for scanning.
     *
     * A code segment that is followed by a string literal keeps a sentinel quote
     * so the introducer form (`_cp1251'x'`) still matches its lookahead — the
     * real quote lives in the next segment. Without it a foreign introducer slips
     * through the pre-flight while retarget() happily rewrites the mb3 one.
     */
    private function codeOnly(string $ddl): string
    {
        $parts = $this->tokenise($ddl);
        $code = '';

        foreach ($parts as $i => $part) {
            if ($i % 2 !== 0) {
                continue;
            }

            $next = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
            $sentinel =
                $next !== '' && ($next[0] === "'" || $next[0] === '"')
                    ? $next[0]
                    : '';

            $code .= $part . $sentinel . "\n";
        }

        return $code;
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
        // The TARGET collation, not the old family name carried over: with a
        // target of utf8mb4_unicode_ci, keeping `_general_ci` would leave the
        // program disagreeing with the columns just converted. _bin is the one
        // family that must survive, since it is case sensitivity, not a locale.
        $collation = $this->collation;
        $charset = $this->charset;

        return $this->overCode($ddl, function ($code) use (
            $old,
            $charset,
            $collation
        ) {
            return preg_replace(
                [
                    '/\b(CHARACTER SET|CHARSET|USING)\s+' . $old . '\b/i',
                    '/\bCOLLATE\s+' . $old . '_bin\b/i',
                    '/\bCOLLATE\s+' . $old . '_\w+\b/i',
                    // Introducer form `_utf8'…'`. The quote sits in the NEXT
                    // segment once literals are split out, so end-of-segment
                    // counts as the lookahead too; the lookbehind still keeps
                    // an identifier such as `col_utf8` out of it.
                    '/(?<![A-Za-z0-9_])_' . $old . '(?=[\x27"]|$)/i',
                    // Already on the target charset but a foreign collation —
                    // utf8mb4_0900_ai_ci is what MySQL 8 hands out by default,
                    // so a program can carry it while the columns were just
                    // normalised. Same "by collation, not charset" rule the
                    // tables follow; _bin is exempt, it is case sensitivity.
                    '/\bCOLLATE\s+' . preg_quote($charset, '/') . '_(?!bin\b)\w+/i',
                ],
                [
                    '$1 ' . $charset,
                    'COLLATE ' . $charset . '_bin',
                    'COLLATE ' . $collation,
                    '_' . $charset,
                    'COLLATE ' . $collation,
                ],
                $code
            );
        });
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
     * For recovery statements in finally blocks: q() does not throw, it logs —
     * and Migration::run() reads that log after up() returns, so a stray entry
     * from a restore would mark a wholly successful conversion as a failure.
     */
    private function quiet(string $sql): void
    {
        $before = count($this->db->getLog());
        $this->db->q($sql);
        $this->db->truncateLog($before);
    }

    /**
     * diDB::q() logs failures instead of throwing, and a migration only inspects
     * that log once up() has returned — so each statement is checked on the spot,
     * else a failing table is stepped over and the schema left half converted.
     *
     * Only the entries THIS statement added are inspected — the log is left as
     * it was rather than reset, because a consumer migration that failed before
     * calling the converter must not have that erased.
     */
    private function exec(string $sql, string $context): void
    {
        $before = count($this->db->getLog());
        $this->db->q($sql);
        $log = $this->db->getLog();

        if (count($log) > $before) {
            throw new \Exception(
                "Charset conversion failed on [$context]: " .
                    implode('; ', array_slice($log, $before))
            );
        }
    }
}
