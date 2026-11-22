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

/** TINY_ACTIONS: no action part, the cli method is plain `_cliAction`. */
class TestableTinyCliRouting extends TestableCliRouting
{
    const TINY_ACTIONS = true;

    public function _cliAction()
    {
        $this->calls[] = 'cli';
    }

    public function _getAction()
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

    /**
     * camelize() drops empty parts around `_` and method names are
     * case-insensitive, so each of these resolved to `_cliRunAction`.
     */
    public static function cliMethods(): array
    {
        return [
            'CLI' => ['CLI'],
            'leading underscore' => ['_CLI'],
            'trailing underscore' => ['CLI_'],
            'underscore inside' => ['C_LI'],
            'underscores around' => ['__CLI__'],
            'lower case' => ['cli'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cliMethods')]
    public function testCliMethodOverHttpIsNotFound(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $c = new TestableCliRouting();

        try {
            $c->act('run');
            $this->fail('a cli action was reached over HTTP');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame([], $c->calls, 'neither the cli nor the plain action');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cliMethods')]
    public function testTinyCliMethodOverHttpIsNotFound(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $c = new TestableTinyCliRouting();

        try {
            $c->act();
            $this->fail('a tiny cli action was reached over HTTP');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame([], $c->calls);
    }

    public function testTinyActionsStillRoute(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $c = new TestableTinyCliRouting();
        $c->act();

        TestableCliRouting::$cli = true;
        $c->act();

        $this->assertSame(['get', 'cli'], $c->calls);
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
