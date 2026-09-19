<?php

namespace diCore\Tests\Entity\AdminTableEditLog;

use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use PHPUnit\Framework\TestCase;

/**
 * Rows of a 'dynamic' field are folded into the PARENT's record: that is the only
 * record the parent form's log tab reads. In memory, no DB writes.
 */
class NestedRowsLogTest extends TestCase
{
    private const TABLE = '_di_core_test_nested_rows';

    private function log(): TableEditLog
    {
        return TableEditLog::create()
            ->setTargetTable('_di_core_test_parent')
            ->setTargetId(7)
            ->setAdminId(42);
    }

    private function old(TableEditLog $log): array
    {
        return unserialize($log->getOldData());
    }

    private function new(TableEditLog $log): array
    {
        return unserialize($log->getNewData());
    }

    public function testEditedColumnIsLoggedAlone(): void
    {
        $log = $this->log()->addNestedRowsData(
            'items',
            self::TABLE,
            [5 => ['id' => '5', 'title' => 'Old', 'visible' => '1']],
            [5 => ['id' => '5', 'title' => 'New', 'visible' => '1']]
        );

        $this->assertSame(['items[5].title' => 'Old'], $this->old($log));
        $this->assertSame(['items[5].title' => 'New'], $this->new($log));
    }

    public function testAddedRowIsLoggedAsAWholeWithoutIdAndSkippedFields(): void
    {
        $log = $this->log()->addNestedRowsData(
            'items',
            self::TABLE,
            [],
            [
                9 => [
                    'id' => '9',
                    'title' => 'Фраза',
                    'created_at' => '2026-09-19 10:00:00',
                ],
            ]
        );

        $this->assertSame(['items[9]' => null], $this->old($log));
        $this->assertSame(['items[9]' => '{"title":"Фраза"}'], $this->new($log));
        $log->validate();
        $this->assertSame(
            [],
            $log->preparedValidationErrors(),
            'a create-only record is saved'
        );
    }

    public function testRemovedRowKeepsItsLastStateToRestoreFrom(): void
    {
        $log = $this->log()->addNestedRowsData(
            'items',
            self::TABLE,
            [3 => ['id' => '3', 'title' => 'Gone/away', 'visible' => '0']],
            []
        );

        $this->assertSame(
            ['items[3]' => '{"title":"Gone/away","visible":"0"}'],
            $this->old($log)
        );
        $this->assertSame(['items[3]' => null], $this->new($log));
    }

    public function testUntouchedRowsLeaveTheRecordEmpty(): void
    {
        $rows = [5 => ['id' => '5', 'title' => 'Same', 'note' => null]];

        $log = $this->log()->addNestedRowsData('items', self::TABLE, $rows, $rows);

        $this->assertFalse($log->hasOldData());
        $this->assertFalse($log->hasNewData());
    }

    public function testNullAndEmptyStringAreDifferentValues(): void
    {
        $log = $this->log()->addNestedRowsData(
            'items',
            self::TABLE,
            [5 => ['id' => '5', 'note' => null]],
            [5 => ['id' => '5', 'note' => '']]
        );

        $this->assertSame(['items[5].note' => null], $this->old($log));
        $this->assertSame(['items[5].note' => ''], $this->new($log));
    }

    public function testNestedRowsJoinTheParentsOwnChanges(): void
    {
        $log = $this->log()
            ->setOldData(serialize(['title' => 'Parent old']))
            ->setNewData(serialize(['title' => 'Parent new']))
            ->addNestedRowsData(
                'items',
                self::TABLE,
                [5 => ['id' => '5', 'title' => 'a']],
                [5 => ['id' => '5', 'title' => 'b']]
            );

        $this->assertSame(
            ['title' => 'Parent old', 'items[5].title' => 'a'],
            $this->old($log)
        );
        $this->assertSame(
            ['title' => 'Parent new', 'items[5].title' => 'b'],
            $this->new($log)
        );
    }

    public function testFoldedRecordRendersThroughTheLogTemplateData(): void
    {
        $log = $this->log()
            ->addNestedRowsData(
                'items',
                self::TABLE,
                [3 => ['id' => '3', 'title' => 'Gone']],
                [9 => ['id' => '9', 'title' => 'Added']]
            )
            ->parseData();

        // the tab template prints old values as they are: an array there is a notice
        foreach ($log->getOldValues() as $value) {
            $this->assertTrue($value === null || is_scalar($value));
        }

        $this->assertSame('', (string) $log->getOldValues('items[9]'));
        $this->assertSame('{"title":"Added"}', $log->getNewValues('items[9]'));
    }
}
