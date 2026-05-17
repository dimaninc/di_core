<?php

namespace diCore\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * Covers the saveToDb() branches in \diModel that resolve the row id when
 * MySQL's insert_id is 0 (existing row, no auto-increment generated):
 *   - INSERT IGNORE on a unique-key conflict (opt-in via allowSkipConflictOnInsert)
 *   - INSERT ... ON DUPLICATE KEY UPDATE on the UPDATE path (parameterized
 *     LAST_INSERT_ID(<idField>) trick wired from saveToDb)
 *
 * Self-contained: creates a throwaway table in setUp and drops it in tearDown,
 * so the test doesn't depend on any specific consumer-project entity.
 */
class SaveToDbTest extends TestCase
{
    private const TABLE = '_di_core_test_save_to_db';

    private \diDB $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                '` (
                id INT NOT NULL AUTO_INCREMENT,
                slug VARCHAR(64) NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                amount INT DEFAULT 0,
                UNIQUE KEY slug_idx (slug),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci'
        );
    }

    protected function tearDown(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        parent::tearDown();
    }

    private function makeModel(): \diModel
    {
        return new class extends \diModel {
            const table = '_di_core_test_save_to_db';
        };
    }

    public function testInsertIgnoreWithoutConflictAssignsNewId(): void
    {
        $m = $this->makeModel();
        $m->set('slug', 'fresh-' . uniqid('', true))
            ->set('title', 'fresh')
            ->set('amount', 5)
            ->allowSkipConflictOnInsert(['slug'])
            ->save();

        $this->assertGreaterThan(0, (int) $m->getId());
    }

    public function testInsertIgnoreConflictWithLookupFieldsPopulatesExistingId(): void
    {
        $slug = 'conflict-' . uniqid('', true);

        $first = $this->makeModel();
        $first->set('slug', $slug)
            ->set('title', 'first')
            ->set('amount', 10)
            ->save();
        $existingId = (int) $first->getId();
        $this->assertGreaterThan(0, $existingId);

        // Duplicate insert hits the unique slug; INSERT IGNORE silently skips it.
        // With lookup fields set, the model should still end up with the existing id.
        $dup = $this->makeModel();
        $dup->set('slug', $slug)
            ->set('title', 'dup')
            ->set('amount', 99)
            ->allowSkipConflictOnInsert(['slug'])
            ->save();

        $this->assertSame($existingId, (int) $dup->getId());
    }

    public function testInsertIgnoreConflictWithoutLookupFieldsLeavesIdEmpty(): void
    {
        $slug = 'noopt-' . uniqid('', true);

        $first = $this->makeModel();
        $first->set('slug', $slug)
            ->set('title', 'first')
            ->set('amount', 10)
            ->save();
        $this->assertGreaterThan(0, (int) $first->getId());

        $dup = $this->makeModel();
        $dup->set('slug', $slug)
            ->set('title', 'dup')
            ->set('amount', 99)
            ->allowSkipConflictOnInsert()
            ->save();

        $this->assertEmpty($dup->getId());
    }

    public function testInsertOrUpdateOnInsertPathReturnsNewId(): void
    {
        $m = $this->makeModel();
        $m->set('slug', 'iou-fresh-' . uniqid('', true))
            ->set('title', 'fresh')
            ->set('amount', 7)
            ->allowInsertOrUpdate()
            ->save();

        $this->assertGreaterThan(0, (int) $m->getId());
    }

    public function testInsertOrUpdateOnUpdatePathReturnsExistingId(): void
    {
        $slug = 'iou-update-' . uniqid('', true);

        $first = $this->makeModel();
        $first->set('slug', $slug)
            ->set('title', 'first')
            ->set('amount', 10)
            ->save();
        $existingId = (int) $first->getId();
        $this->assertGreaterThan(0, $existingId);

        // Same slug -> ON DUPLICATE KEY UPDATE fires the UPDATE path.
        // The parameterized LAST_INSERT_ID(`id`) trick (wired from saveToDb)
        // makes MySQL return the existing row's id rather than 0.
        $upd = $this->makeModel();
        $upd->set('slug', $slug)
            ->set('title', 'updated')
            ->set('amount', 50)
            ->allowInsertOrUpdate()
            ->save();

        $this->assertSame($existingId, (int) $upd->getId());

        // And the row really got updated, not duplicated.
        $row = $this->db->r(
            self::TABLE,
            'WHERE slug = ' . $this->db->escapeValue($slug),
            'id, amount, title'
        );
        $this->assertSame($existingId, (int) $row->id);
        $this->assertSame(50, (int) $row->amount);
        $this->assertSame('updated', $row->title);
    }
}
