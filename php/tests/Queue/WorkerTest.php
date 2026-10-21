<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Handler;
use diCore\Queue\MailPump;
use diCore\Queue\PermanentFailure;
use diCore\Queue\RetryLater;
use diCore\Queue\Task;
use diCore\Queue\Worker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestSetup.php';
require_once __DIR__ . '/QueueTestDb.php';

/**
 * Worker on a live server: handler outcomes, run order, exhausted attempts, reports, the
 * mail pass, SIGTERM and the timeout (processes of their own – fixtures/worker.php).
 */
final class WorkerTest extends TestCase
{
    use QueueTestDb;

    private const Q = TestSetup::FAST;
    const REQUIRED_FUNCTIONS = ['pcntl_alarm', 'pcntl_signal', 'pcntl_async_signals', 'posix_kill'];

    /** @var string[] */
    private array $log = [];

    private function work(array $queues, callable $handle, ?MailPump $mail = null): void
    {
        $handler = new class ($handle) implements Handler {
            public function __construct(private $handle)
            {
            }

            public function handle(Task $task): void
            {
                ($this->handle)($task);
            }
        };

        $handlers = array_fill_keys(array_diff($queues, [Worker::MAIL]), $handler);
        $worker = new Worker($this->queue, $queues, $handlers, $mail, function ($m) {
            $this->log[] = $m;
        }, true);

        $this->assertSame(0, $worker->run());
    }

    private function runAtIn(int $id): float
    {
        return (float) $this->pdo->query(
            'SELECT EXTRACT(EPOCH FROM run_at - now()) FROM ' . TestSetup::TABLE . " WHERE id = $id"
        )->fetchColumn();
    }

    public function testHandlerOutcomes(): void
    {
        $ok = $this->queue->push(self::Q, ['do' => 'ok']);
        $later = $this->queue->push(self::Q, ['do' => 'later']);
        $never = $this->queue->push(self::Q, ['do' => 'never']);
        $boom = $this->queue->push(self::Q, ['do' => 'boom']);

        $this->work([self::Q], function (Task $task) {
            match ($task->payload['do']) {
                'ok' => null,
                'later' => throw new RetryLater('rate limited', 100),
                'never' => throw new PermanentFailure('recipient is gone'),
                'boom' => throw new \LogicException('bug'),
            };
        });

        $this->assertSame('done', $this->jobField($ok, 'status'));

        $this->assertSame('queued', $this->jobField($later, 'status'));
        $this->assertEqualsWithDelta(100, $this->runAtIn($later), 3, 'Retry-After above the policy – wait for it');

        $this->assertSame(['failed', 'recipient is gone'], [$this->jobField($never, 'status'), $this->jobField($never, 'error')]);

        $this->assertSame('queued', $this->jobField($boom, 'status'));
        $this->assertEqualsWithDelta(7, $this->runAtIn($boom), 3, 'any exception – a retry by the policy');
        $this->assertStringContainsString('LogicException: bug', $this->jobField($boom, 'error'));

        $this->assertSame([], $this->setup->reports, 'retries and PermanentFailure are not reported');
    }

    public function testOnlyTheFinalFailureIsReported(): void
    {
        $final = $this->queue->push(TestSetup::SINGLE, ['bot_id' => 5]);
        $this->work([TestSetup::SINGLE], fn() => throw new \RuntimeException('getMe failed'));

        $this->assertSame('failed', $this->jobField($final, 'status'));
        $this->assertSame(['getMe failed'], $this->setup->reportedMessages());
        $this->assertSame($final, $this->setup->reports[0]['task']->id, 'the task goes along for tags');
    }

    public function testExhaustedInterruptedRunIsFailedWithoutHandler(): void
    {
        // The worker died on the last attempt: a run by the reserve would be one too many.
        $id = $this->queue->push(TestSetup::SINGLE, []);
        $this->pdo->exec('UPDATE ' . TestSetup::TABLE . " SET status = 'running', attempts = 1, locked_until = now() - interval '1 second' WHERE id = $id");
        $called = false;

        $this->work([TestSetup::SINGLE], function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame('failed', $this->jobField($id, 'status'));
        $this->assertStringContainsString('interrupted', $this->jobField($id, 'error'));
        $this->assertSame([TestSetup::SINGLE . ': attempts exhausted, last run was interrupted'], $this->setup->reportedMessages());
    }

    public function testOrderedExecution(): void
    {
        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $ids[] = $this->queue->push(self::Q, ['n' => $n], 'order:7');
        }

        $seen = [];
        $this->work([self::Q], function (Task $task) use (&$seen) {
            $seen[] = $task->id;
        });

        $this->assertSame($ids, $seen);
    }

