<?php

namespace diCore\Queue;

use diCore\Traits\BasicCreate;

/**
 * What a project tells the queue: its queues, their rules, the worker groups, the handlers
 * and the schedule. A project subclasses it as `<Namespace>\Queue\Setup`; `basicCreate()`
 * finds the subclass, so the worker, the scheduler and `QueueFactory` need no wiring.
 *
 * The constants name shared server-side objects. A project that already runs the queue keeps
 * its own values: a channel renamed between the migration and the restart leaves old workers
 * deaf until their idle poll, a lock renamed lets an old and a new process run side by side.
 */
class Setup
{
    use BasicCreate;

    const TABLE = 'job';
    // LISTEN/NOTIFY channel: the same name goes to the trigger (sql/postgres/job_notify.sql).
    const CHANNEL = 'di_queue';
    // First half of the order key lock, pg_advisory_xact_lock(int4, int4). That form never
    // meets the bigint locks of acquireNamedLock(); a project's own int4 locks need other classes.
    const ORDER_LOCK_CLASS = 4271;
    const SCHEDULER_LOCK = 'di_queue_scheduler';
    const MAIL_LOCK = 'di_queue_mail';

    /**
     * Values of job.queue – the same list as the CHECK of the project's table. A queue missing
     * here is refused by push() and never reserved.
     *
     * @return string[]
     */
    public function queues(): array
    {
        return [];
    }

    /**
     * Queue => ['attempts' => total runs, 'delays' => pause before a retry after the Nth
     * failure (the last one repeats), 'timeout' => seconds per run]. The network timeouts of
     * one run must add up to less than the queue's timeout: SIGALRM does not interrupt a
     * blocking call, and a run outliving its lock (2 × timeout) would go on beside its retry.
     *
     * @return array<string, array{attempts: int, delays: int[], timeout: int}>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Named lists of queues for `worker.php --queues=<group>`, e.g. 'fast' (someone waits for
     * the answer) and 'slow' (renders, uploads). Worker::MAIL in a group makes that worker send
     * the core's mail_queue.
     *
     * @return array<string, string[]>
     */
    public function groups(): array
    {
        return [];
    }

    /**
     * Queue => handler. A queue without one is not reserved – its tasks wait. Wrap a handler
     * with heavy construction in LazyHandler: every worker builds all of them.
     *
     * @param \Closure(string): void $log
     * @return array<string, Handler>
     */
    public function handlers(Policy $policy, \Closure $log): array
    {
        return [];
    }

    /**
     * Periodic tasks: name => [interval in seconds, task]. An interval is at most
     * Scheduler::MAX_SECONDS. A task must be safe to repeat: after a restart all run at once.
     *
     * @param \Closure(string): void $log
     * @return array<string, array{0: int, 1: callable}>
     */
    public function schedule(\Closure $log): array
    {
        return [];
    }

    /**
     * Monitoring hook (Sentry and the like); the journal line is written anyway. Called for
     * a task's final failure (not for a retry, not for PermanentFailure), a transaction a
     * handler left open, a failed scheduler task and a fatal error of a process.
     *
     * @param array<string, scalar> $context
     */
    public function report(\Throwable|string $error, ?Task $task = null, array $context = []): void
    {
    }

    public function policy(): Policy
    {
        return new Policy($this->rules());
    }
}
