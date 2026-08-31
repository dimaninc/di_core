<?php

namespace diCore\Tests\Controller;

use diCore\Admin\Page\Configuration as ConfigurationPage;
use diCore\Controller\Configuration as ConfigurationController;
use diCore\Data\Configuration as Cfg;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Admin/EditLogGatePages.php';

/**
 * Настройки меняют поведение всего сайта одной галкой, и до сих пор были
 * единственной админской страницей без истории правок: откатить нечем, «кто и
 * когда выключил» не узнать. Пишем разницу одной записью на действие.
 */
class ConfigurationEditLogTest extends TestCase
{
    /** Cfg::$data живёт весь процесс – чужие тесты не должны это заметить */
    private $originalCfgData;

    protected function setUp(): void
    {
        $this->originalCfgData = Cfg::$data;
    }

    protected function tearDown(): void
    {
        Cfg::$data = $this->originalCfgData;
    }

    public function testTwoChangedSettingsBecomeOneRecordWithTwoFields(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old', 'per_page' => 20, 'show_banners' => 1],
            ['site_title' => 'New', 'per_page' => 20, 'show_banners' => 0]
        );

        $probe->runStore(function () {
        });

        $this->assertCount(
            1,
            $probe->savedRecords(),
            'одно сохранение – одна запись, а не запись на настройку'
        );

        $log = $probe->savedRecords()[0];

        $this->assertSame(
            ['site_title' => 'Old', 'show_banners' => 1],
            unserialize($log->data['old_data']),
            'только изменившиеся настройки, ключ настройки играет роль имени поля'
        );
        $this->assertSame(
            ['site_title' => 'New', 'show_banners' => 0],
            unserialize($log->data['new_data'])
        );
    }

    public function testRecordPointsAtTheSettingsTableAndItsSyntheticId(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old'],
            ['site_title' => 'New']
        );

        $probe->runStore(function () {
        });

        $log = $probe->savedRecords()[0];

        // писать надо туда, откуда страница настроек потом читает
        $this->assertSame(
            Cfg::getInstance()->getTableName(),
            $log->data['target_table']
        );
        $this->assertSame(
            ConfigurationPage::EDIT_LOG_TARGET_ID,
            $log->data['target_id'],
            'строки у настроек нет, но validate() требует непустой target_id'
        );
        $this->assertSame($probe->adminId, $log->data['admin_id']);
    }

    public function testNoChangesMeanNoRecord(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Same', 'per_page' => 20],
            ['site_title' => 'Same', 'per_page' => 20]
        );

        $probe->runStore(function () {
        });

        $this->assertSame([], $probe->records);
    }

    /**
     * Значения типизированы (чекбокс приезжает int), поэтому сравнение строгое по
     * === давало бы запись на каждое сохранение вообще без правок.
     */
    public function testTypeNoiseIsNotAChange(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['show_banners' => 0, 'per_page' => '20'],
            ['show_banners' => '0', 'per_page' => 20]
        );

        $probe->runStore(function () {
        });

        $this->assertSame([], $probe->records);
    }

    public function testDisabledEditLogCostsNothingAndWritesNothing(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old'],
            ['site_title' => 'New']
        );
        $probe->editLogEnabled = false;

        $probe->runStore(function () {
        });

        $this->assertSame([], $probe->records);
        $this->assertSame(
            0,
            $probe->reads,
            'выключенный журнал не должен стоить лишнего чтения настроек'
        );
    }

    /** Снимок «после» – именно после действия, иначе разницы не будет никогда */
    public function testSnapshotsSurroundTheAction(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old'],
            ['site_title' => 'New']
        );
        $readsInsideAction = null;

        $probe->runStore(function () use ($probe, &$readsInsideAction) {
            $readsInsideAction = $probe->reads;
        });

        $this->assertSame(1, $readsInsideAction);
        $this->assertSame(2, $probe->reads);
    }

    public function testBrokenLogDoesNotBreakSavingTheSettings(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old'],
            ['site_title' => 'New']
        );
        $probe->saveThrows = true;
        $stored = false;

        $probe->runStore(function () use (&$stored) {
            $stored = true;
        });

        $this->assertTrue($stored);
        $this->assertFalse($probe->records[0]->saved);
    }

    /** А вот упавшее сохранение настроек глушить нельзя – и логировать нечего */
    public function testFailingActionIsNotSwallowedAndIsNotLogged(): void
    {
        $probe = ConfigurationControllerProbe::make(
            ['site_title' => 'Old'],
            ['site_title' => 'New']
        );

        $caught = null;

        try {
            $probe->runStore(function () {
                throw new \Exception('unable to store');
            });
        } catch (\Exception $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'exception expected');
        $this->assertSame('unable to store', $caught->getMessage());
        $this->assertSame([], $probe->records);
        $this->assertSame(1, $probe->reads);
    }

    /**
     * Гейт спрашивается по МОДУЛЮ, а не по имени таблицы. Проверяется наизнанку:
     * таблица тут переименована в ту, чьё имя резолвится в страницу с включённым
     * журналом, – значит вопрос «по таблице» ответил бы true. Настоящий вопрос –
     * про модуль configuration, у которого useEditLog() по умолчанию false.
     *
     * Расхождение не гипотетическое: Data\Configuration::setTableName() публичен,
     * а обычное переименование резолвится в никуда, то есть журнал молча
     * выключался бы при живой вкладке.
     */
    public function testGateIsAskedByModuleNotByTableName(): void
    {
        $cfg = Cfg::getInstance();
        $originalTable = $cfg->getTableName();

        try {
            $cfg->setTableName('edit_log_gate_probe_on');

            $probe = (new \ReflectionClass(
                ConfigurationController::class
            ))->newInstanceWithoutConstructor();

            $method = new \ReflectionMethod(
                ConfigurationController::class,
                'isEditLogEnabled'
            );
            $method->setAccessible(true);

            $this->assertFalse(
                $method->invoke($probe),
                'ответ обязан прийти от страницы модуля configuration, а не от таблицы'
            );
        } finally {
            $cfg->setTableName($originalTable);
        }
    }

    /**
     * В журнал попадает ровно то, что страница настроек показывает и умеет
     * менять: без title настройка не выводится, hidden прячется.
     */
    public function testOnlyVisibleSettingsAreSnapshotted(): void
    {
        Cfg::$data = [
            'site_title' => ['title' => 'Title', 'type' => 'string', 'value' => 'A'],
            'internal_key' => ['type' => 'string', 'value' => 'secret'],
            'db_version' => [
                'title' => 'Version',
                'type' => 'string',
                'value' => '5',
                'flags' => ['hidden'],
            ],
            'empty_one' => ['title' => 'Empty', 'type' => 'string'],
        ];

        $probe = ConfigurationControllerProbe::make([], []);

        $this->assertSame(
            [
                'site_title' => 'A',
                'empty_one' => '',
            ],
            $probe->runFilter()
        );
    }
}

