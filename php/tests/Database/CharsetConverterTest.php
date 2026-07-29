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
            [self::TABLE, self::MYISAM_TABLE, self::COMPACT_TABLE, self::GENERATED_TABLE]
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

    /** The point of the whole exercise. */
    public function testFourByteCharactersSurvive(): void
    {
        $this->convert();

        $emoji = '🎂 юбилей';
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

        $this->assertSame($emoji, (string) $this->db->fetch($rs)->v);
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

        (new CharsetConverter($this->db, 'utf8', 'utf8_general_ci'))
            ->inPreparedSession(false, function ($c) {
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
        $this->assertSame(
            'CHARSET utf8mb4',
            $this->retarget('CHARSET utf8')
        );
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

    public function testMovesMyisamToInnoDb(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::MYISAM_TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::MYISAM_TABLE .
                '` (id INT PRIMARY KEY, a VARCHAR(10)) ENGINE=MyISAM'
        );

        (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
            ->moveMyisamTablesToInnoDb([self::MYISAM_TABLE]);

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

        (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
            ->inPreparedSession(false, function ($c) {
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

        (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
            ->inPreparedSession(false, function ($c) {
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

        try {
            (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
                ->convertTables([self::GENERATED_TABLE]);
            $this->fail('expected the generated column to be refused');
        } catch (\Exception $e) {
            $this->assertStringContainsString('EXTRA', $e->getMessage());
        } finally {
            $this->db->q('DROP TABLE IF EXISTS `' . self::GENERATED_TABLE . '`');
        }
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
            (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
                ->inPreparedSession(false, function ($c) use ($names) {
                    $c->rebuildStoredPrograms($names);
                });
        } catch (\Exception $e) {
            // The schema may hold a consumer's own program this converter
            // refuses to touch; that is not what these tests are about. A
            // failure on one of OUR objects is, so it must not be skipped away.
            if (strpos($e->getMessage(), 'di_charset_probe') !== false) {
                throw $e;
            }
            foreach (self::ORDERED_TRIGGERS as $ours) {
                if (strpos($e->getMessage(), $ours) !== false) {
                    throw $e;
                }
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
     * A stored program remembers its own sql_mode, and CREATE stamps the session
     * one — which inPreparedSession() deliberately alters (it drops
     * NO_ZERO_DATE). Without restoring it, every rebuild quietly changes the
     * mode of every program in the schema.
     */
    public function testTriggerKeepsItsOwnSqlMode(): void
    {
        $before = (string) $this->triggerRow(self::TRIGGER)->sql_mode;

        if (strpos($before, 'NO_ZERO_DATE') === false) {
            $this->markTestSkipped('session mode carries no NO_ZERO_DATE');
        }

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
        $this->db->q(
            'CREATE FUNCTION `' .
                self::ROUTINE .
                "` (a VARCHAR(10)) RETURNS VARCHAR(10) DETERMINISTIC RETURN a"
        );

        $rs = $this->db->q(
            "SELECT SQL_MODE AS v FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '" .
                self::ROUTINE .
                "'"
        );
        $before = (string) $this->db->fetch($rs)->v;

        if (strpos($before, 'NO_ZERO_DATE') === false) {
            $this->markTestSkipped('session mode carries no NO_ZERO_DATE');
        }

        $this->rebuildPrograms();

        $rs = $this->db->q(
            "SELECT SQL_MODE AS v FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = '" .
                self::ROUTINE .
                "'"
        );

        $this->assertSame($before, (string) $this->db->fetch($rs)->v);
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

        (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
            ->inPreparedSession(false, function ($c) {
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
            "GRANT ALL PRIVILEGES ON *.* TO `root`@`%`",
            "GRANT SUPER ON *.* TO `a`@`%`",
            "GRANT SET_USER_ID ON *.* TO `a`@`%`",
        ];
        $no = [
            "GRANT ALL PRIVILEGES ON `mydb`.* TO `app`@`%`",
            "GRANT SELECT, INSERT ON *.* TO `app`@`%`",
            "GRANT TRIGGER ON `mydb`.* TO `app`@`%`",
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
                "` () RETURNS VARCHAR(80) DETERMINISTIC " .
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

    public function testUnknownCharsetIsRejected(): void
    {
        $this->expectException(\Exception::class);

        new CharsetConverter($this->db, 'utf8mb9', 'utf8mb9_general_ci');
    }
}
