<?php

namespace diCore\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * Covers the schema-introspection helpers on \diDB that migrations use to make
 * down() rollbacks idempotent on MySQL < 8.0.19 (no DROP ... IF EXISTS for
 * indexes/foreign keys):
 *   - columnExists()
 *   - indexExists()  → getIndexNames()
 *   - fkExists()     → getForeignKeyNames()
 *
 * Self-contained: creates a throwaway parent + child (with a named index and a
 * named FK) in setUp and drops them in tearDown, so the test doesn't depend on
 * any specific consumer-project entity.
 */
class SchemaIntrospectionTest extends TestCase
{
    private const PARENT_TABLE = '_di_core_test_schema_parent';
    private const CHILD_TABLE = '_di_core_test_schema_child';
    private const INDEX_NAME = 'title_idx';
    private const FK_NAME = 'fk_di_core_test_child_parent';

    private \diDB $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        $this->dropTables();

        $this->db->q(
            'CREATE TABLE `' .
                self::PARENT_TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci'
        );

        $this->db->q(
            'CREATE TABLE `' .
                self::CHILD_TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                parent_id INT DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX ' .
                self::INDEX_NAME .
                ' (title),
                INDEX parent_idx (parent_id),
                CONSTRAINT ' .
                self::FK_NAME .
                ' FOREIGN KEY (parent_id)
                    REFERENCES `' .
                self::PARENT_TABLE .
                '`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci'
        );
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        // child first — it holds the FK onto the parent
        $this->db->q('DROP TABLE IF EXISTS `' . self::CHILD_TABLE . '`');
        $this->db->q('DROP TABLE IF EXISTS `' . self::PARENT_TABLE . '`');
    }

    public function testColumnExists(): void
    {
        $this->assertTrue($this->db->columnExists(self::CHILD_TABLE, 'title'));
        $this->assertFalse(
            $this->db->columnExists(self::CHILD_TABLE, 'nonexistent')
        );
    }

    public function testIndexExists(): void
    {
        $this->assertTrue(
            $this->db->indexExists(self::CHILD_TABLE, self::INDEX_NAME)
        );
        $this->assertTrue(
            $this->db->indexExists(self::CHILD_TABLE, 'parent_idx')
        );
        $this->assertFalse(
            $this->db->indexExists(self::CHILD_TABLE, 'missing_idx')
        );
        // an index on a different table must not leak through
        $this->assertFalse(
            $this->db->indexExists(self::PARENT_TABLE, self::INDEX_NAME)
        );
    }

    public function testFkExists(): void
    {
        $this->assertTrue($this->db->fkExists(self::CHILD_TABLE, self::FK_NAME));
        $this->assertFalse(
            $this->db->fkExists(self::CHILD_TABLE, 'fk_missing')
        );
        // the FK belongs to the child, not the parent
        $this->assertFalse($this->db->fkExists(self::PARENT_TABLE, self::FK_NAME));
        // a plain index name is not a foreign key
        $this->assertFalse(
            $this->db->fkExists(self::CHILD_TABLE, self::INDEX_NAME)
        );
    }
}
