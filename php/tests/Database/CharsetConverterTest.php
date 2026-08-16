<?php

namespace diCore\Tests\Database;

use diCore\Database\Connection;
use diCore\Database\Tool\CharsetConverter;
use PHPUnit\Framework\TestCase;

/**
 * Self-contained: builds a throwaway utf8mb3 table (plus a trigger) and converts
 * only that one, so it never touches the rest of the schema.
 */
class CharsetConverterTest extends TestCase
{
    const TABLE = 'di_charset_probe';
    const TRIGGER = 'di_charset_probe_trg';
    const TARGET = 'utf8mb4';
    const TARGET_COLLATION = 'utf8mb4_general_ci';
    const MYISAM_TABLE = 'di_charset_probe_myisam';
    const COMPACT_TABLE = 'di_charset_probe_compact';
    const GENERATED_TABLE = 'di_charset_probe_generated';
    const WIDE_KEY_TABLE = 'di_charset_probe_wide';
    const VIEW = 'di_charset_probe_view';
    const ROUTINE = 'di_charset_probe_fn';
    const ORDERED_TRIGGERS = ['zz_first', 'aa_second'];

    /** @var \diDB */
    private $db;

    /** @var string mb3 spelled the way THIS server spells it */
    private $mb3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Connection::get()->getDb();

        if (!CharsetConverter::supports($this->db)) {
            $this->markTestSkipped('MySQL only');
        }

        $this->mb3 = CharsetConverter::mb3NameFor($this->db);
        if ($this->mb3 === null) {
            $this->markTestSkipped('server has no utf8mb3');
        }

