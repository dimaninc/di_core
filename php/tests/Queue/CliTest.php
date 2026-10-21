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
    private function worker(array $options): array
    {
        $err = fopen('php://memory', 'w+');
        $code = Cli::worker($options, new TestSetup(), $err);
        rewind($err);

        return [$code, stream_get_contents($err)];
    }

    public function testUnknownQueueIsRefused(): void
    {
        [$code, $err] = $this->worker(['queues' => 'fast_q,fats_q']);
        $this->assertSame(1, $code);
        $this->assertStringStartsWith('Unknown queue: fats_q', $err);
        $this->assertStringContainsString('fast – fast_q, single_q', $err, 'the groups are listed');

        $this->assertSame([1, true], [($r = $this->worker(['queues' => ' , ']))[0], str_contains($r[1], '(empty)')], 'an empty list');
        $this->assertSame(1, $this->worker([])[0], 'no --queues');
    }
}
