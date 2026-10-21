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

    const REQUIRED_FUNCTIONS = ['pcntl_signal', 'pcntl_async_signals'];

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

    public function testATransactionLeftByATaskIsRolledBack(): void
    {
        $t = TestSetup::TABLE;
        $other = self::openQueueDb('di_core_queue_scheduler_reader')->getDb()->getLink();
        $ran = [];

        $code = $this->scheduler([
            // Aborted: the probe SELECT 1 inside it would fail with 25P02 and stop the process.
            'aborted' => [60, function () {
                $this->db->startTransaction();
                $this->pdo->query('SELECT 1/0');
            }],
            // Left open without an error: every later write would stay invisible until exit.
            'open' => [60, function () {
                $this->db->startTransaction();
            }],
            'next' => [60, function () use (&$ran, $t) {
                $ran[] = 'next';
                $this->pdo->exec("INSERT INTO $t (queue, payload) VALUES ('" . TestSetup::FAST . "', '{}')");
            }],
        ])->run();

        $this->assertSame(0, $code, implode("\n", $this->log));
        $this->assertSame(['next'], $ran);
        $this->assertSame(0, (int) $this->db->getTransactionNestingLevel());
        $this->assertSame(1, (int) $other->query("SELECT count(*) FROM $t")->fetchColumn(), 'the write of next is committed');
        $this->assertSame([
            'aborted: the task left a transaction open',
            'open: the task left a transaction open',
        ], array_values(array_filter($this->setup->reportedMessages(), fn($m) => str_contains($m, 'left'))));
    }

    public function testDeadConnectionStopsTheScheduler(): void
    {
        // The task's own queries go through diDB::q(), which swallows the error: the probe
        // after the task, not the task's exception, must notice the link is gone.
        $db = self::openQueueDb('di_core_queue_scheduler_dead')->getDb();
        $pid = (int) $db->getLink()->query('SELECT pg_backend_pid()')->fetchColumn();
        $killer = $this->pdo;
        $ran = [];

        $scheduler = new Scheduler($db, [
            'killed' => [60, function () use ($db, $killer, $pid) {
                $killer->query("SELECT pg_terminate_backend($pid)");
                usleep(100000);

                return $db->q('SELECT 1') === false ? 'q() returned false' : 'alive';
            }],
            'next' => [60, function () use (&$ran) {
                $ran[] = 'next';
            }],
        ], function ($m) {
            $this->log[] = $m;
        }, true, $this->setup);

        try {
            $scheduler->run();
            $this->fail('a dead connection must end the process');
        } catch (\PDOException $e) {
            $this->assertSame([], $ran, 'nothing runs on a dead connection');
            $this->assertContains('killed: "q() returned false"', $this->log);
        }
    }

    public function testAFailingReportDoesNotStopTheSchedule(): void
    {
        $setup = new class extends TestSetup {
            public function report(\Throwable|string $error, ?\diCore\Queue\Task $task = null, array $context = []): void
            {
                throw new \RuntimeException('sentry is down');
            }
        };
        $ran = [];
        $scheduler = new Scheduler($this->db, [
            'broken' => [60, fn() => throw new \LogicException('bug')],
            'next' => [60, function () use (&$ran) {
                $ran[] = 'next';
            }],
        ], function ($m) {
            $this->log[] = $m;
        }, true, $setup);

        $this->assertSame(0, $scheduler->run());
        $this->assertSame(['next'], $ran);
        $this->assertContains('report failed: RuntimeException: sentry is down', $this->log);
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
