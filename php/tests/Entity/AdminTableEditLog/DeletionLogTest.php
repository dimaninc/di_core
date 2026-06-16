<?php

namespace diCore\Tests\Entity\AdminTableEditLog;

use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use PHPUnit\Framework\TestCase;

/**
 * The deletion path stores the whole row in old_data with an empty new_data and
 * operation = delete; validate() must accept that shape while still requiring
 * new_data for ordinary (toggle/form) updates.
 *
 * Framework-only: no consumer-project entities or types, no DB writes (logs are
 * built and validated in memory).
 */
class DeletionLogTest extends TestCase
{
    private function deletedRecord(): \diModel
    {
        return new \diModel(
            ['id' => 5, 'visible' => 1, 'title' => 'Hello'],
            'demo_widgets'
        );
    }

    public function testCreateForDeletionSnapshotsTheWholeRow(): void
    {
        $log = TableEditLog::createForDeletion($this->deletedRecord(), 42);

        $this->assertSame(TableEditLog::OPERATION_DELETE, $log->getOperation());
        $this->assertTrue($log->isDeletion());
        $this->assertSame('demo_widgets', $log->getTargetTable());
        $this->assertSame(5, (int) $log->getTargetId());
        $this->assertSame(42, (int) $log->getAdminId());

        $old = unserialize($log->getOldData());
        $this->assertSame(1, $old['visible']);
        $this->assertSame('Hello', $old['title']);
        $this->assertSame(5, (int) $old['id']);

        $this->assertFalse($log->hasNewData());
    }

    public function testDeletionLogValidatesWithoutNewData(): void
    {
        $log = TableEditLog::createForDeletion($this->deletedRecord(), 42);

        $log->validate();

        $this->assertSame([], $log->preparedValidationErrors());
    }

    public function testOrdinaryUpdateStillRequiresNewData(): void
    {
        $log = TableEditLog::create()
            ->setTargetTable('demo_widgets')
            ->setTargetId(5)
            ->setAdminId(42)
            ->setOldData(serialize(['visible' => 1]));

        $log->validate();

        $this->assertArrayHasKey('new_data', $log->preparedValidationErrors());
    }

    public function testToggleShapedUpdateValidates(): void
    {
        $log = TableEditLog::create()
            ->setTargetTable('demo_widgets')
            ->setTargetId(5)
            ->setAdminId(42)
            ->setOldData(serialize(['visible' => 1]))
            ->setNewData(serialize(['visible' => 0]));

        $log->validate();

        $this->assertSame([], $log->preparedValidationErrors());
        $this->assertFalse($log->isDeletion());
    }
}
