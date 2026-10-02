<?php

namespace diCore\Tests\Controller;

use diCore\Admin\Base;
use diCore\Controller\AdminTableEditLog as AdminTableEditLogController;
use PHPUnit\Framework\TestCase;

/**
 * Only the controller's own routing logic (param validation, module
 * resolution) is exercised here without a live DB/session – constructing a
 * real diBaseAdminController needs both, and the real work (pagination,
 * degradation, the useEditLog()/hideEditLog() gate) belongs to
 * BasePage::loadEditLogPage(), already covered end to end by
 * EditLogLazyLoadTest. The rights check and the hand-over to the page run
 * through the controller's createLiteAdmin()/createPage() seams, with a probe
 * Base and page instead of a live diAdminUser session.
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

    public function testMissingModuleIsABadRequest(): void
    {
        $_GET = ['id' => 1];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame('module and id are required', $result['message']);
    }

    public function testMissingIdIsABadRequest(): void
    {
        $_GET = ['module' => 'probe_module'];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame('module and id are required', $result['message']);
    }

    public function testUnknownModuleIsNotFound(): void
    {
        $_GET = [
            'module' => 'di_core_tests_no_such_module_at_all',
            'id' => 1,
        ];

        $result = $this->runPageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame(
            "Unknown module 'di_core_tests_no_such_module_at_all'",
            $result['message']
        );
    }

    public function testMalformedCursorIsABadRequest(): void
    {
        $_GET = ['module' => 'configuration', 'id' => 1, 'last_id' => 'abc'];
        $controller = $this->runProbe(true);
        $controller->page->cursorValid = false;

        $result = $controller->pageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame('Malformed last_id', $result['message']);
        $this->assertSame('abc', $controller->page->checkedCursor);
        $this->assertSame('not called', $controller->page->lastId);
    }

    /**
     * liteCreate() runs the page constructor, which may have side effects: an
     * admin without access to the module must be turned away before it.
     */
    public function testNoAccessIsForbiddenBeforeThePageIsCreated(): void
    {
        $_GET = ['module' => 'configuration', 'id' => 1];
        $controller = $this->runProbe(false);

        $result = $controller->pageAction();

        $this->assertFalse($result['ok']);
        $this->assertSame("No access to module 'configuration'", $result['message']);
        $this->assertFalse($controller->pageCreated);
    }

    /**
     * Mongo ids are ObjectId strings: (int) turned both the record id and the
     * cursor into wrong numbers.
     */
    public function testIdAndCursorReachThePageUncast(): void
    {
        $_GET = [
            'module' => 'configuration',
            'id' => '65a1f0c2e4b0a1b2c3d4e5f6',
            'last_id' => '65a1f0c2e4b0a1b2c3d4e5f0',
        ];
        $controller = $this->runProbe(true);

        $result = $controller->pageAction();

        $this->assertTrue($result['ok']);
        $this->assertTrue($controller->pageCreated);
        $this->assertSame('65a1f0c2e4b0a1b2c3d4e5f6', $controller->page->id);
        $this->assertSame('65a1f0c2e4b0a1b2c3d4e5f0', $controller->page->lastId);
    }

    public function testEmptyCursorMeansTheFirstChunk(): void
    {
        $_GET = ['module' => 'configuration', 'id' => 7, 'last_id' => ''];
        $controller = $this->runProbe(true);

        $controller->pageAction();

        $this->assertSame('7', $controller->page->id);
        $this->assertNull($controller->page->lastId);
    }

    private function runProbe(bool $allowed): AdminTableEditLogProbeController
    {
        /** @var AdminTableEditLogProbeController $controller */
        $controller = (new \ReflectionClass(
            AdminTableEditLogProbeController::class
        ))->newInstanceWithoutConstructor();

        /** @var AdminTableEditLogProbeBase $admin */
        $admin = (new \ReflectionClass(
            AdminTableEditLogProbeBase::class
        ))->newInstanceWithoutConstructor();
        $admin->allowed = $allowed;

        $controller->admin = $admin;
        $controller->page = new AdminTableEditLogProbePage();

        return $controller;
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

class AdminTableEditLogProbeController extends AdminTableEditLogController
{
    public $admin;
    public $page;
    public bool $pageCreated = false;

    protected function createLiteAdmin()
    {
        return $this->admin;
    }

    protected function createPage($module, Base $admin)
    {
        $this->pageCreated = true;

        return $this->page;
    }
}

class AdminTableEditLogProbeBase extends Base
{
    public bool $allowed = false;

    public function canAccessModule($module, $method = self::DEFAULT_METHOD)
    {
        return $this->allowed;
    }
}

class AdminTableEditLogProbePage
{
    public $id;
    public $lastId = 'not called';
    public bool $cursorValid = true;
    public $checkedCursor = null;

    public function isValidEditLogCursor($cursor)
    {
        $this->checkedCursor = $cursor;

        return $this->cursorValid;
    }

    public function setId($id, $setOriginal = false)
    {
        $this->id = $id;

        return $this;
    }

    public function loadEditLogPage($lastId = null)
    {
        $this->lastId = $lastId;

        return ['html' => '', 'has_more' => false, 'last_id' => null];
    }
}
