<?php

namespace diCore\Queue;

/**
 * A long-lived worker (Cli::worker()).
 *
 * - Waits on LISTEN/NOTIFY rather than polling: an insert into job wakes it at once (other
 *   queues' notifications do not); the fallback poll is at the nearest run_at, at least once
 *   in IDLE_WAIT_MAX seconds.
 * - The run timeout is SIGALRM: the whole process exits, the task stays running until its
 *   lock expires (twice the timeout) and is issued again as interrupted. The signal fires
 *   only between opcodes and does not interrupt a blocking C call, so there is no parallel
 *   second run while every such call has its own bound: DB queries – statement_timeout and
 *   lock_timeout for the run (PgQueue::limitStatements()), network – the client's timeout;
 *   the handler's network timeouts must add up to less than the queue's timeout (Setup::rules()).
 * - SIGTERM / SIGINT – finish the current task and exit.
 * - Exits by itself after MAX_TASKS tasks, MAX_SECONDS of work or MAX_MEMORY of memory:
 *   systemd (Restart=always) starts a new process – insurance against leaks in PHP. Between
 *   tasks only: give the process a memory_limit below the unit's MemoryMax, or a leak inside
 *   a task is a SIGKILL with no line in the journal.
 */
class Worker
{
    // A pseudo-queue in a worker's list: send the core's mail_queue (MailPump).
    const MAIL = 'mail';

    const MAX_TASKS = 1000;
    const MAX_SECONDS = 3600;
    const MAX_MEMORY = 128 * 1024 * 1024;

    // Fallback poll in case a notification is lost.
    const IDLE_WAIT_MAX = 30;
    // Work exists but cannot be issued (it waits for an earlier task of its key): don't spin.
    const IDLE_WAIT_MIN = 1;
    // A mail pass without a notification too: a letter may have come while the process was busy.
    const MAIL_EVERY = 60;

    private bool $stop = false;
    private ?Task $current = null;
    /** @var callable(string): void */
    private $log;

    /**
     * @param string[] $queues the process's queues (a Setup::groups() group or a list),
     *   Worker::MAIL among them – mail through $mail
     * @param Handler[] $handlers queue => handler; a queue without one is not served
     * @param bool $untilIdle exit when there is no work (tests, a manual run)
     */
    public function __construct(
        private PgQueue $queue,
        private array $queues,
        private array $handlers,
        private ?MailPump $mail,
        callable $log,
        private bool $untilIdle = false
    ) {
        $this->log = $log;
    }

    public function run(): int
    {
        $served = array_values(array_filter($this->queues, fn($q) => isset($this->handlers[$q])));
        $mail = $this->mail && in_array(self::MAIL, $this->queues, true);
        $noHandler = array_values(array_diff($this->queues, $served, [self::MAIL]));

        // A served queue without a rule would throw in every reserve, and systemd would
        // restart the process every few seconds with nothing clearer than this in the journal.
        $policy = $this->queue->getPolicy();
        if ($noRule = array_values(array_filter($served, fn($q) => !$policy->has($q)))) {
            throw new \InvalidArgumentException('No rule in Setup::rules() for queue(s): ' . implode(', ', $noRule));
        }

        $this->log(
            'start: queues ' . ($served ? implode(', ', $served) : '-') .
                ($mail ? ', mail' : '') .
                ($noHandler ? '; no handler yet: ' . implode(', ', $noHandler) : '')
        );

        $this->installSignals();
        $this->queue->listen();

        $started = time();
        $done = 0;
        $mailPending = $mail;
        $mailAt = 0;

        while (!$this->stop) {
            if ($mail && ($mailPending || time() - $mailAt >= self::MAIL_EVERY)) {
                // A pass is bounded by its budget (MailPump): letters left – the next portion on
                // the next round, between tasks, so piled-up mail does not hold the queues.
                [$sent, $mailPending] = $this->mail->run();

                if ($sent) {
                    $this->log("mail: sent $sent");
                }

                $mailAt = time();
            }

            $task = $served ? $this->queue->reserve($served) : null;

            if ($task) {
                $this->process($task);
                $done++;
            } elseif ($this->untilIdle && !$mailPending) {
                break;
            } elseif (!$mailPending) {
                $due = $served ? $this->queue->secondsUntilDue($served) : null;
                $wait = $due === null ? self::IDLE_WAIT_MAX : min(max($due, self::IDLE_WAIT_MIN), self::IDLE_WAIT_MAX);

                if (in_array(self::MAIL, $this->waitForOwn($wait, $served, $mail), true)) {
                    $mailPending = true;
                }
            }

            if ($reason = $this->limitReached($started, $done)) {
                $this->log("restart: $reason");
                break;
            }
        }

        $this->log("stop: $done task(s)");

        return 0;
    }

