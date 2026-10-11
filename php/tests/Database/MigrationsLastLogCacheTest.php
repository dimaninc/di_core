<?php

namespace diCore\Tests\Database;

use diCore\Database\Tool\MigrationsManager;
use diCore\Database\Tool\Migration;
use diCore\Entity\DiMigrationsLog\Model;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * Кеш последних записей журнала не должен превращать сбой запроса в «миграция не
 * применена». Живая часть: DI_CORE_TEST_DB=postgresql://… или mysql://… (см. AuthorizationPinTestDb).
 */
class MigrationsLastLogCacheTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function tearDown(): void
    {
        MigrationsManager::setCacheAllowed(false);
        $cache = new \ReflectionProperty(MigrationsManager::class, 'lastLogCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    public function testLiveCachedLastLogDecidesWasExecuted(): void
    {
        AuthorizationPinTestDb::need($this);

        $manager = new MigrationsManager();
        $db = AuthorizationPinTestDb::db();
        // Только свои idx: в журнале могут быть записи самой базы
        $own = 'DELETE FROM ' . MigrationsManager::logTable .
            " WHERE idx IN ('990001', '990002', '990003', '990004')";
        $db->q($own);

        $log = function (string $idx, int $direction) {
            Model::create()
                ->setIdx($idx)
                ->setName('t')
                ->setDirection($direction)
                ->setDate(\diDateTime::sqlFormat())
                ->save();
        };
        $log('990001', Migration::UP);
        // Последняя запись решает: up, затем down – не применена
        $log('990002', Migration::UP);
        $log('990002', Migration::DOWN);
        // Старый up не должен перекрыть новый: MAX(id) по idx
        $log('990003', Migration::DOWN);
        $log('990003', Migration::UP);

        $manager->cacheLastLogsForIdx(['990001', '990002', '990003', '990004']);

        $this->assertTrue(MigrationsManager::shouldUseCache());
        $this->assertTrue($manager->wasExecuted('990001'));
        $this->assertFalse($manager->wasExecuted('990002'));
        $this->assertTrue($manager->wasExecuted('990003'));
        $this->assertFalse($manager->wasExecuted('990004'), 'no log at all');
        $this->assertNotNull($manager->whenExecuted('990001'));

        $db->q($own);
    }

    public function testFailedQueryDoesNotEnableCacheAndIsReported(): void
    {
        MigrationsManager::setCacheAllowed(true);
        $manager = (new \ReflectionClass(FailingLastLogsManager::class))
            ->newInstanceWithoutConstructor();

        $warnings = [];
        set_error_handler(function ($no, $str) use (&$warnings) {
            $warnings[] = $str;

            return true;
        }, E_USER_WARNING);

        try {
            $manager->cacheLastLogsForIdx(['1']);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse(MigrationsManager::shouldUseCache());
        $this->assertCount(1, $warnings, 'failure is reported, not silent');
        $this->assertStringContainsString('migration', $warnings[0]);
    }
}

class FailingLastLogsManager extends MigrationsManager
{
    protected function queryLastLogs(array $idxAr)
    {
        return false;
    }
}
