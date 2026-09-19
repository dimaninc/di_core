<?php

namespace diCore\Tests\Admin;

use diCore\Admin\BasePage;
use PHPUnit\Framework\TestCase;

/**
 * diDynamicRows::submit() saves and deletes a dynamic field's rows by itself, so
 * the parent's edit-log record sees them only if submit() hands them over. Runs the
 * real submit() against a throwaway table, then builds the record without saving it.
 */
class NestedEditLogTest extends TestCase
{
    const TABLE = '_di_core_test_nested_edit_log';

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
                parent_id INT NOT NULL,
                title VARCHAR(255) NOT NULL DEFAULT \'\',
                note VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ' .
                \diCore\Data\Config::getDbCharsetClause()
        );
    }

    protected function tearDown(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        unset($_POST['items_ids_ar'], $_POST['items_title']);

        parent::tearDown();
    }

    private function insert($parentId, $title): int
    {
        $this->db->insert(self::TABLE, [
            'parent_id' => $parentId,
            'title' => $title,
        ]);

        return (int) $this->db->getLastInsertId();
    }

    /**
     * The form keeps "keep", retypes "edit", drops "drop" and adds a row. A row of
     * another parent must stay out of the diff.
     *
     * @return array [record or null, ids by title]
     */
    private function submit(NestedEditLogProbePage $page): array
    {
        $ids = [
            'keep' => $this->insert(NestedEditLogProbePage::PARENT_ID, 'keep'),
            'edit' => $this->insert(NestedEditLogProbePage::PARENT_ID, 'edit'),
            'drop' => $this->insert(NestedEditLogProbePage::PARENT_ID, 'drop'),
            'other' => $this->insert(NestedEditLogProbePage::PARENT_ID + 1, 'other'),
        ];

        $_POST['items_ids_ar'] = [$ids['keep'], $ids['edit'], -1];
        $_POST['items_title'] = [
            $ids['keep'] => 'keep',
            $ids['edit'] => 'edited',
            -1 => 'added',
        ];

        (new \diDynamicRows($page, 'items'))->submit();

        return [$page->runBuildEditLogRecord(), $ids];
    }

    public function testSubmitHandsEveryKindOfRowChangeToTheParentRecord(): void
    {
        $page = NestedEditLogProbePage::make();
        $page->nested = true;

        [$log, $ids] = $this->submit($page);

        $this->assertNotNull($log, 'nested changes alone are enough for a record');
        $this->assertSame([], $page->reported);
        $this->assertSame('admins', $log->getTargetTable());
        $this->assertSame(
            NestedEditLogProbePage::PARENT_ID,
            (int) $log->getTargetId()
        );

        $old = unserialize($log->getOldData());
        $new = unserialize($log->getNewData());
        $addedId = (int) $this->db->r(self::TABLE, "WHERE title = 'added'", 'id')
            ->id;

        $this->assertSame(
            [
                "items[{$ids['edit']}].title",
                "items[$addedId]",
                "items[{$ids['drop']}]",
            ],
            array_keys($new)
        );
        $this->assertSame('edit', $old["items[{$ids['edit']}].title"]);
        $this->assertSame('edited', $new["items[{$ids['edit']}].title"]);

        $this->assertNull($old["items[$addedId]"]);
        $this->assertSame(
            [
                'parent_id' => (string) NestedEditLogProbePage::PARENT_ID,
                'title' => 'added',
                'note' => null,
            ],
            json_decode($new["items[$addedId]"], true)
        );

        $this->assertSame(
            'drop',
            json_decode($old["items[{$ids['drop']}]"], true)['title']
        );
        $this->assertNull($new["items[{$ids['drop']}]"]);
    }

    public function testNothingIsCollectedWhenNestedLoggingIsOff(): void
    {
        $page = NestedEditLogProbePage::make();
        $page->nested = false;

        [$log] = $this->submit($page);

        $this->assertNull($log);
        $this->assertSame(
            'edited',
            $this->db->r(self::TABLE, "WHERE title = 'edited'", 'title')->title,
            'the rows themselves are saved either way'
        );
    }

    public function testNestedLoggingFollowsUseEditLogByDefault(): void
    {
        $page = NestedEditLogProbePage::make();

        $page->editLog = false;
        $this->assertFalse($page->useEditLogForNestedEntities());

        $page->editLog = true;
        $this->assertTrue($page->useEditLogForNestedEntities());

        $page->editLog = ['show_only_diff' => true];
        $this->assertTrue($page->useEditLogForNestedEntities());
    }

    // read as "no rows", a failed read would log every row as added or removed
    public function testAFailedSnapshotReadIsReportedAndLogsNothing(): void
    {
        $page = NestedEditLogProbePage::make();
        $page->nested = true;
        $page->dropTableAfterSave = true;

        [$log] = $this->submit($page);

        $this->assertNull($log, 'no rows reported as removed');
        $this->assertCount(1, $page->reported);
        $this->assertInstanceOf(\RuntimeException::class, $page->reported[0]);
    }

    public function testAFailedRecordSaveIsReported(): void
    {
        $page = NestedEditLogProbePage::make();
        $page->recordToSave = new class {
            public function save()
            {
                throw new \Exception('store down');
            }
        };

        $page->runAddEditLogRecord();

        $this->assertSame('store down', $page->saveFailure->getMessage());
    }
}

