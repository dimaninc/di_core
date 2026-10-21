<?php

namespace diCore\Queue;

use diCore\Database\Connection;

/**
 * The queue on Postgres: the job table (sql/postgres/job.sql), its name and the notify
 * channel come from Setup.
 *
 * Reserving is one UPDATE ... FROM (SELECT ... FOR UPDATE SKIP LOCKED): two workers never
 * take the same row. Order within a key: a row is not issued while the same order_key has an
 * earlier queued or running one – including one waiting for its retry. failed does not hold
 * the order: what failed for good must not stop the key forever.
 *
 * A running row with an expired locked_until is abandoned (the worker died or hit the
 * timeout): it is issued again marked interrupted.
 *
 * Business code pushes through the Queue interface (QueueFactory); the rest is for the worker
 * and the scheduler. A project with a source of its own (another table) subclasses this as
 * `<Namespace>\Queue\PgQueue` and overrides reserve(), secondsUntilDue() and finish().
 */
class PgQueue implements Queue
{
    // The error text in the task row is for diagnosis, not a log store.
    const ERROR_MAX_LENGTH = 2000;

    protected Setup $setup;

    /**
     * @param \diDB|null $db the core wrapper over the same connection: endTask() resets its
     *   transaction nesting counter together with the transaction itself
     */
    public function __construct(
        protected \PDO $pdo,
        protected Policy $policy,
        protected ?\diDB $db = null,
        ?Setup $setup = null
    ) {
        $this->setup = $setup ?? Setup::basicCreate();
    }

    public static function create(?Policy $policy = null, ?Setup $setup = null): static
    {
        $setup ??= Setup::basicCreate();
        $db = Connection::get()->getDb();

        return new static($db->getLink(), $policy ?? $setup->policy(), $db, $setup);
    }

    public static function forPdo(\PDO $pdo, ?Setup $setup = null): static
    {
        $setup ??= Setup::basicCreate();

        return new static($pdo, $setup->policy(), null, $setup);
    }

    public function getPolicy(): Policy
    {
        return $this->policy;
    }

    public function getSetup(): Setup
    {
        return $this->setup;
    }

    public function push(string $queue, array $payload, ?string $orderKey = null, int $delaySeconds = 0): int
    {
        if (!in_array($queue, $this->setup->queues(), true)) {
            throw new \InvalidArgumentException("Unknown job queue '$queue'");
        }

        $insert = function () use ($queue, $payload, $orderKey, $delaySeconds) {
            $st = $this->pdo->prepare(
                "INSERT INTO {$this->table()} (queue, order_key, payload, run_at) " .
                    "VALUES (?, ?, ?::jsonb, now() + ? * interval '1 second') RETURNING id"
            );
            $st->execute([
                $queue,
                $orderKey,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                max(0, $delaySeconds),
            ]);

            return (int) $st->fetchColumn();
        };

        return $orderKey === null ? $insert() : $this->withOrderLock($orderKey, $insert);
    }

    /** The next task of the listed queues or null. */
    public function reserve(array $queues): ?Task
    {
        $jobQueues = $this->jobQueues($queues);

        return $jobQueues ? $this->reserveJob($jobQueues) : null;
    }

    /**
     * Bound the handler's queries for the run: SIGALRM does not interrupt a blocking C call
     * (a query, a lock wait), and without these limits a hung query would outlive the timeout
     * and the task lock – a second worker would then issue the task again while the first one
     * still runs. The limit is 4/5 of the queue's timeout.
     */
    public function limitStatements(int $timeoutSeconds): void
    {
        $ms = max(1000, intdiv($timeoutSeconds * 1000 * 4, 5));
        $this->pdo->exec("SET statement_timeout = $ms");
        $this->pdo->exec("SET lock_timeout = $ms");
    }

    /**
     * After a run: roll back a transaction the handler left open (the next reserve would
     * otherwise run inside it, never commit and hold the rows) and lift the limits. true –
     * there was one.
     */
    public function endTask(): bool
    {
        $open = false;

        // Through the core first, down to zero: a raw ROLLBACK would leave its nesting counter
        // as it was, and the next startTransaction() in this process would make a SAVEPOINT
        // outside a transaction – every query committing by itself, a rollback rolling back nothing.
        while ($this->db && $this->db->getTransactionNestingLevel() > 0) {
            $this->db->rollbackTransaction();
            $open = true;
        }

        // The rest is a BEGIN past the core: pdo_pgsql asks libpq for inTransaction and sees it.
        if ($this->pdo->inTransaction()) {
            $this->pdo->exec('ROLLBACK');
            $open = true;
        }

        $this->pdo->exec('RESET statement_timeout');
        $this->pdo->exec('RESET lock_timeout');

        return $open;
    }

