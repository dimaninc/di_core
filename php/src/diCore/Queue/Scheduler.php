<?php

namespace diCore\Queue;

/**
 * Periodic tasks (Cli::scheduler()): the schedule is in code (Setup::schedule()), a long-lived
 * process under systemd. One per database – a named lock (Setup::SCHEDULER_LOCK): a second
 * instance exits. After a restart the tasks run at once, so each must be safe to repeat.
 *
 * A lost DB connection is an exit with an error, not a journal line: there is no reconnect
 * here or in the core, and after a Postgres restart the process would live on doing nothing
 * while its lock on the server is already gone – a second scheduler would start beside it.
 * The sign is not the exception class but a SELECT 1 probe after a task's PDOException:
 * pdo_pgsql reports a broken link now with SQLSTATE 08xxx, now with HY000 and a text.
 * Plus an exit by age (MAX_SECONDS), as the worker: systemd starts a new one.
 *
 * Run times live in the process's memory and the tasks run at once after a start, so an
 * interval is at most MAX_SECONDS: a daily task would otherwise run every hour. Rarer ones
 * need the last run time stored in the database.
 */
class Scheduler
{
    const MAX_SECONDS = 3600;

    private bool $stop = false;
    /** @var callable(string): void */
    private $log;

    /**
     * @param array<string, array{0: int, 1: callable}> $tasks name => [interval in seconds,
     *   task]; the task's result (a number or an array) goes to the log
     * @param bool $once run every task once and exit
     */
    public function __construct(
        private \diDB $db,
        private array $tasks,
        callable $log,
        private bool $once = false,
        private ?Setup $setup = null
    ) {
        foreach ($tasks as $name => [$interval]) {
            if ($interval > self::MAX_SECONDS) {
                throw new \InvalidArgumentException(
                    "Task '$name': interval $interval s is longer than the process lifetime (" . self::MAX_SECONDS . ' s)'
                );
            }
        }

        $this->setup ??= Setup::basicCreate();
        $this->log = $log;
    }

    public function run(): int
    {
        $lock = $this->setup::SCHEDULER_LOCK;

        // acquireNamedLock answers false on a query error too – without this check an
        // unreachable database would read in the journal as "another scheduler holds the lock".
        $this->db->getLink()->query('SELECT 1');

        if (!$this->db->acquireNamedLock($lock, 0)) {
            ($this->log)('another scheduler holds the lock, exiting');

            return 1;
        }

        pcntl_async_signals(true);
        $stop = function () {
            $this->stop = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);

        ($this->log)('start: ' . implode(', ', array_keys($this->tasks)));
        $next = array_fill_keys(array_keys($this->tasks), 0);
        $started = time();

        try {
            while (!$this->stop) {
                foreach ($this->tasks as $name => [$interval, $task]) {
                    if ($this->stop || time() < $next[$name]) {
                        continue;
                    }

                    $e = null;

                    try {
                        $result = $task();
                        ($this->log)("$name: " . json_encode($result, JSON_UNESCAPED_UNICODE));
                    } catch (\Throwable $e) {
                        ($this->log)("$name: " . get_class($e) . ': ' . $e->getMessage());
                    }

                    // Before the probe: inside an aborted transaction SELECT 1 fails too (25P02)
                    // and a live connection would read as dead; an open one would swallow the
                    // writes of every later task until the process exits.
                    if (OpenTransaction::rollBack($this->db->getLink(), $this->db)) {
                        ($this->log)("$name: the task left a transaction open, rolled back");
                        $this->report("$name: the task left a transaction open", $name);
                    }

                    if ($e) {
                        $this->report($e, $name);

                        // A query error (SQL, a constraint, lock_timeout) belongs to one task: it
                        // must not stop the others or loop the process through restarts. Exit
                        // only if the connection itself is dead.
                        if ($e instanceof \PDOException) {
                            $this->db->getLink()->query('SELECT 1');
                        }
                    }

                    $next[$name] = time() + $interval;
                }

                if ($this->once) {
                    break;
                }

                if (time() - $started >= self::MAX_SECONDS) {
                    ($this->log)('restart: uptime');
                    break;
                }

                // By seconds: a stop signal does not wait for the next task.
                $wakeAt = $next ? min($next) : $started + self::MAX_SECONDS;

                while (!$this->stop && time() < $wakeAt && time() - $started < self::MAX_SECONDS) {
                    sleep(1);
                }
            }
        } finally {
            // The connection may be dead – then there is nothing to release, and the release's
            // exception would mask the original one.
            try {
                $this->db->releaseNamedLock($lock);
            } catch (\Throwable $e) {
            }
        }

        ($this->log)('stop');

        return 0;
    }

    /** A failing monitoring hook must not stop the schedule. */
    private function report(\Throwable|string $error, string $name): void
    {
        try {
            $this->setup->report($error, null, ['scheduler_task' => $name]);
        } catch (\Throwable $e) {
            ($this->log)('report failed: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
}