        $this->dropAll();
        $this->createProbe();
    }

    protected function tearDown(): void
    {
        $this->dropAll();

        parent::tearDown();
    }

    private function dropAll(): void
    {
        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        foreach (self::ORDERED_TRIGGERS as $trigger) {
            $this->db->q("DROP TRIGGER IF EXISTS `$trigger`");
        }
        $this->db->q('DROP VIEW IF EXISTS `' . self::VIEW . '`');
        $this->db->q('DROP FUNCTION IF EXISTS `' . self::ROUTINE . '`');
        foreach (
            [
                self::TABLE,
                self::MYISAM_TABLE,
                self::COMPACT_TABLE,
                self::GENERATED_TABLE,
                self::WIDE_KEY_TABLE,
            ]
            as $table
        ) {
            $this->db->q("DROP TABLE IF EXISTS `$table`");
        }
    }

    private function createProbe(): void
    {
        $mb3 = $this->mb3;

        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                slug VARCHAR(32) BINARY NOT NULL,
                title VARCHAR(100) NOT NULL DEFAULT 'none' COMMENT 'a comment',
                body TEXT NULL,
                touched TINYINT NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY slug_idx (slug)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
              DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        $this->db->q(
            'CREATE TRIGGER `' .
                self::TRIGGER .
                '` BEFORE INSERT ON `' .
                self::TABLE .
                '` FOR EACH ROW SET NEW.touched = 1'
        );
    }

    private function convert(): CharsetConverter
    {
        $converter = new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        );

        return $converter->inPreparedSession(false, function ($c) {
            $c->convertTables([self::TABLE]);
        });
    }

    private function columns(): array
    {
        $rs = $this->db->q(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                    COLUMN_COMMENT, COLLATION_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = '" .
                self::TABLE .
                "'"
        );

        $out = [];
        while ($r = $this->db->fetch($rs)) {
            $out[$r->COLUMN_NAME] = $r;
        }

        return $out;
    }

    private function tableCollation(): string
    {
        $rs = $this->db->q(
            "SELECT TABLE_COLLATION AS v FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::TABLE .
                "'"
        );

        return (string) $this->db->fetch($rs)->v;
    }

    public function testConvertsColumnsAndTableDefault(): void
    {
        $this->convert();

        $this->assertSame(self::TARGET_COLLATION, $this->tableCollation());

        foreach (['title', 'body'] as $column) {
            $this->assertSame(
                self::TARGET_COLLATION,
                $this->columns()[$column]->COLLATION_NAME,
                $column
            );
        }
    }

    /** Flattening this would collide slugs differing only in case. */
    public function testBinaryColumnStaysBinary(): void
    {
        $this->convert();

        $this->assertSame(
            self::TARGET . '_bin',
            $this->columns()['slug']->COLLATION_NAME
        );
    }

    public function testColumnAttributesSurvive(): void
    {
        $before = $this->columns();
        $this->convert();
        $after = $this->columns();

        foreach ($before as $name => $column) {
            foreach (
                ['COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT', 'COLUMN_COMMENT']
                as $field
            ) {
                $this->assertSame(
                    $column->$field,
                    $after[$name]->$field,
                    "$name.$field"
                );
            }
        }
    }

    public function testStoredValuesAndTriggerSurvive(): void
    {
        $this->convert();

        $this->db->q(
            'INSERT INTO `' .
                self::TABLE .
                "` (slug, title, body) VALUES ('Ab', 'x', 'y')"
        );

        $rs = $this->db->q(
            'SELECT touched AS v FROM `' . self::TABLE . "` WHERE slug = 'Ab'"
        );
        $r = $this->db->fetch($rs);

        $this->assertNotNull($r, 'row inserted');
        $this->assertSame('1', (string) $r->v, 'trigger still fires');
    }

    /**
     * The point of the whole exercise.
     *
     * The write goes through a connection raised to the target charset for the
     * duration. That is not cheating, it is the missing half of the story: the
     * converter only widens the COLUMNS, and until the connection follows, an
     * mb3 session drops every 4-byte character on the way in — silently on a
     * lax sql_mode, with an error on a strict one. Asserting on the connection
     * this suite happens to run under would test the consumer's Data\Config
     * (still mb3 by design — see the README) instead of the conversion.
     */
    public function testFourByteCharactersSurvive(): void
    {
        $this->convert();

        $emoji = '🎂 юбилей';

        $this->withConnectionCharset(self::TARGET, function () use ($emoji) {
            $this->db->q(
                'INSERT INTO `' .
                    self::TABLE .
                    "` (slug, body) VALUES ('e', '" .
                    $this->db->escape_string($emoji) .
                    "')"
            );

            $rs = $this->db->q(
                'SELECT body AS v FROM `' . self::TABLE . "` WHERE slug = 'e'"
            );
            $r = $this->db->fetch($rs);

            $this->assertNotNull($r, 'the row was inserted');
            $this->assertSame($emoji, (string) $r->v);
        });
    }

    /**
     * Runs $work with BOTH sides on $charset — the client library (which is
     * what escape_string and result decoding use, moved by set_charset) and the
     * server session — then puts back exactly what was there.
     *
     * The connection is a process singleton shared with every other test, so
     * the restore has to be the real previous values: set_charset() also resets
     * collation_connection to the charset default, which is not necessarily the
     * one the consumer configured.
     */
    /**
     * The `utf8mb4_0900_*` family arrived with MySQL 8 and does not exist on
     * 5.7 or MariaDB — which README still names as supported, so a test that
     * needs one has to skip there rather than fail on CREATE TABLE.
     */
    private function requireCollation(string $collation): void
    {
        $rs = $this->db->q(
            "SELECT COUNT(*) AS v FROM information_schema.COLLATIONS
             WHERE COLLATION_NAME = '" .
                $this->db->escape_string($collation) .
                "'"
        );

        if (!(int) $this->db->fetch($rs)->v) {
            $this->markTestSkipped("server has no $collation");
        }
    }

    private function withConnectionCharset(string $charset, callable $work): void
    {
        $vars = [
            'character_set_client',
            'character_set_connection',
            'character_set_results',
            'collation_connection',
        ];

        $previousClient = $this->db->get_charset();
        $previous = [];
        foreach ($vars as $var) {
            $rs = $this->db->q("SELECT @@SESSION.$var AS v");
            $previous[$var] = (string) $this->db->fetch($rs)->v;
        }

        $this->db->set_charset($charset);

        try {
            $work();
        } finally {
            $this->db->set_charset($previousClient);

            $parts = [];
            foreach ($previous as $var => $value) {
                $parts[] = "$var = '" . $this->db->escape_string($value) . "'";
            }
            $this->db->q('SET SESSION ' . implode(', ', $parts));
        }
    }

    public function testSecondRunIsANoop(): void
    {
        $this->convert();
        $before = $this->columns();

        $this->convert();

        $this->assertEquals($before, $this->columns());
    }

    /**
     * The regression this class was silently broken by: a project configured as
     * 'utf8' finds neither that charset nor utf8_general_ci in information_schema
     * from MySQL 8.0.28 on, where they are spelled utf8mb3. Constructing must
     * resolve the spelling instead of throwing…
     */
    public function testMb3SpellingIsResolved(): void
    {
        $converter = new CharsetConverter($this->db, 'utf8', 'utf8_general_ci');

        $this->assertInstanceOf(CharsetConverter::class, $converter);
    }

    /** …and must then treat an already-mb3 table as needing nothing done. */
    public function testMb3TargetLeavesAnMb3TableAlone(): void
    {
        $before = $this->columns();

        (new CharsetConverter(
            $this->db,
            'utf8',
            'utf8_general_ci'
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::TABLE]);
        });

        $this->assertEquals($before, $this->columns());
    }

    /** retarget() is pure; reached through reflection to keep it private. */
    private function retarget(string $ddl): string
    {
        $converter = new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        );
        $method = new \ReflectionMethod($converter, 'retarget');
        $method->setAccessible(true);

        return $method->invoke($converter, $ddl);
    }

    public function testRetargetRewritesMb3Tokens(): void
    {
        $this->assertSame(
            'CHARACTER SET utf8mb4',
            $this->retarget('CHARACTER SET utf8mb3')
        );
        $this->assertSame('CHARSET utf8mb4', $this->retarget('CHARSET utf8'));
        $this->assertSame(
            'COLLATE utf8mb4_bin',
            $this->retarget('COLLATE utf8_bin')
        );
        $this->assertSame(
            "SET x = _utf8mb4'y'",
            $this->retarget("SET x = _utf8'y'"),
            'introducer form'
        );
    }

    /** The token must not be doubled into utf8mb4mb4 on a second pass. */
    public function testRetargetLeavesTheTargetAlone(): void
    {
        $ddl = "CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci _utf8mb4'x'";

        $this->assertSame($ddl, $this->retarget($ddl));
    }

    /** USING BTREE is an index hint, not a charset. */
    public function testIndexHintIsNotMistakenForACharset(): void
    {
        $this->assertSame('USING BTREE', $this->retarget('USING BTREE'));
    }

    /** Same, with an arbitrary target — the rollback direction needs mb3. */
    private function retargetTo(string $charset, string $ddl): string
    {
        $converter = new CharsetConverter(
            $this->db,
            $charset,
            $charset . '_general_ci'
        );
        $method = new \ReflectionMethod($converter, 'retarget');
        $method->setAccessible(true);

        return $method->invoke($converter, $ddl);
    }

    /**
     * mb4 -> mb3. Rewriting only the mb3 spellings would make every consumer's
     * down() a no-op for stored programs: after an mb3 -> mb4 run they all
     * declare mb4, so there is no mb3 token left to rewrite.
     */
    public function testRetargetRewritesTheTargetBackToMb3(): void
    {
        $mb3 = $this->mb3;

        $this->assertSame(
            "CHARACTER SET $mb3",
            $this->retargetTo($mb3, 'CHARACTER SET utf8mb4')
        );
        $this->assertSame(
            "COLLATE {$mb3}_general_ci",
            $this->retargetTo($mb3, 'COLLATE utf8mb4_general_ci')
        );
        $this->assertSame(
            "SET x = _$mb3'y'",
            $this->retargetTo($mb3, "SET x = _utf8mb4'y'"),
            'introducer form'
        );
    }

    /**
     * _bin is case sensitivity, not a locale, so it survives either way. The
     * target charset is itself rewritable now, which puts an already-correct
     * `<target>_bin` within reach of the generic collation rule.
     */
    public function testRetargetKeepsBinaryCollationBothWays(): void
    {
        $mb3 = $this->mb3;

        $this->assertSame(
            'COLLATE utf8mb4_bin',
            $this->retarget('COLLATE utf8mb4_bin'),
            'already on the target'
        );
        $this->assertSame(
            "COLLATE {$mb3}_bin",
            $this->retargetTo($mb3, 'COLLATE utf8mb4_bin'),
            'rolled back'
        );
    }

    public function testMovesMyisamToInnoDb(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::MYISAM_TABLE .
                '` (id INT PRIMARY KEY, a VARCHAR(10)) ENGINE=MyISAM'
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->moveMyisamTablesToInnoDb([self::MYISAM_TABLE]);

        $rs = $this->db->q(
            "SELECT ENGINE AS v FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::MYISAM_TABLE .
                "'"
        );

        $this->assertSame('InnoDB', (string) $this->db->fetch($rs)->v);
        $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
    }

    /**
     * A COMPACT table caps an index at 767 bytes, which an indexed varchar(255)
     * exceeds the moment it is widened to utf8mb4 — the ALTER must carry
     * ROW_FORMAT=DYNAMIC or abort.
     */
    public function testCompactTableWithAnIndexedVarcharConverts(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::COMPACT_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::COMPACT_TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                slug VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug_idx (slug)
            ) ENGINE=InnoDB ROW_FORMAT=COMPACT
              DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::COMPACT_TABLE]);
        });

        $rs = $this->db->q(
            "SELECT ROW_FORMAT AS fmt, TABLE_COLLATION AS coll
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::COMPACT_TABLE .
                "'"
        );
        $r = $this->db->fetch($rs);

        $this->assertSame('Dynamic', (string) $r->fmt);
        $this->assertSame(self::TARGET_COLLATION, (string) $r->coll);
        $this->db->q('DROP TABLE IF EXISTS `' . self::COMPACT_TABLE . '`');
    }

    /**
     * A generated column elsewhere in the schema is not this run's problem when
     * the caller named the tables it wants converted.
     */
    public function testPreflightIgnoresTablesOutOfScope(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::GENERATED_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::GENERATED_TABLE .
                "` (
                a VARCHAR(10),
                b VARCHAR(20) GENERATED ALWAYS AS (CONCAT(a, 'x')) STORED
            ) ENGINE=InnoDB DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::TABLE]);
        });

        $this->assertSame(
            self::TARGET_COLLATION,
            $this->columns()['title']->COLLATION_NAME
        );
        $this->db->q('DROP TABLE IF EXISTS `' . self::GENERATED_TABLE . '`');
    }

    /** …and IS this run's problem when it is in scope. */
    public function testPreflightRejectsAGeneratedColumnInScope(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::GENERATED_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::GENERATED_TABLE .
                "` (
                a VARCHAR(10),
                b VARCHAR(20) GENERATED ALWAYS AS (CONCAT(a, 'x')) STORED
            ) ENGINE=InnoDB DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->convertTables([self::GENERATED_TABLE]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        } finally {
            $this->db->q('DROP TABLE IF EXISTS `' . self::GENERATED_TABLE . '`');
        }

        $this->assertNotNull($message, 'the generated column must be refused');
        $this->assertStringContainsString('EXTRA', $message);
    }

    private function rebuildPrograms($names = null): void
    {
        // Scoped to this test's own objects by default: rebuildStoredPrograms()
        // is otherwise schema-wide, and the connected database is the consumer's.
        if ($names === null) {
            $names = array_merge(
                [self::TRIGGER, self::VIEW, self::ROUTINE],
                self::ORDERED_TRIGGERS
            );
        }

        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->inPreparedSession(false, function ($c) use ($names) {
                $c->rebuildStoredPrograms($names);
            });
        } catch (\Exception $e) {
            // The rebuild is always scoped to this test's own objects, so a
            // failure is normally ours and must not be skipped away. The one
            // exception is environmental: an account that may not recreate a
            // program under its original DEFINER cannot run these tests at all.
            if (strpos($e->getMessage(), 'SET_USER_ID or SUPER') === false) {
                throw $e;
            }

            $this->markTestSkipped($e->getMessage());
        }
    }

    private function triggerRow(string $name)
    {
        $rs = $this->db->q(
            "SELECT ACTION_STATEMENT AS body, ACTION_ORDER AS ord,
                    SQL_MODE AS sql_mode
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '$name'"
        );

        return $this->db->fetch($rs);
    }

    /** The body must be retargeted, not just the table under it. */
    public function testTriggerBodyIsRetargeted(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        $this->db->q(
            'CREATE TRIGGER `' .
                self::TRIGGER .
                '` BEFORE INSERT ON `' .
                self::TABLE .
                "` FOR EACH ROW SET NEW.title = CONVERT(NEW.title USING $mb3)"
        );

        $this->convert();
        $this->rebuildPrograms();

        $body = (string) $this->triggerRow(self::TRIGGER)->body;

        $this->assertStringContainsString(self::TARGET, $body);
        $this->assertStringNotContainsString("USING $mb3", $body);
    }

    /**
     * The whole rollback, end to end. This is what the charset check used to
     * make impossible: it whitelisted mb3 plus the target, so a converter
     * pointed back at mb3 refused every program the previous run had put on
     * mb4 — and refused it in preflight, before altering anything, so a
     * consumer's down() could never start.
     */
    public function testConversionCanBeRolledBack(): void
    {
        $mb3 = $this->mb3;

        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        $this->db->q(
            'CREATE TRIGGER `' .
                self::TRIGGER .
                '` BEFORE INSERT ON `' .
                self::TABLE .
                "` FOR EACH ROW SET NEW.title = CONVERT(NEW.title USING $mb3)"
        );

        $this->convert();
        $this->rebuildPrograms([self::TRIGGER]);

        // Narrowing, so strict: a 4-byte character that no longer fits must
        // error rather than be swept out silently.
        (new CharsetConverter(
            $this->db,
            $mb3,
            $mb3 . '_general_ci'
        ))->inPreparedSession(true, function ($c) {
            $c->convertTables([self::TABLE]);
            $c->rebuildStoredPrograms([self::TRIGGER]);
        });

        $this->assertSame($mb3 . '_general_ci', $this->tableCollation());
        $this->assertSame(
            $mb3 . '_bin',
            $this->columns()['slug']->COLLATION_NAME,
            'BINARY survives the way back too'
        );

        $body = (string) $this->triggerRow(self::TRIGGER)->body;
        $this->assertStringContainsString("USING $mb3", $body);
        $this->assertStringNotContainsString(self::TARGET, $body);
    }

    /**
     * Several triggers may share a (table, timing, event) since MySQL 5.7, and
     * they fire in ACTION_ORDER — which SHOW CREATE TRIGGER does not carry, so
     * recreating them alphabetically would silently reorder them.
     */
    public function testTriggerFiringOrderIsPreserved(): void
    {
        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        // Named so that alphabetical order is the REVERSE of the wanted one.
        foreach (self::ORDERED_TRIGGERS as $name) {
            $this->db->q(
                "CREATE TRIGGER `$name` BEFORE INSERT ON `" .
                    self::TABLE .
                    '` FOR EACH ROW SET NEW.touched = NEW.touched + 1'
            );
        }

        $before = [
            'zz_first' => (int) $this->triggerRow('zz_first')->ord,
            'aa_second' => (int) $this->triggerRow('aa_second')->ord,
        ];

        $this->convert();
        $this->rebuildPrograms();

        $after = [
            'zz_first' => (int) $this->triggerRow('zz_first')->ord,
            'aa_second' => (int) $this->triggerRow('aa_second')->ord,
        ];

        $this->assertSame($before, $after, 'firing order changed');
    }

    /**
     * The program is created under a mode of this test's own choosing, NOT
     * whatever the server happens to default to.
     *
     * inPreparedSession() strips exactly these two flags, so a rebuild that
     * stamps the session mode instead of the program's own shows up. Reading
     * the mode off the server and skipping when it lacks NO_ZERO_DATE — which
     * is what this used to do — meant the check never ran anywhere sql_mode is
     * empty (the maintainer's own machine included), and there the two strings
     * are equal whatever the code does, so it could not have failed anyway.
     */
    const PROBE_SQL_MODE = 'NO_ZERO_DATE,NO_ZERO_IN_DATE';

    private function underSqlMode(string $mode, callable $work): void
    {
        $rs = $this->db->q('SELECT @@SESSION.sql_mode AS v');
        $previous = (string) $this->db->fetch($rs)->v;

        $this->db->q("SET SESSION sql_mode = '$mode'");

        try {
            $work();
        } finally {
            $this->db->q(
                "SET SESSION sql_mode = '" .
                    $this->db->escape_string($previous) .
                    "'"
            );
        }
    }

    /**
     * A stored program remembers its own sql_mode, and CREATE stamps the session
     * one — which inPreparedSession() deliberately alters. Without restoring it,
     * every rebuild quietly changes the mode of every program in the schema.
     */
    public function testTriggerKeepsItsOwnSqlMode(): void
    {
        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        $this->underSqlMode(self::PROBE_SQL_MODE, function () {
            $this->db->q(
                'CREATE TRIGGER `' .
                    self::TRIGGER .
                    '` BEFORE INSERT ON `' .
                    self::TABLE .
                    '` FOR EACH ROW SET NEW.touched = 1'
            );
        });

        $before = (string) $this->triggerRow(self::TRIGGER)->sql_mode;
        $this->assertStringContainsString('NO_ZERO_DATE', $before, 'probe mode');

        $this->convert();
        $this->rebuildPrograms();

        $this->assertSame(
            $before,
            (string) $this->triggerRow(self::TRIGGER)->sql_mode
        );
    }

    /** A routine takes the extra probe/rename path; its sql_mode must survive it. */
    public function testRoutineKeepsItsOwnSqlMode(): void
    {
        $this->db->q('DROP FUNCTION IF EXISTS `' . self::ROUTINE . '`');
        $this->underSqlMode(self::PROBE_SQL_MODE, function () {
            $this->db->q(
                'CREATE FUNCTION `' .
                    self::ROUTINE .
                    '` (a VARCHAR(10)) RETURNS VARCHAR(10) DETERMINISTIC RETURN a'
            );
        });

        $before = $this->routineSqlMode();
        $this->assertStringContainsString('NO_ZERO_DATE', $before, 'probe mode');

        $this->rebuildPrograms();

        $this->assertSame($before, $this->routineSqlMode());
    }

    private function routineSqlMode(): string
    {
        $rs = $this->db->q(
            "SELECT SQL_MODE AS v FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '" .
                self::ROUTINE .
                "'"
        );
        $r = $rs ? $this->db->fetch($rs) : null;

        return $r ? (string) $r->v : '';
    }

    public function testViewIsRebuiltAndRetargeted(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP VIEW IF EXISTS `' . self::VIEW . '`');
        $this->db->q(
            'CREATE VIEW `' .
                self::VIEW .
                '` AS SELECT id, CONVERT(title USING ' .
                $mb3 .
                ') AS t FROM `' .
                self::TABLE .
                '`'
        );

        $this->convert();
        $this->rebuildPrograms();

        $rs = $this->db->q(
            "SELECT VIEW_DEFINITION AS body FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::VIEW .
                "'"
        );
        $r = $this->db->fetch($rs);

        $this->assertNotNull($r, 'view still exists');
        $this->assertStringContainsString(self::TARGET, (string) $r->body);
        $this->assertStringNotContainsString(
            "using $mb3",
            strtolower((string) $r->body)
        );
    }

    /** COMPRESSED is a deliberate choice with the same 3072-byte limit. */
    public function testCompressedRowFormatIsLeftAlone(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::COMPACT_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::COMPACT_TABLE .
                "` (id INT PRIMARY KEY, a VARCHAR(50))
             ENGINE=InnoDB ROW_FORMAT=COMPRESSED
             DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::COMPACT_TABLE]);
        });

        $rs = $this->db->q(
            "SELECT ROW_FORMAT AS v FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::COMPACT_TABLE .
                "'"
        );

        $this->assertSame('Compressed', (string) $this->db->fetch($rs)->v);
    }

    /**
     * `GRANT ALL ON mydb.*` confers neither SUPER nor SET_USER_ID, so reading it
     * as permission would pass the pre-flight and fail after the first DROP.
     */
    public function testGrantPredicate(): void
    {
        $yes = [
            'GRANT ALL PRIVILEGES ON *.* TO `root`@`%`',
            'GRANT SUPER ON *.* TO `a`@`%`',
            'GRANT SET_USER_ID ON *.* TO `a`@`%`',
        ];
        $no = [
            'GRANT ALL PRIVILEGES ON `mydb`.* TO `app`@`%`',
            'GRANT SELECT, INSERT ON *.* TO `app`@`%`',
            'GRANT TRIGGER ON `mydb`.* TO `app`@`%`',
        ];

        foreach ($yes as $grant) {
            $this->assertTrue(
                CharsetConverter::grantAllowsForeignDefiner($grant),
                $grant
            );
        }
        foreach ($no as $grant) {
            $this->assertFalse(
                CharsetConverter::grantAllowsForeignDefiner($grant),
                $grant
            );
        }
    }

    /** An underscore-prefixed local variable is not a charset introducer. */
    public function testUnderscoreVariableIsNotTakenForACharset(): void
    {
        $this->db->q('DROP FUNCTION IF EXISTS `' . self::ROUTINE . '`');
        $this->db->q(
            'CREATE FUNCTION `' .
                self::ROUTINE .
                '` () RETURNS INT DETERMINISTIC BEGIN DECLARE _count INT; ' .
                'SET _count = 0; RETURN _count; END'
        );

        $this->rebuildPrograms();

        $rs = $this->db->q(
            "SELECT COUNT(*) AS v FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '" .
                self::ROUTINE .
                "'"
        );

        $this->assertSame('1', (string) $this->db->fetch($rs)->v);
    }

    /**
     * A routine that BUILDS SQL as a string must not have that string rewritten,
     * nor be refused for what it says. Only code is rewritten.
     */
    public function testCharsetTokensInsideLiteralsAreLeftAlone(): void
    {
        $this->db->q('DROP FUNCTION IF EXISTS `' . self::ROUTINE . '`');
        $this->db->q(
            'CREATE FUNCTION `' .
                self::ROUTINE .
                '` () RETURNS VARCHAR(80) DETERMINISTIC ' .
                "RETURN 'ALTER TABLE t CONVERT TO CHARACTER SET utf8'"
        );

        $this->rebuildPrograms([self::ROUTINE]);

        $rs = $this->db->q(
            "SELECT ROUTINE_DEFINITION AS body FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '" .
                self::ROUTINE .
                "'"
        );
        $r = $this->db->fetch($rs);

        $this->assertNotNull($r, 'routine survived');
        $this->assertStringContainsString(
            'CHARACTER SET utf8',
            (string) $r->body,
            'the literal must still say utf8'
        );
        $this->assertStringNotContainsString(
            'CHARACTER SET utf8mb4',
            (string) $r->body,
            'the literal must NOT have been rewritten'
        );
    }

    /**
     * MySQL stamps a program with the session charset it was created under, so a
     * project still connected as mb3 (which the README tells it to be until it
     * converts) would get every program put straight back on mb3.
     */
    public function testProgramsAreRebuiltOnTheTargetSessionCharset(): void
    {
        $mb3 = $this->mb3;

        // The connection is a process singleton shared with every other test —
        // restore what was actually there, not the target this test happens to
        // use, or a consumer left on mb3 gets a polluted session (and, with
        // stopOnFailure, a suite that stops on ConnectionCharsetTest).
        $vars = [
            'character_set_client',
            'character_set_connection',
            'character_set_results',
            'collation_connection',
        ];
        $previous = [];
        foreach ($vars as $var) {
            $rs = $this->db->q("SELECT @@SESSION.$var AS v");
            $previous[$var] = (string) $this->db->fetch($rs)->v;
        }

        $this->db->q("SET NAMES $mb3");

        try {
            $this->rebuildPrograms([self::TRIGGER]);

            $rs = $this->db->q(
                "SELECT CHARACTER_SET_CLIENT AS cs, COLLATION_CONNECTION AS co
                 FROM information_schema.TRIGGERS
                 WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '" .
                    self::TRIGGER .
                    "'"
            );
            $r = $this->db->fetch($rs);

            $this->assertSame(self::TARGET, (string) $r->cs);
            $this->assertSame(self::TARGET_COLLATION, (string) $r->co);
        } finally {
            $parts = [];
            foreach ($previous as $var => $value) {
                $parts[] = "$var = '" . $this->db->escape_string($value) . "'";
            }
            $this->db->q('SET SESSION ' . implode(', ', $parts));
        }
    }

    /**
     * Rebuilding ONE trigger of a group must not move it: recreated without a
     * position clause, MySQL appends it after its untouched siblings.
     */
    public function testScopedRebuildKeepsFiringOrder(): void
    {
        $this->db->q('DROP TRIGGER IF EXISTS `' . self::TRIGGER . '`');
        foreach (self::ORDERED_TRIGGERS as $name) {
            $this->db->q(
                "CREATE TRIGGER `$name` BEFORE INSERT ON `" .
                    self::TABLE .
                    '` FOR EACH ROW SET NEW.touched = NEW.touched + 1'
            );
        }

        $order = fn() => [
            self::ORDERED_TRIGGERS[0] => (int) $this->triggerRow(
                self::ORDERED_TRIGGERS[0]
            )->ord,
            self::ORDERED_TRIGGERS[1] => (int) $this->triggerRow(
                self::ORDERED_TRIGGERS[1]
            )->ord,
        ];
        $before = $order();

        // Only the FIRST one — the case that silently reorders.
        $this->rebuildPrograms([self::ORDERED_TRIGGERS[0]]);

        $this->assertSame($before, $order(), 'scoped rebuild moved the trigger');
    }

    /** Scoped rebuild must leave everything it was not asked about alone. */
    public function testRebuildCanBeScopedByName(): void
    {
        $rs = $this->db->q(
            "SELECT CREATED AS v FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '" .
                self::TRIGGER .
                "'"
        );
        $before = (string) $this->db->fetch($rs)->v;

        // Ask for a name that does not exist: nothing may be touched.
        $this->rebuildPrograms(['di_charset_probe_absent']);

        $rs = $this->db->q(
            "SELECT CREATED AS v FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '" .
                self::TRIGGER .
                "'"
        );

        $this->assertSame($before, (string) $this->db->fetch($rs)->v);
    }

    /**
     * `_cp1251'x'` is a charset this converter will not rewrite, and it must be
     * refused rather than left behind in the rebuilt program. The quote lives in
     * the next token once literals are split out, so the scanner has to see it.
     */
    public function testForeignIntroducerIsRefused(): void
    {
        $this->db->q('DROP FUNCTION IF EXISTS `' . self::ROUTINE . '`');
        $this->db->q(
            'CREATE FUNCTION `' .
                self::ROUTINE .
                "` () RETURNS VARCHAR(10) DETERMINISTIC RETURN CONCAT(_latin1\x27x\x27)"
        );

        // NB no fail() inside the try: PHPUnit's AssertionFailedError extends
        // Exception, so the catch would swallow it and then match its own
        // message — the test would pass no matter what.
        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->rebuildStoredPrograms([self::ROUTINE]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->assertNotNull($message, 'the latin1 introducer must be refused');
        $this->assertStringContainsString('latin1', $message);
    }

    /**
     * MyISAM keeps its 1000-byte key cap, and a FULLTEXT table is deliberately
     * left on MyISAM — so an indexed varchar(255) there cannot be widened. It
     * must be named up front, not blow up mid-run.
     */
    public function testMyisamFulltextWithWideKeyIsRefused(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::MYISAM_TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                slug VARCHAR(255) NOT NULL,
                body TEXT,
                PRIMARY KEY (id),
                KEY slug_idx (slug),
                FULLTEXT KEY body_idx (body)
            ) ENGINE=MyISAM DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->convertTables([self::MYISAM_TABLE]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        } finally {
            $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        }

        $this->assertNotNull($message, 'the oversized MyISAM key must be refused');
        $this->assertStringContainsString(self::MYISAM_TABLE, $message);
        // Specifically the pre-flight wording: a mid-run failure from exec()
        // would also name the table, and that is the outcome being prevented.
        $this->assertStringContainsString('1000-byte', $message);
    }

    /**
     * A program already on the target charset but carrying MySQL 8's default
     * collation still disagrees with the columns just normalised.
     */
    public function testForeignCollationOnTargetCharsetIsRewritten(): void
    {
        $this->requireCollation('utf8mb4_0900_ai_ci');

        // The table must be on the target charset first: that collation is not
        // valid for an mb3 column.
        $this->convert();

        $this->db->q('DROP VIEW IF EXISTS `' . self::VIEW . '`');
        $this->db->q(
            'CREATE VIEW `' .
                self::VIEW .
                '` AS SELECT id, title COLLATE utf8mb4_0900_ai_ci AS t FROM `' .
                self::TABLE .
                '`'
        );

        $this->rebuildPrograms([self::VIEW]);

        $rs = $this->db->q(
            "SELECT VIEW_DEFINITION AS body FROM information_schema.VIEWS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::VIEW .
                "'"
        );
        $body = (string) $this->db->fetch($rs)->body;

        $this->assertStringNotContainsString('0900_ai_ci', $body);
        $this->assertStringContainsString(self::TARGET_COLLATION, $body);
    }

    /**
     * Case sensitivity is the same kind of property as _bin: flattening an
     * _as_cs column to _general_ci makes values differing only in case equal.
     */
    public function testCaseSensitiveCollationIsPreserved(): void
    {
        $this->requireCollation('utf8mb4_0900_as_cs');
        $this->requireCollation('utf8mb4_0900_ai_ci');

        $this->db->q('DROP TABLE IF EXISTS `' . self::COMPACT_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::COMPACT_TABLE .
                '` (id INT PRIMARY KEY,
                    cs VARCHAR(50) COLLATE utf8mb4_0900_as_cs,
                    plain VARCHAR(50) COLLATE utf8mb4_0900_ai_ci)
             ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::COMPACT_TABLE]);
        });

        $rs = $this->db->q(
            "SELECT COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" .
                self::COMPACT_TABLE .
                "'"
        );
        $got = [];
        while ($r = $this->db->fetch($rs)) {
            $got[$r->COLUMN_NAME] = $r->COLLATION_NAME;
        }
        $this->db->q('DROP TABLE IF EXISTS `' . self::COMPACT_TABLE . '`');

        $this->assertSame(
            'utf8mb4_0900_as_cs',
            $got['cs'],
            'case-sensitive collation must survive'
        );
        $this->assertSame(
            self::TARGET_COLLATION,
            $got['plain'],
            'the MySQL 8 default must still be normalised'
        );
    }

    public function testUnknownCharsetIsRejected(): void
    {
        $this->expectException(\Exception::class);

        new CharsetConverter($this->db, 'utf8mb9', 'utf8mb9_general_ci');
    }

    /**
     * A DEFAULT holding a 4-byte character, on the table this converter is most
     * often pointed at: already utf8mb4, but on a collation that is not the
     * target, so every column is rebuilt.
     *
     * information_schema.COLUMN_DEFAULT answers with the emoji replaced by '?'
     * (HEX() confirms it: the value really ends 3F), and so does SHOW COLUMNS —
     * so rebuilding the column from either writes the mangled text back and
     * reports success. Only SHOW CREATE TABLE keeps it, as a 0x… literal.
     */
    public function testFourByteColumnDefaultSurvives(): void
    {
        $this->requireCollation('utf8mb4_unicode_ci');

        $emoji = '🎂 юбилей';

        // The CREATE itself has to go over an mb4 connection — an mb3 one
        // cannot even carry the literal to the server.
        $this->withConnectionCharset(self::TARGET, function () use ($emoji) {
            $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
            $this->db->q(
                'CREATE TABLE `' .
                    self::WIDE_KEY_TABLE .
                    "` (
                    id INT NOT NULL AUTO_INCREMENT,
                    t VARCHAR(50) NOT NULL DEFAULT '" .
                    $this->db->escape_string($emoji) .
                    "',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        });

        // …but the conversion runs on the connection the suite has, which for a
        // consumer mid-upgrade is still mb3. That must not cost the default.
        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::WIDE_KEY_TABLE]);
        });

        $this->withConnectionCharset(self::TARGET, function () use ($emoji) {
            $this->db->q(
                'INSERT INTO `' . self::WIDE_KEY_TABLE . '` (id) VALUES (1)'
            );
            $rs = $this->db->q(
                'SELECT t AS v FROM `' . self::WIDE_KEY_TABLE . '` WHERE id = 1'
            );
            $r = $this->db->fetch($rs);

            $this->assertNotNull($r, 'the row was inserted');
            $this->assertSame($emoji, (string) $r->v, 'the DEFAULT was rebuilt');
        });

        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
    }

    /**
     * The DEFAULT is looked for in code only, so neither an enum value nor a
     * comment saying "DEFAULT " can be taken for one, and a doubled quote or a
     * newline inside the value has to survive the round trip untouched.
     */
    public function testAwkwardDefaultsSurvive(): void
    {
        $this->requireCollation('utf8mb4_unicode_ci');

        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::WIDE_KEY_TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                quoted VARCHAR(50) NOT NULL DEFAULT 'it''s a \"test\"',
                blank VARCHAR(50) NOT NULL DEFAULT '',
                wrapped VARCHAR(50) NOT NULL DEFAULT 'one\ntwo',
                enm ENUM('DEFAULT 5','b') NOT NULL DEFAULT 'b',
                noted VARCHAR(50) NOT NULL DEFAULT 'x' COMMENT 'says DEFAULT 9',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $before = $this->defaults(self::WIDE_KEY_TABLE);
        // Or a CREATE that silently failed would compare two empty sets and pass.
        $this->assertCount(5, $before, 'the probe table was created');

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::WIDE_KEY_TABLE]);
        });

        $after = $this->defaults(self::WIDE_KEY_TABLE);
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');

        $this->assertSame($before, $after);
        $this->assertSame("it's a \"test\"", $before['quoted']);
        $this->assertSame('b', $before['enm']);
    }

    private function defaults(string $table): array
    {
        $rs = $this->db->q(
            "SELECT COLUMN_NAME n, COLUMN_DEFAULT d FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'
               AND COLUMN_DEFAULT IS NOT NULL
             ORDER BY ORDINAL_POSITION"
        );

        $out = [];
        while ($r = $this->db->fetch($rs)) {
            $out[$r->n] = (string) $r->d;
        }

        return $out;
    }

    /**
     * An index that no longer fits InnoDB's 3072-byte cap once widened must be
     * named before anything is altered. Found from the ALTER instead, it stops
     * the run partway down the table list — with the connection already on the
     * new charset, so whatever is left unconverted starts truncating.
     */
    public function testWideInnodbKeyIsRefused(): void
    {
        $mb3 = $this->mb3;
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
        // 2700 bytes in mb3, 3600 in mb4.
        $this->db->q(
            'CREATE TABLE `' .
                self::WIDE_KEY_TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                a VARCHAR(500) NOT NULL,
                b VARCHAR(400) NOT NULL,
                PRIMARY KEY (id),
                KEY wide_idx (a, b)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
              DEFAULT CHARSET=$mb3 COLLATE={$mb3}_general_ci"
        );

        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->convertTables([self::WIDE_KEY_TABLE]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $collation = $this->collationOf(self::WIDE_KEY_TABLE);
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');

        $this->assertNotNull($message, 'the oversized key must be refused');
        $this->assertStringContainsString('3072-byte', $message);
        $this->assertStringContainsString(self::WIDE_KEY_TABLE, $message);
        // Pre-flight, not a failed ALTER: nothing may have been touched.
        $this->assertSame($mb3 . '_general_ci', $collation);
    }

    /**
     * A MyISAM table without FULLTEXT is measured against InnoDB's cap, not its
     * own 1000: moveMyisamTablesToInnoDb() has made it InnoDB by the time its
     * columns are widened. An indexed latin1 varchar(1000) is exactly 1000
     * bytes today — legal on MyISAM — and 4000 in mb4.
     */
    public function testPlainMyisamWideKeyIsRefusedAgainstTheInnodbCap(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::MYISAM_TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                a VARCHAR(1000) NOT NULL,
                PRIMARY KEY (id),
                KEY wide_idx (a)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci'
        );

        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->convertTables([self::MYISAM_TABLE]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        } finally {
            $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        }

        $this->assertNotNull($message, 'the oversized key must be refused');
        $this->assertStringContainsString('3072-byte', $message);
    }

    /**
     * The other side of it: a prefix index over the same wide column fits, and
     * refusing it would block a conversion that would have worked.
     */
    public function testPrefixIndexOnAWideColumnIsAccepted(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::WIDE_KEY_TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                a VARCHAR(1000) NOT NULL,
                PRIMARY KEY (id),
                KEY prefix_idx (a(100))
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
              DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci'
        );

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::WIDE_KEY_TABLE]);
        });

        $collation = $this->collationOf(self::WIDE_KEY_TABLE);
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');

        $this->assertSame(self::TARGET_COLLATION, $collation);
    }

    /**
     * An ENUM member holding a 4-byte character must stop the run, not be
     * rebuilt from what information_schema reports.
     *
     * The member's text lives inside COLUMN_TYPE, which modifyClause() copies
     * verbatim — and MySQL renders it as '?' in information_schema AND in
     * SHOW CREATE TABLE, so unlike a DEFAULT there is no lossless source to
     * fall back to. Converting anyway redefines the member as the literal '?',
     * and every stored row holding the original silently collapses to the enum
     * error value ''. Refusing costs one table converted by hand; not refusing
     * costs the column, with the migration reporting success.
     */
    public function testEnumWithAFourByteMemberIsRefused(): void
    {
        $this->requireCollation('utf8mb4_0900_ai_ci');

        // The CREATE has to go over an mb4 connection or the literal never
        // reaches the server intact — the suite's own connection may be mb3.
        $this->withConnectionCharset(self::TARGET, function () {
            $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
            $this->db->q(
                'CREATE TABLE `' .
                    self::WIDE_KEY_TABLE .
                    "` (
                    id INT NOT NULL AUTO_INCREMENT,
                    e ENUM('a', 'x\u{1F600}') NOT NULL DEFAULT 'a',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
                  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
            );
            $this->db->q(
                'INSERT INTO `' .
                    self::WIDE_KEY_TABLE .
                    "` (e) VALUES ('x\u{1F600}')"
            );
        });

        // Guard the guard: if the member did not survive the CREATE, the
        // refusal below would pass for the wrong reason.
        $rs = $this->db->q(
            'SELECT HEX(e) AS v FROM `' . self::WIDE_KEY_TABLE . '` WHERE id = 1'
        );
        $this->assertSame(
            '78F09F9880',
            (string) $this->db->fetch($rs)->v,
            'the 4-byte member was not stored, so this test proves nothing'
        );

        $message = null;
        try {
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->inPreparedSession(false, function ($c) {
                $c->convertTables([self::WIDE_KEY_TABLE]);
            });
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        // Nothing may have been touched: the refusal is a pre-flight, so the
        // row and the column definition are exactly as they were.
        $rs = $this->db->q(
            'SELECT HEX(e) AS v FROM `' . self::WIDE_KEY_TABLE . '` WHERE id = 1'
        );
        $value = (string) $this->db->fetch($rs)->v;
        $collation = $this->collationOf(self::WIDE_KEY_TABLE);

        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');

        $this->assertNotNull($message, 'the conversion was not refused');
        $this->assertStringContainsString('ENUM/SET', (string) $message);
        $this->assertStringContainsString(
            self::WIDE_KEY_TABLE . '.e',
            (string) $message
        );
        $this->assertSame('78F09F9880', $value, 'the stored member was destroyed');
        $this->assertSame('utf8mb4_0900_ai_ci', $collation, 'the table was altered');
    }

    /**
     * An ENUM of plain ASCII members is not collateral damage of the guard
     * above — it still converts.
     */
    public function testPlainEnumStillConverts(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::WIDE_KEY_TABLE .
                "` (
                id INT NOT NULL AUTO_INCREMENT,
                e ENUM('a', 'b') NOT NULL DEFAULT 'a',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
              DEFAULT CHARSET=$this->mb3 COLLATE={$this->mb3}_general_ci"
        );
        $this->db->q('INSERT INTO `' . self::WIDE_KEY_TABLE . "` (e) VALUES ('b')");

        (new CharsetConverter(
            $this->db,
            self::TARGET,
            self::TARGET_COLLATION
        ))->inPreparedSession(false, function ($c) {
            $c->convertTables([self::WIDE_KEY_TABLE]);
        });

        $rs = $this->db->q(
            'SELECT e AS v FROM `' . self::WIDE_KEY_TABLE . '` WHERE id = 1'
        );
        $value = (string) $this->db->fetch($rs)->v;
        $collation = $this->collationOf(self::WIDE_KEY_TABLE);

        $this->db->q('DROP TABLE IF EXISTS `' . self::WIDE_KEY_TABLE . '`');

        $this->assertSame('b', $value);
        $this->assertSame(self::TARGET_COLLATION, $collation);
    }

    /**
     * `character_set_results = NULL` (result conversion off) has to come back as
     * NULL after a stored-program rebuild.
     *
     * It used to be read as '' and written back as `SET SESSION … = ''`, which
     * is ERROR 1115 — and since all four variables were restored by ONE
     * statement, that error took the other three down with it and left the
     * connection on the target charset for everything that ran afterwards.
     */
    public function testSessionIsRestoredWhenResultsCharsetWasNull(): void
    {
        // The configured pair, whatever the consumer running this suite uses —
        // not an assumed mb3.
        $rs = $this->db->q(
            'SELECT @@SESSION.character_set_client AS c,
                    @@SESSION.collation_connection AS n'
        );
        $before = $this->db->fetch($rs);
        $client = (string) $before->c;
        $collation = (string) $before->n;

        $this->db->q('SET SESSION character_set_results = NULL');

        try {
            // An empty scope: no program is touched, so what this exercises is
            // withSessionCharset()'s SET NAMES and its restore, nothing else.
            (new CharsetConverter(
                $this->db,
                self::TARGET,
                self::TARGET_COLLATION
            ))->rebuildStoredPrograms([]);

            $rs = $this->db->q(
                'SELECT @@SESSION.character_set_results AS r,
                        @@SESSION.character_set_client AS c,
                        @@SESSION.collation_connection AS n'
            );
            $after = $this->db->fetch($rs);

            $this->assertNull($after->r, 'character_set_results was not restored');
            $this->assertSame(
                $client,
                (string) $after->c,
                'character_set_client was collateral damage'
            );
            $this->assertSame(
                $collation,
                (string) $after->n,
                'collation_connection was collateral damage'
            );
        } finally {
            $this->db->q("SET NAMES $client COLLATE $collation");
        }
    }

    private function collationOf(string $table): string
    {
        $rs = $this->db->q(
            "SELECT TABLE_COLLATION AS v FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'"
        );
        $r = $rs ? $this->db->fetch($rs) : null;

        return $r ? (string) $r->v : '';
    }
}
