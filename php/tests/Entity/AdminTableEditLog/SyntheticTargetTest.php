<?php

namespace diCore\Tests\Entity\AdminTableEditLog;

use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use PHPUnit\Framework\TestCase;

/**
 * A record can point at a whole table instead of a row: the settings log writes a
 * synthetic target_id because `configuration` has no row to hang history on, and
 * no model class either. Rendering such a record used to kill the page —
 * Admin\Page\AdminTableEditLog::renderForm() calls getTarget()->appearanceForAdmin(),
 * and createForTable() throws on a table with no model.
 *
 * Framework-only: the unresolvable branch must not touch the database at all.
 */
class SyntheticTargetTest extends TestCase
{
    private function recordFor(string $table, $id): TableEditLog
    {
        /** @var TableEditLog $log */
        $log = TableEditLog::create();

        return $log
            ->setTargetTable($table)
            ->setTargetId($id)
            ->setAdminId(7)
            ->setOldData(serialize(['a' => 1]))
            ->setNewData(serialize(['a' => 2]));
    }

    public function testATableWithNoModelHasNoTargetRow(): void
    {
        $this->assertFalse($this->recordFor('configuration', 1)->hasTargetModel());
        $this->assertFalse(
            $this->recordFor('no_such_table_at_all', 3)->hasTargetModel()
        );
    }

    /** A real entity still resolves — the guard must not mute the ordinary case */
    public function testAnEntityTableStillHasATargetRow(): void
    {
        $this->assertTrue(
            $this->recordFor('admin_table_edit_log', 1)->hasTargetModel()
        );
    }

    public function testAnUnresolvableTargetRendersInsteadOfThrowing(): void
    {
        $target = $this->recordFor('configuration', 1)->getTarget();

        $this->assertInstanceOf(\diModel::class, $target);
        $this->assertFalse($target->exists());
        $this->assertSame('---', $target->appearanceForAdmin());
    }

    /**
     * The empty model must NOT be loaded by the synthetic id: `configuration` has an
     * `id` column of its own, so a no-strict load would resolve id 1 to whichever
     * setting happens to sit there and print it as the edit target.
     */
    public function testTheSyntheticIdIsNotUsedToLoadARow(): void
    {
        $target = $this->recordFor('configuration', 1)->getTarget();

        $this->assertSame(0, (int) $target->getId());
    }

    /**
     * No row means no form: /_admin/configuration/form/1/ is a page that dies
     * (Admin\Page\Configuration::renderForm() throws), so the link goes to the module.
     */
    public function testHrefPointsAtTheModuleWhenThereIsNoRow(): void
    {
        $this->assertSame(
            '/_admin/configuration/',
            $this->recordFor('configuration', 1)->getTargetAdminHref()
        );
        $this->assertSame(
            '/_admin/admin_table_edit_log/form/5/',
            $this->recordFor('admin_table_edit_log', 5)->getTargetAdminHref()
        );
    }
}
