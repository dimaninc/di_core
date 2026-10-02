<?php

namespace diCore\Tests\Controller;

use diCore\Controller\AdminTableEditLog as AdminTableEditLogController;
use PHPUnit\Framework\TestCase;

/**
 * Only the controller's own routing logic (param validation, module
 * resolution) is exercised here without a live DB/session – constructing a
 * real diBaseAdminController needs both, and the real work (pagination,
 * degradation, the useEditLog()/hideEditLog() gate) belongs to
 * BasePage::loadEditLogPage(), already covered end to end by
 * EditLogLazyLoadTest. Not covered here: the happy path, which needs an actual
 * registered admin page and a live diAdminUser session.
 */
class AdminTableEditLogTest extends TestCase
{
    private $originalGet;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
    }

    public function testMissingTableIsABadRequest(): void
    {
        $_GET = ['id' => 1];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame('table and id are required', $result['message']);
    }

    public function testMissingIdIsABadRequest(): void
    {
        $_GET = ['table' => 'probe_table'];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame('table and id are required', $result['message']);
    }

    public function testUnknownTableIsNotFound(): void
    {
        $_GET = [
            'table' => 'di_core_tests_no_such_table_at_all',
            'id' => 1,
        ];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame(
            "Unknown table 'di_core_tests_no_such_table_at_all'",
            $result['message']
        );
    }

    private function runPageAction(): array
    {
        /** @var AdminTableEditLogController $controller */
        $controller = (new \ReflectionClass(
            AdminTableEditLogController::class
        ))->newInstanceWithoutConstructor();

        return $controller->pageAction();
    }
}