    private function process(Task $task): void
    {
        $policy = $this->queue->getPolicy();
        $max = $policy->attempts($task->queue);
        $name = "{$task->queue}#{$task->id} (attempt {$task->attempts}" . ($task->interrupted ? ', interrupted' : '') . ')';

        // Abandoned on its last attempt: the reserve has already counted a new one.
        if ($task->attempts > $max) {
            $this->queue->fail($task, 'Attempts exhausted' . ($task->interrupted ? ': last run was interrupted' : ''));
            $this->log("$name: failed, attempts exhausted");
            $this->report("{$task->queue}: attempts exhausted" . ($task->interrupted ? ', last run was interrupted' : ''), $task);

            return;
        }

        $timeout = $policy->timeout($task->queue);
        $this->current = $task;
        $this->queue->limitStatements($timeout);
        pcntl_alarm($timeout);
        $e = null;

        try {
            $this->handlers[$task->queue]->handle($task);
        } catch (\Throwable $e) {
        } finally {
            // The timer goes off before the outcome is written: firing on that UPDATE, it would
            // leave a cleanly failed run running, to come back as interrupted.
            pcntl_alarm(0);
            $this->current = null;
        }

        $leftOpen = $this->queue->endTask();

        if ($leftOpen) {
            $this->log("$name: the handler left a transaction open, rolled back");
        }

        if (!$e) {
            if (!$this->queue->complete($task)) {
                $this->log("$name: done, but the row was taken over by another run");
            }
        } elseif ($e instanceof PermanentFailure) {
            // Not reported: the handler decided this is an outcome, not a fault; the text is in job.error.
            $this->queue->fail($task, $e->getMessage());
            $this->log("$name: failed: {$e->getMessage()}");
        } else {
            $error = get_class($e) . ': ' . $e->getMessage();

            // Retries by the policy are normal (network, limits): only the final failure is reported.
            if ($task->attempts >= $max) {
                $this->queue->fail($task, $error);
                $this->log("$name: failed after $max attempts: $error");
                $this->report($e, $task);
            } else {
                $delay = $policy->delay($task->queue, $task->attempts, $e instanceof RetryLater ? $e->atLeast : 0);
                $this->queue->retry($task, $delay, $error);
                $this->log("$name: retry in {$delay}s: $error");
            }
        }

        if ($leftOpen) {
            // A code bug, reported at once – not only after the last attempt.
            $this->report("{$task->queue}: the handler left a transaction open", $task);
        }
    }

    /**
     * After the outcome is written, never instead of it: a slow monitoring endpoint must not
     * delay the record, a failing one must not lose it or kill the worker.
     */
    private function report(\Throwable|string $error, Task $task): void
    {
        try {
            $this->queue->getSetup()->report($error, $task);
        } catch (\Throwable $e) {
            $this->log('report failed: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Wait up to $seconds, waking only for the process's own queues: the channel is shared,
     * and a slow worker would otherwise wake for every task of a fast one. Returns its own
     * queues among the arrived notifications (empty – the time ran out or a signal came).
     */
    private function waitForOwn(float $seconds, array $served, bool $mail): array
    {
        $own = $mail ? array_merge($served, [self::MAIL]) : $served;
        $deadline = microtime(true) + $seconds;

        do {
            $left = (int) ceil(($deadline - microtime(true)) * 1000);
            $woke = $this->queue->wait(max(0, $left));
            $mine = array_values(array_intersect($woke, $own));
        } while (!$this->stop && $woke && !$mine && microtime(true) < $deadline);

        return $mine;
    }

    private function installSignals(): void
    {
        pcntl_async_signals(true);

        $stop = function () {
            $this->stop = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);

        pcntl_signal(SIGALRM, function () {
            $task = $this->current;
            $this->log(
                ($task ? "{$task->queue}#{$task->id}" : 'task') .
                    ': timeout, exiting; it runs again as interrupted after its lock expires'
            );

            exit(1);
        });
    }

    private function limitReached(int $started, int $done): ?string
    {
        if ($done >= self::MAX_TASKS) {
            return "$done tasks";
        }

        if (time() - $started >= self::MAX_SECONDS) {
            return 'uptime';
        }

        if (memory_get_usage(true) >= self::MAX_MEMORY) {
            return 'memory';
        }

        return null;
    }

    private function log(string $message): void
    {
        ($this->log)($message);
    }
}