    public function testQueueWithoutHandlerIsNotServed(): void
    {
        $id = $this->queue->push(self::Q, []);

        $worker = new Worker($this->queue, [self::Q], [], null, fn($m) => $this->log[] = $m, true);
        $this->assertSame(0, $worker->run());

        $this->assertSame('queued', $this->jobField($id, 'status'));
        $this->assertStringContainsString('no handler yet: ' . self::Q, $this->log[0]);
    }

    public function testServedQueueWithoutRuleIsRefusedAtStart(): void
    {
        $setup = new TestSetup([self::Q => $this->setup->rules()[self::Q]]);
        $queue = new \diCore\Queue\PgQueue($this->pdo, $setup->policy(), $this->db, $setup);
        $handler = $this->createStub(Handler::class);

        $this->expectExceptionMessage('No rule in Setup::rules() for queue(s): ' . TestSetup::SLOW);
        (new Worker($queue, [self::Q, TestSetup::SLOW], [self::Q => $handler, TestSetup::SLOW => $handler], null, fn() => null, true))->run();
    }

    public function testHandlerRunsUnderStatementLimitsAndOpenTransactionIsRolledBack(): void
    {
        $id = $this->queue->push(self::Q, []);
        $seen = null;

        $this->work([self::Q], function () use (&$seen) {
            $seen = $this->pdo->query('SHOW statement_timeout')->fetchColumn();
            // The handler opened a transaction and failed without rolling it back.
            $this->pdo->exec('BEGIN');
            $this->pdo->query('SELECT id FROM ' . TestSetup::TABLE . ' WHERE id > 0 FOR UPDATE')->fetchAll();
            throw new \RuntimeException('mid-transaction');
        });

        $this->assertSame('24s', $seen, '4/5 of the queue timeout (30 s)');
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame('0', $this->pdo->query('SHOW statement_timeout')->fetchColumn(), 'the limit is lifted');
        $this->assertSame('queued', $this->jobField($id, 'status'), 'the outcome is written outside the abandoned transaction');
        $this->assertTrue((bool) array_filter($this->log, fn($m) => str_contains($m, 'rolled back')));
        // A retry is not reported; an unclosed transaction is a code bug, reported at once.
        $this->assertSame([self::Q . ': the handler left a transaction open'], $this->setup->reportedMessages());
    }

    public function testCoreTransactionLeftOpenIsRolledBackWithItsCounter(): void
    {
        $this->queue->push(self::Q, []);

        $this->work([self::Q], function () {
            $this->db->startTransaction();
            $this->queue->push(self::Q, ['inside' => 1]);
            throw new \RuntimeException('mid-transaction');
        });

        $count = fn(string $field) => (int) $this->pdo->query(
            'SELECT count(*) FROM ' . TestSetup::TABLE . " WHERE payload->>'$field' = '1'"
        )->fetchColumn();
        $this->assertSame(0, (int) $this->db->getTransactionNestingLevel(), 'the core counter is reset');
        $this->assertSame(0, $count('inside'));

        // Core transactions are atomic again: a rollback really rolls back.
        $this->db->startTransaction();
        $this->queue->push(self::Q, ['after' => 1]);
        $this->db->rollbackTransaction();
        $this->assertSame(0, $count('after'));
    }

    public function testCoreTransactionClosedPastTheCoreDoesNotKillTheWorker(): void
    {
        // Opened through the core, committed past it: PDO::rollBack() then throws "no active
        // transaction"; the outcome must still be written and the counter reset.
        $id = $this->queue->push(self::Q, []);

        $this->work([self::Q], function () {
            $this->db->startTransaction();
            $this->pdo->exec('COMMIT');
        });

        $this->assertSame('done', $this->jobField($id, 'status'));
        $this->assertSame(0, (int) $this->db->getTransactionNestingLevel());
    }

