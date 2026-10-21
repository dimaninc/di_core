<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Scheduler;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestSetup.php';
require_once __DIR__ . '/QueueTestDb.php';

/**
 * Scheduler on a live server (--once): results to the log, a failing task reported and not
 * stopping the others, one scheduler per database.
 */
final class SchedulerTest extends TestCase
{
    use QueueTestDb;

    /** @var string[] */
    private array $log = [];

    private function scheduler(array $tasks): Scheduler
    {
        return new Scheduler($this->db, $tasks, function ($m) {
            $this->log[] = $m;
        }, true, $this->setup);
    }

    public function testOnceRunsEveryTaskAndReportsAFailure(): void
    {
        $ran = [];
        $code = $this->scheduler([
            'broken' => [60, function () {
                throw new \RuntimeException('disk is gone');
            }],
            'purge' => [60, function () use (&$ran) {
                $ran[] = 'purge';

                return ['job' => 3];
            }],
        ])->run();

        $this->assertSame(0, $code);
        $this->assertSame(['purge'], $ran, 'a failed task does not stop the others');
        $this->assertContains('purge: {"job":3}', $this->log);
        $this->assertContains('broken: RuntimeException: disk is gone', $this->log);
        $this->assertSame(['disk is gone'], $this->setup->reportedMessages());
        $this->assertSame(['scheduler_task' => 'broken'], $this->setup->reports[0]['context']);
    }

    public function testSecondSchedulerExits(): void
    {
        $other = self::openQueueDb('di_core_queue_scheduler_lock')->getDb();
        $this->assertTrue($other->acquireNamedLock(TestSetup::SCHEDULER_LOCK, 0));

        try {
            $ran = false;
            $this->assertSame(1, $this->scheduler(['t' => [60, function () use (&$ran) {
                $ran = true;
            }]])->run());
            $this->assertFalse($ran);
            $this->assertSame(['another scheduler holds the lock, exiting'], $this->log);
        } finally {
            $other->releaseNamedLock(TestSetup::SCHEDULER_LOCK);
        }
    }

    public function testIntervalLongerThanTheLifetimeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scheduler(['daily' => [86400, fn() => null]]);
    }
}
