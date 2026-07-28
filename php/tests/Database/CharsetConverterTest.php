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

    private function rebuildPrograms(): void
    {
        try {
            (new CharsetConverter($this->db, self::TARGET, self::TARGET_COLLATION))
                ->inPreparedSession(false, function ($c) {
                    $c->rebuildStoredPrograms();
                });
        } catch (\Exception $e) {
            // The schema may hold a consumer's own program this converter
            // refuses to touch; that is not what these tests are about. A
            // failure on one of OUR objects is, so it must not be skipped away.
            if (strpos($e->getMessage(), 'di_charset_probe') !== false) {
                throw $e;
            }
            foreach (['zz_first', 'aa_second'] as $ours) {
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
        foreach (['zz_first', 'aa_second'] as $name) {
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

        $this->db->q('DROP TRIGGER IF EXISTS `zz_first`');
        $this->db->q('DROP TRIGGER IF EXISTS `aa_second`');

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

    public function testUnknownCharsetIsRejected(): void
    {
        $this->expectException(\Exception::class);

        new CharsetConverter($this->db, 'utf8mb9', 'utf8mb9_general_ci');
    }
}