    /** Done. false – another run has taken the row over (the record is left alone). */
    public function complete(Task $task): bool
    {
        return $this->finish($task, "status = 'done', locked_until = NULL, error = NULL, finished_at = now()");
    }

    /** Repeat not sooner than in $delaySeconds. */
    public function retry(Task $task, int $delaySeconds, string $error): bool
    {
        return $this->finish(
            $task,
            "status = 'queued', locked_until = NULL, error = ?, run_at = now() + ? * interval '1 second'",
            [$this->cutError($error), max(0, $delaySeconds)]
        );
    }

    public function fail(Task $task, string $error): bool
    {
        return $this->finish(
            $task,
            "status = 'failed', locked_until = NULL, error = ?, finished_at = now()",
            [$this->cutError($error)]
        );
    }

    /**
     * In how many seconds work appears in the queues: the nearest run_at of the waiting or
     * locked_until of the taken. 0 – there is some already, null – nothing is expected. Rows
     * held by an earlier one of the same key don't count, or the worker would see "work is
     * there" and poll every second while the holder waits for its retry. Their time is the
     * holder's, but only while the holder is in the same queues: a holder of another queue is
     * not selected, and the worker sleeps until Worker::IDLE_WAIT_MAX (its completion sends no
     * NOTIFY). Hence the rule: one order_key – one queue.
     */
    public function secondsUntilDue(array $queues): ?float
    {
        $jobQueues = $this->jobQueues($queues);

        if (!$jobQueues) {
            return null;
        }

        $t = $this->table();
        $st = $this->pdo->prepare(
            "SELECT EXTRACT(EPOCH FROM (
  min(CASE WHEN u.status = 'queued' THEN u.run_at ELSE u.locked_until END) - now()
)) FROM $t u
WHERE u.queue = ANY(?::text[]) AND u.status IN ('queued', 'running')
  AND (u.order_key IS NULL OR NOT EXISTS (
    SELECT 1 FROM $t e WHERE e.order_key = u.order_key AND e.id < u.id AND e.status IN ('queued', 'running')
  ))"
        );
        $st->execute([$this->pgArray($jobQueues)]);
        $seconds = $st->fetchColumn();

        return $seconds === null || $seconds === false ? null : max(0.0, (float) $seconds);
    }

    public function listen(): void
    {
        $this->pdo->exec('LISTEN ' . $this->setup::CHANNEL);
    }

    /**
     * Wait for a notification up to $milliseconds. Returns the queue names of the arrived
     * notifications (empty – the time ran out or a signal interrupted).
     */
    public function wait(int $milliseconds): array
    {
        $queues = [];
        $timeout = max(0, $milliseconds);

        // PDO::pgsqlGetNotify – PHP 8.3; 8.4 has Pdo\Pgsql::getNotify and deprecates the old name.
        while (($n = $this->pdo->pgsqlGetNotify(\PDO::FETCH_ASSOC, $timeout)) !== false) {
            $queues[] = (string) ($n['payload'] ?? '');
            // The rest that piled up – without waiting.
            $timeout = 0;
        }

        return array_values(array_unique($queues));
    }

    /**
     * Delete finished tasks in batches (the scheduler): done older than $doneDays, failed older
     * than $failedDays – longer, to have time to look into an incident. One big DELETE would
     * hold locks and swell the WAL. Age is run_at: for a finished row that is its last attempt.
     * The queue list is in the query for the (queue, status, run_at) index – without it the
     * index with queue first is useless and the table is scanned whole.
     *
     * @return int deleted rows
     */
    public function purgeFinished(int $doneDays = 7, int $failedDays = 30, int $batch = 1000): int
    {
        $queues = $this->setup->queues();

        if (!$queues) {
            return 0;
        }

        $t = $this->table();
        $st = $this->pdo->prepare(
            "DELETE FROM $t WHERE id IN (
  SELECT id FROM $t
  WHERE queue = ANY(?::text[]) AND ((status = 'done' AND run_at < now() - ? * interval '1 day')
     OR (status = 'failed' AND run_at < now() - ? * interval '1 day'))
  LIMIT ?
)"
        );
        $total = 0;

        do {
            $st->execute([$this->pgArray($queues), $doneDays, $failedDays, $batch]);
            $total += $deleted = $st->rowCount();
        } while ($deleted === $batch);

        return $total;
    }

    protected function table(): string
    {
        return $this->setup::TABLE;
    }

    /**
     * Insert under the order key lock until commit. "By id" order holds only if the ids of
     * one key become visible in commit order: an id is given at insert, a row becomes visible
     * at commit, and an insert in a long transaction would otherwise be overtaken by one
     * pushed later in autocommit. Outside a transaction – a short one of its own: without it
     * the lock would be released before the insert.
     */
    protected function withOrderLock(string $key, \Closure $insert): mixed
    {
        if ($this->pdo->inTransaction()) {
            $this->lockOrderKey($key);

            return $insert();
        }

        $this->pdo->exec('BEGIN');

        try {
            $this->lockOrderKey($key);
            $result = $insert();
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK');

            throw $e;
        }
    }

    /** The order key lock until the end of the transaction. */
    protected function lockOrderKey(string $orderKey): void
    {
        $st = $this->pdo->prepare('SELECT pg_advisory_xact_lock(?, hashtext(?))');
        $st->execute([$this->setup::ORDER_LOCK_CLASS, $orderKey]);
    }

    protected function reserveJob(array $jobQueues): ?Task
    {
        // The lock depends on the queue: a CASE from the policy, names are known ones only.
        $lock = 'CASE job.queue';
        foreach ($jobQueues as $queue) {
            $lock .= " WHEN '$queue' THEN " . $this->policy->lockSeconds($queue);
        }
        $lock .= ' END';

        $t = $this->table();
        $st = $this->pdo->prepare(
            "WITH c AS (
  SELECT j.id, j.status = 'running' AS interrupted
  FROM $t j
  WHERE j.queue = ANY(?::text[])
    AND ((j.status = 'queued' AND j.run_at <= now()) OR (j.status = 'running' AND j.locked_until < now()))
    AND (j.order_key IS NULL OR NOT EXISTS (
      SELECT 1 FROM $t e
      WHERE e.order_key = j.order_key AND e.id < j.id AND e.status IN ('queued', 'running')
    ))
  ORDER BY j.run_at, j.id
  LIMIT 1
  FOR UPDATE OF j SKIP LOCKED
)
UPDATE $t job SET status = 'running', attempts = job.attempts + 1,
  locked_until = now() + ($lock) * interval '1 second'
FROM c WHERE job.id = c.id
RETURNING job.id, job.queue, job.order_key, job.payload, job.attempts, c.interrupted"
        );
        $st->execute([$this->pgArray($jobQueues)]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ? $this->newTask($r) : null;
    }

    /** @param array<string, mixed> $row a reserved row (RETURNING of reserveJob()) */
    protected function newTask(array $row): Task
    {
        return new Task(
            $row['queue'],
            (int) $row['id'],
            $this->decode($row['payload']),
            (int) $row['attempts'],
            (bool) $row['interrupted'],
            $row['order_key']
        );
    }

    /**
     * The outcome of a run. The condition on status and attempts: if the row was issued again
     * (this run counted as abandoned), the old run's outcome does not overwrite it.
     */
    protected function finish(Task $task, string $set, array $params = []): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE {$this->table()} SET $set WHERE id = ? AND status = 'running' AND attempts = ?"
        );
        $st->execute(array_merge($params, [$task->id, $task->attempts]));

        return $st->rowCount() === 1;
    }

    protected function jobQueues(array $queues): array
    {
        return array_values(array_intersect($queues, $this->setup->queues()));
    }

    protected function pgArray(array $values): string
    {
        // Only queue names from Setup::queues(): nothing to escape.
        return '{' . implode(',', $values) . '}';
    }

    protected function decode($payload): array
    {
        $data = json_decode((string) $payload, true);

        return is_array($data) ? $data : [];
    }

    protected function cutError(string $error): string
    {
        return mb_substr($error, 0, self::ERROR_MAX_LENGTH);
    }
}
