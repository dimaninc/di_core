<?php

namespace diCore\Tests\Database;

use diCore\Database\Tool\Migration;
use PHPUnit\Framework\TestCase;

/**
 * executeSqlFile() для папок ядра и проекта отдаёт файл в Db::restoreAction, а тот
 * копит ошибки операторов в своём результате, а не в логе БД. Упавший файл обязан
 * остановить миграцию, иначе она помечается выполненной.
 */
class MigrationRestoreResultTest extends TestCase
{
    public function testFailedRestoreThrowsWithFileAndErrors(): void
    {
        try {
            Migration::assertRestored('admins.sql', [
                'ok' => false,
                'errors' => ['CREATE INDEX failed', 'second'],
            ]);
            $this->fail('Failed restore did not throw');
        } catch (\Exception $e) {
            $this->assertStringContainsString('admins.sql', $e->getMessage());
            $this->assertStringContainsString('CREATE INDEX failed', $e->getMessage());
            $this->assertStringContainsString('second', $e->getMessage());
        }
    }

    public function testSuccessfulRestorePasses(): void
    {
        Migration::assertRestored('admins.sql', ['ok' => true, 'errors' => []]);
        $this->addToAssertionCount(1);
    }

    public function testResultWithoutOkFlagIsNotJudged(): void
    {
        // Контроллер без возвращаемых данных – не повод ронять миграцию.
        Migration::assertRestored('admins.sql', null);
        Migration::assertRestored('admins.sql', []);
        $this->addToAssertionCount(1);
    }
}