    public function testAFailingReportDoesNotLoseTheOutcome(): void
    {
        $setup = new class extends TestSetup {
            public function report(\Throwable|string $error, ?Task $task = null, array $context = []): void
            {
                throw new \RuntimeException('sentry is down');
            }
        };
        $this->queue = new \diCore\Queue\PgQueue($this->pdo, $setup->policy(), $this->db, $setup);
        $id = $this->queue->push(TestSetup::SINGLE, []);

        $this->work([TestSetup::SINGLE], function () {
            $this->pdo->exec('BEGIN');
            throw new \RuntimeException('getMe failed');
        });

        $this->assertSame('failed', $this->jobField($id, 'status'));
        $this->assertCount(2, array_filter($this->log, fn($m) => $m === 'report failed: RuntimeException: sentry is down'));
    }

    public function testDeadConnectionKeepsTheHandlerErrorInTheLog(): void
    {
        $id = $this->queue->push(self::Q, []);
        $db = self::openQueueDb('di_core_queue_worker_dead')->getDb();
        $pdo = $db->getLink();
        $pid = (int) $pdo->query('SELECT pg_backend_pid()')->fetchColumn();
        $queue = new \diCore\Queue\PgQueue($pdo, $this->setup->policy(), $db, $this->setup);
        $handler = new class ($this->pdo, $pid) implements Handler {
            public function __construct(private \PDO $killer, private int $pid)
            {
            }

            public function handle(Task $task): void
            {
                $this->killer->query("SELECT pg_terminate_backend($this->pid)");
                usleep(100000);
                throw new \RuntimeException('upstream refused');
            }
        };
        $worker = new Worker($queue, [self::Q], [self::Q => $handler], null, function ($m) {
            $this->log[] = $m;
        }, true);

        try {
            $worker->run();
            $this->fail('a dead connection must end the process');
        } catch (\PDOException $e) {
            $this->assertTrue(
                (bool) array_filter($this->log, fn($m) => str_contains($m, 'RuntimeException: upstream refused')),
                implode("\n", $this->log)
            );
            $this->assertSame('running', $this->jobField($id, 'status'), 'it comes back as interrupted');
        }
    }

    public function testATakenOverRowGetsNoOutcomeAndNoReport(): void
    {
        $t = TestSetup::TABLE;
        $id = $this->queue->push(TestSetup::SINGLE, []);

        // Another run took the row (its lock expired): the attempt counter moved on.
        $this->work([TestSetup::SINGLE], function () use ($t, $id) {
            $this->pdo->exec("UPDATE $t SET attempts = attempts + 1 WHERE id = $id");
            throw new \RuntimeException('late');
        });

        $this->assertSame('running', $this->jobField($id, 'status'), 'the other run owns the outcome');
        $this->assertSame([], $this->setup->reports);
        $this->assertTrue(
            (bool) array_filter($this->log, fn($m) => str_contains($m, 'taken over by another run')),
            implode("\n", $this->log)
        );
    }

    public function testWorkerSleepsThroughForeignNotifications(): void
    {
        // The channel is shared: a slow worker must not wake for every fast task.
        $worker = new Worker($this->queue, [TestSetup::SLOW], [], null, fn() => null);
        $wait = new \ReflectionMethod(Worker::class, 'waitForOwn');
        $this->queue->listen();
        $other = $this->otherQueue();

        $other->push(self::Q, []);
        $started = microtime(true);
        $this->assertSame([], $wait->invoke($worker, 0.3, [TestSetup::SLOW], false));
        $this->assertGreaterThanOrEqual(0.25, microtime(true) - $started, 'a foreign notification did not wake it');

        $other->push(TestSetup::SLOW, []);
        $started = microtime(true);
        $this->assertSame([TestSetup::SLOW], $wait->invoke($worker, 5.0, [TestSetup::SLOW], false));
        $this->assertLessThan(1, microtime(true) - $started);
    }

    public function testMailIsSentBetweenTasksInPortions(): void
    {
        // A zero budget – a letter per pass: the rest on the next rounds, without waiting for
        // the minute pass, and the worker does not exit while letters are left.
        $mail = new TestMailPump($this->db, [true, true, true], 0);
        $this->work([self::Q, Worker::MAIL], fn() => null, $mail);

        $this->assertSame(0, $mail->left());
        $this->assertCount(3, array_filter($this->log, fn($m) => $m === 'mail: sent 1'));
        $this->assertStringContainsString(', mail', $this->log[0]);
    }

