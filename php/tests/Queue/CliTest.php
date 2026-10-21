<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Cli;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestSetup.php';

/**
 * Argument checks of the worker entry point: a typo in --queues must not start a worker that
 * serves nothing. No database: the checks come before the connection.
 */
final class CliTest extends TestCase
{
    public function testUnknownQueueIsRefused(): void
    {
        $this->assertSame(1, Cli::worker(['queues' => 'fast_q,fats_q'], new TestSetup()));
        $this->assertSame(1, Cli::worker(['queues' => ' , '], new TestSetup()), 'an empty list');
        $this->assertSame(1, Cli::worker([], new TestSetup()), 'no --queues');
    }
}
