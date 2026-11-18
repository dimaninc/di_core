<?php

namespace diCore\Tests\Controller;

use diCore\Base\Exception\HttpException;
use PHPUnit\Framework\TestCase;

class TestableCliRouting extends \diBaseController
{
    public static $cli = false;
    public $calls = [];

    public function __construct()
    {
        // No parent constructor: it starts a session.
    }

    public static function isCli()
    {
        return static::$cli;
    }

    public function _cliRunAction()
    {
        $this->calls[] = 'cli';
    }

    public function runAction()
    {
        $this->calls[] = 'plain';
    }

    public function _getRunAction()
    {
        $this->calls[] = 'get';
    }
}

/**
 * The HTTP method is written by the client: `CLI /api/…` used to route a web
 * request into `_cli*Action` – crons and long-running workers.
 */
class CliActionOverHttpTest extends TestCase
{
    private $method;

    protected function setUp(): void
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
        TestableCliRouting::$cli = false;
    }

    protected function tearDown(): void
    {
        if ($this->method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->method;
        }

        TestableCliRouting::$cli = false;
    }

    public function testCliMethodOverHttpIsNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'CLI';
        $c = new TestableCliRouting();

        try {
            $c->act('run');
            $this->fail('a cli action was reached over HTTP');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame([], $c->calls, 'neither the cli nor the plain action');
    }

    public function testCliActionRunsFromCli(): void
    {
        TestableCliRouting::$cli = true;
        $c = new TestableCliRouting();

        $c->act('run');

        $this->assertSame(['cli'], $c->calls);
    }

    public function testRegularMethodsStillRoute(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $c = new TestableCliRouting();

        $c->act('run');

        $this->assertSame(['get'], $c->calls);
    }
}