    public function testSlowMailFailureEndsThePass(): void
    {
        // SMTP that accepts and keeps silent: the pass stops instead of waiting on every letter.
        $log = [];
        $pump = new TestMailPump($this->db, [false, true], 3.0, 0.05, 0.1, function ($m) use (&$log) {
            $log[] = $m;
        });

        $this->assertSame([0, false], $pump->run(), 'nothing went, the next pass is in a minute');
        $this->assertContains('mail: slow failure, SMTP looks unavailable; next pass in a minute', $log);
        $this->assertSame(1, $pump->left(), 'the second letter waits for the next pass');
    }

    public function testMailPassIsSkippedWhileAnotherProcessSends(): void
    {
        $other = self::openQueueDb('di_core_queue_mail_lock')->getDb();
        $this->assertTrue($other->acquireNamedLock(TestSetup::MAIL_LOCK, 0));

        try {
            $pump = new TestMailPump($this->db, [true]);
            $this->assertSame([0, false], $pump->run());
            $this->assertSame(1, $pump->left());
        } finally {
            $other->releaseNamedLock(TestSetup::MAIL_LOCK);
        }
    }

    public function testSigtermFinishesTheCurrentTaskAndExits(): void
    {
        $id = $this->queue->push(self::Q, []);
        $proc = proc_open($this->fixture('sigterm'), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $pid = proc_get_status($proc)['pid'];

        // Wait for the task to be taken, and send SIGTERM in the middle of it.
        $deadline = microtime(true) + 5;
        while ($this->jobField($id, 'status') !== 'running' && microtime(true) < $deadline) {
            usleep(50000);
        }
        posix_kill($pid, SIGTERM);

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $code = proc_close($proc);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('interrupted mid-task', $output, 'the signal came mid-task, not after');
        $this->assertSame('done', $this->jobField($id, 'status'), 'the current task is finished');
        $this->assertStringContainsString('stop: 1 task(s)', $output);
    }

    public function testTimeoutKillsTheProcessAndLeavesTheTaskLocked(): void
    {
        $id = $this->queue->push(self::Q, []);
        $started = microtime(true);

        exec(implode(' ', array_map('escapeshellarg', $this->fixture('timeout'))) . ' 2>&1', $output, $code);

        $this->assertSame(1, $code, implode("\n", $output));
        $this->assertLessThan(4, microtime(true) - $started, 'the handler sleeps 10 s – the process left on the 1 s timeout');
        $this->assertStringContainsString(self::Q . "#$id: timeout", implode("\n", $output));
        // The task is not released: until the lock expires (2 × timeout) a second run is impossible.
        $this->assertSame('running', $this->jobField($id, 'status'));
        $this->assertNull($this->queue->reserve([self::Q]));
    }

    /** @return string[] argv of fixtures/worker.php */
    private function fixture(string $mode): array
    {
        // The consumer's Composer autoloader – whichever loaded this test.
        $loader = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();

        return [
            PHP_BINARY,
            __DIR__ . '/fixtures/worker.php',
            dirname($loader, 2) . '/autoload.php',
            (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
            $mode,
        ];
    }
}

/**
 * MailPump over a script instead of SMTP: each element is one letter – true is sent, false
 * fails after $failSeconds.
 */
final class TestMailPump extends MailPump
{
    public function __construct(
        \diDB $db,
        private array $letters,
        float $budget = MailPump::BUDGET_SECONDS,
        float $slowFailure = MailPump::SLOW_FAILURE,
        private float $failSeconds = 0,
        ?callable $log = null
    ) {
        parent::__construct($db, $budget, $log, $slowFailure, TestSetup::MAIL_LOCK);
    }

    public function left(): int
    {
        return count($this->letters);
    }

    protected function pending(): bool
    {
        return (bool) $this->letters;
    }

    protected function sendOne(): bool
    {
        $ok = array_shift($this->letters);

        if (!$ok) {
            usleep((int) ($this->failSeconds * 1e6));
        }

        return $ok;
    }
}
