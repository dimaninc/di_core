<?php

namespace diCore\Tests\Database;

use diCore\Database\Engine;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * DDL и чтение журнала действий не должны содержать MySQL-синтаксис для других движков.
 * Живая часть: DI_CORE_TEST_DB=postgresql://… или mysql://… (см. AuthorizationPinTestDb).
 */
class ActionsLogEnginesTest extends TestCase
{
    const TARGET_ID = 987654;

    private $globalDb;

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        $this->globalDb = $GLOBALS['db'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['db'] = $this->globalDb;
    }

    public function testPostgresAndSqliteDdlHasNoMysqlSyntax(): void
    {
        foreach ([Engine::POSTGRESQL, Engine::SQLITE] as $engine) {
            $sql = join("\n", \diActionsLog::getCreateTableSql($engine, 'CHARSET x'));

            $this->assertStringNotContainsString('`', $sql);
            $this->assertStringNotContainsString('AUTO_INCREMENT', $sql);
            $this->assertStringNotContainsString('ENGINE=', $sql);
            $this->assertStringNotContainsString('UNSIGNED', $sql);
            $this->assertStringNotContainsString('CHARSET', $sql);
        }

        $pg = join("\n", \diActionsLog::getCreateTableSql(Engine::POSTGRESQL));
        $this->assertStringContainsString('GENERATED ALWAYS AS IDENTITY', $pg);
        $this->assertStringContainsString('timestamptz', $pg);
    }

    public function testMysqlDdlIsKept(): void
    {
        $sql = join("\n", \diActionsLog::getCreateTableSql(Engine::MYSQL, 'CHARSET x'));

        $this->assertStringContainsString('`di_actions_log`', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB CHARSET x', $sql);
    }

    public function testLiveDdlCreatesAnIsolatedTableOnceAndNotAgain(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $GLOBALS['db'] = $db;
        $db->q('DROP TABLE IF EXISTS di_core_al_flag');

        try {
            // Своя таблица: общий журнал тест не трогает
            new FlagActionsLog(\diTypes::content, 1);
            $this->assertNotFalse($db->q('SELECT COUNT(*) FROM di_core_al_flag'));

            // Второй create() в процессе таблицу не проверяет: CREATE INDEX берёт ShareLock
            $db->q('DROP TABLE di_core_al_flag');
            $db->resetLog();
            new FlagActionsLog(\diTypes::content, 2);
            $this->assertFalse(
                @$db->q('SELECT COUNT(*) FROM di_core_al_flag'),
                'initTable() ran twice'
            );
        } finally {
            $db->q('DROP TABLE IF EXISTS di_core_al_flag');
            $db->resetLog();
        }
    }

    public function testLiveWriteAndReadWithPaging(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $GLOBALS['db'] = $db;
        $log = \diActionsLog::create(\diTypes::content, self::TARGET_ID);
        // Только свои строки: в таблице могут быть чужие
        $own = 'target_type = ' . \diTypes::content . ' AND target_id = ' . self::TARGET_ID;
        $db->q("DELETE FROM di_actions_log WHERE $own");

        try {
            foreach ([1, 2, 3] as $n) {
                \diModel::create(\diActionsLog::logTable)
                    ->setTargetType(\diTypes::content)
                    ->setTargetId(self::TARGET_ID)
                    ->setUserType(\diActionsLog::utAdmin)
                    ->setUserId(1)
                    ->setAction(\diActionsLog::aEdited)
                    ->setInfo("n$n")
                    ->save();
            }

            $all = $log->getLogAr(['action' => \diActionsLog::aEdited]);
            $this->assertSame(['n1', 'n2', 'n3'], array_column($all, 'info'));

            // LIMIT n OFFSET m на любом движке
            $page = $log->getLogAr([
                'action' => \diActionsLog::aEdited,
                '_start' => 1,
                '_per_page' => 1,
            ]);
            $this->assertSame(['n2'], array_column($page, 'info'));
        } finally {
            $db->q("DELETE FROM di_actions_log WHERE $own");
        }
    }
}

class FlagActionsLog extends \diActionsLog
{
    const logTable = 'di_core_al_flag';
}