class ConfigurationControllerProbe extends ConfigurationController
{
    /** @var array[] очередь ответов readConfigurationValues() */
    public $snapshots = [];
    public $reads = 0;
    /** @var EditLogRecordProbe[] */
    public $records = [];
    public $editLogEnabled = true;
    public $saveThrows = false;
    public $adminId = 7;

    public static function make(array $before, array $after): self
    {
        /** @var self $probe */
        $probe = (new \ReflectionClass(
            self::class
        ))->newInstanceWithoutConstructor();
        $probe->snapshots = [$before, $after];

        return $probe;
    }

    public function runStore(callable $action)
    {
        return $this->runWithEditLog($action);
    }

    public function runFilter()
    {
        return $this->filterConfigurationValues();
    }

    /** @return EditLogRecordProbe[] */
    public function savedRecords(): array
    {
        return array_values(
            array_filter($this->records, function (EditLogRecordProbe $r) {
                return $r->saved;
            })
        );
    }

    // сама по себе выборка настроек ходит в базу, здесь она подменена
    protected function readConfigurationValues()
    {
        $this->reads++;

        return array_shift($this->snapshots);
    }

    protected function isEditLogEnabled()
    {
        return $this->editLogEnabled;
    }

    protected function getEditLogAdminId()
    {
        return $this->adminId;
    }

    protected function createEditLogRecord()
    {
        $record = new EditLogRecordProbe($this->saveThrows);

        $this->records[] = $record;

        return $record;
    }
}

/** Заменяет diCore\Entity\AdminTableEditLog\Model: те же сеттеры, без базы */
class EditLogRecordProbe
{
    public $data = [];
    public $saved = false;
    private $saveThrows;

    public function __construct(bool $saveThrows = false)
    {
        $this->saveThrows = $saveThrows;
    }

    public function setTargetTable($value)
    {
        return $this->set('target_table', $value);
    }

    public function setTargetId($value)
    {
        return $this->set('target_id', $value);
    }

    public function setAdminId($value)
    {
        return $this->set('admin_id', $value);
    }

    public function setOldData($value)
    {
        return $this->set('old_data', $value);
    }

    public function setNewData($value)
    {
        return $this->set('new_data', $value);
    }

    public function save()
    {
        if ($this->saveThrows) {
            // так ведёт себя Model::validate() на пустом admin_id (CLI)
            throw new \Exception('Admin required');
        }

        $this->saved = true;

        return $this;
    }

    private function set($field, $value)
    {
        $this->data[$field] = $value;

        return $this;
    }
}
