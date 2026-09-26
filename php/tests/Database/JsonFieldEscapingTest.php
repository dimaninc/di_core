<?php

namespace diCore\Tests\Database;

use diCore\Database\FieldType;
use PHPUnit\Framework\TestCase;

/**
 * A JSON field's leaves must survive save → reload → save unchanged, in value and
 * in type. Escaping them in both diModel and diDB::getJsonForStructure() stored
 * one extra layer per save, and turned int, float and true leaves into strings.
 */
class JsonFieldEscapingTest extends TestCase
{
    private const TABLE = '_di_core_test_json_field_escaping';

    private const TRICKY = [
        'quotes' => '"Что такое осень..."',
        'apostrophe' => "it's",
        'backslash' => 'C:\\path\\to',
        'newline' => "line 1\nline 2",
        'sql' => "x'); DROP TABLE t; --",
        'int' => 5,
        'float' => 1.5,
        'bool' => true,
        // compressJsonData() drops falsy top-level keys, so those live one level down
        'nested' => [
            'deep "quote"',
            'deep\\slash',
            'k"ey' => "v'al",
            'zero' => 0,
            'false' => false,
            'null' => null,
            'numeric string' => '13',
        ],
    ];

    private \diDB $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        // di_core tests run under a consumer's default connection, whatever it is
        if (!($this->db instanceof \diMYSQLi) || !$this->db->doesSupportJson()) {
            $this->markTestSkipped('Needs a MySQL server with a native JSON type');
        }

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) DEFAULT NULL,
                payload JSON DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ' .
                \diCore\Data\Config::getDbCharsetClause()
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db instanceof \diMYSQLi) {
            $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        }

        parent::tearDown();
    }

    private function makeModel(): \diModel
    {
        return new class extends \diModel {
            const table = '_di_core_test_json_field_escaping';

            protected static $fieldTypes = [
                'id' => FieldType::int,
                'title' => FieldType::string,
                'payload' => FieldType::json,
            ];
        };
    }

    private function reload(int $id): \diModel
    {
        $m = $this->makeModel();
        $m->initFrom($this->db->r(self::TABLE, $id));

        return $m;
    }

    // MySQL's JSON type reorders object keys, so compare contents only
    private function assertSameJson(
        array $expected,
        $actual,
        string $message = ''
    ): void {
        $sort = function (array $a) use (&$sort): array {
            ksort($a);

            return array_map(fn($v) => is_array($v) ? $sort($v) : $v, $a);
        };

        $this->assertIsArray($actual, $message);
        $this->assertSame($sort($expected), $sort($actual), $message);
    }

    public function testLeavesSurviveRepeatedSaves(): void
    {
        $m = $this->makeModel();
        $m->updateJsonData('payload', self::TRICKY)->save();
        $id = (int) $m->getId();
        $this->assertSameJson(
            self::TRICKY,
            $this->reload($id)->getJsonData('payload'),
            'after save #1'
        );

        // Each save rewrites the whole payload, even when only another key changed
        for ($save = 2; $save <= 4; $save++) {
            $this->reload($id)->updateJsonData('payload', 'counter', $save)->save();

            $this->assertSameJson(
                self::TRICKY + ['counter' => $save],
                $this->reload($id)->getJsonData('payload'),
                "after save #$save"
            );
        }
    }

    public function testNonJsonFieldsStayEscapedOnce(): void
    {
        $m = $this->makeModel();
        $m->set('title', self::TRICKY['sql'])
            ->updateJsonData('payload', 'a', 1)
            ->save();

        $this->assertSame(
            self::TRICKY['sql'],
            $this->reload((int) $m->getId())->get('title')
        );
    }

    public function testJsonFieldSetAsStringRoundTrips(): void
    {
        $m = $this->makeModel();
        $m->set('payload', json_encode(self::TRICKY))->save();

        $this->assertSameJson(
            self::TRICKY,
            $this->reload((int) $m->getId())->getJsonData('payload')
        );
    }

    public function testDirectDbInsertStillEscapesStructures(): void
    {
        // diDB callers passing arrays rely on getJsonForStructure() escaping itself
        $id = $this->db->insert(self::TABLE, ['payload' => self::TRICKY]);

        $this->assertSameJson(
            self::TRICKY,
            $this->reload((int) $id)->getJsonData('payload')
        );
    }

    public function testNonFiniteFloatsAreStoredAsStrings(): void
    {
        // JSON has no INF/NAN; a bare INF in SQL is read as a column name
        $value = ['inf' => INF, 'n' => [NAN, -INF]];
        $expected = ['inf' => 'INF', 'n' => ['NAN', '-INF']];

        $m = $this->makeModel();
        $m->updateJsonData('payload', $value)->save();
        $this->assertSameJson(
            $expected,
            $this->reload((int) $m->getId())->getJsonData('payload'),
            'via the model'
        );

        // A direct insert never went through diModel's stringification
        $id = $this->db->insert(self::TABLE, ['payload' => $value]);
        $this->assertSameJson(
            $expected,
            $this->reload((int) $id)->getJsonData('payload'),
            'via diDB::insert()'
        );
    }

    /**
     * The fallback for servers without a JSON type sends one escaped literal; it
     * must round-trip like the native path and refuse what it cannot encode.
     */
    public function testFallbackWithoutJsonTypeRoundTripsAndRefusesBadUtf8(): void
    {
        $flag = new \ReflectionProperty(\diMYSQLi::class, 'supportsJson');
        $saved = $flag->getValue($this->db);
        $flag->setValue($this->db, false);

        try {
            $id = $this->db->insert(self::TABLE, ['payload' => self::TRICKY]);
            $this->assertSameJson(
                self::TRICKY,
                $this->reload((int) $id)->getJsonData('payload')
            );

            $this->expectException(\InvalidArgumentException::class);
            $this->db->insert(self::TABLE, ['payload' => ['bad' => "\xB1\x31"]]);
        } finally {
            $flag->setValue($this->db, $saved);
        }
    }
}