class NestedEditLogProbePage extends BasePage
{
    const PARENT_ID = 777;

    /** @var bool|array */
    public $editLog = false;

    /** @var bool|null null = the base rule */
    public $nested = null;

    public $reported = [];

    public $dropTableAfterSave = false;

    /** @var object|null stands in for the built record when set */
    public $recordToSave = null;

    /** @var \Exception|null */
    public $saveFailure = null;

    public static function make(): self
    {
        return (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
    }

    public function runBuildEditLogRecord()
    {
        return $this->buildEditLogRecord();
    }

    public function runAddEditLogRecord()
    {
        return $this->addEditLogRecord();
    }

    protected function buildEditLogRecord()
    {
        return $this->recordToSave ?? parent::buildEditLogRecord();
    }

    protected function onEditLogSaveFailure(\Exception $e)
    {
        $this->saveFailure = $e;

        return $this;
    }

    // diDynamicRows opens its connection through the parent's model, so the parent
    // table has to be a registered one
    public function getTable()
    {
        return 'admins';
    }

    public function getId()
    {
        return self::PARENT_ID;
    }

    public function getLanguage()
    {
        return 'en';
    }

    public function getAllFields()
    {
        return [
            'items' => [
                'type' => 'dynamic',
                'table' => NestedEditLogTest::TABLE,
                'template' => '{TITLE}',
                'subquery' => fn($table, $field, $id) => "parent_id = '$id'",
                'tech_fields_ar' => fn($table, $field, $id) => [
                    'parent_id' => $id,
                ],
                'fields' => [
                    'title' => 'string',
                ],
                // makes the "after" read fail, the "before" one has already run
                'afterAllSaved' => function () {
                    if ($this->dropTableAfterSave) {
                        \diCore\Database\Connection::get()
                            ->getDb()
                            ->q('DROP TABLE `' . NestedEditLogTest::TABLE . '`');
                    }
                },
            ],
        ];
    }

    public function useEditLog()
    {
        return $this->editLog;
    }

    public function useEditLogForNestedEntities()
    {
        return $this->nested ?? parent::useEditLogForNestedEntities();
    }

    public function getAdmin()
    {
        return new class {
            public function getAdminModel()
            {
                return new \diModel(['id' => 42]);
            }
        };
    }

    public function onNestedEditLogFailure(\Throwable $e)
    {
        $this->reported[] = $e;

        return $this;
    }
}
