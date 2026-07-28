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
        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
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

    public function testUnknownCharsetIsRejected(): void
    {
        $this->expectException(\Exception::class);

        new CharsetConverter($this->db, 'utf8mb9', 'utf8mb9_general_ci');
    }
}
