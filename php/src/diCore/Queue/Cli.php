<?php

namespace diCore\Queue;

use diCore\Database\Connection;

/**
 * Entry points of the queue processes. A project's script is a thin wrapper (CLI guard,
 * autoload, cliHelper.php, then `exit(Cli::worker());`), so its systemd units keep their paths.
 * Lines go to stderr: on prod journald collects them.
 */
class Cli
{
    /**
     * worker.php --queues=<group>|<queue,queue> [--until-idle]
     *
     * @param array|null $options parsed options (tests); null – getopt()
     */
    public static function worker(?array $options = null, ?Setup $setup = null): int
    {
        $setup ??= Setup::basicCreate();
        $groups = $setup->groups();
        $log = static::logger('worker');
        $options ??= getopt('', ['queues:', 'until-idle', 'help']);

        $help = "Usage: php scripts/cli/worker.php --queues=<group>|<queue,queue> [--until-idle]\n";
        foreach ($groups as $group => $list) {
            $help .= "  $group – " . implode(', ', $list) . "\n";
        }
        $help .= "  --until-idle – exit when there is nothing to do\n";

        if (isset($options['help']) || !isset($options['queues']) || !is_string($options['queues'])) {
            fwrite(isset($options['help']) ? STDOUT : STDERR, $help);

            return isset($options['help']) ? 0 : 1;
        }

        $queues = $groups[$options['queues']]
            ?? array_values(array_filter(array_map('trim', explode(',', $options['queues']))));
        $unknown = array_diff($queues, $setup->queues(), [Worker::MAIL]);

        if (!$queues || $unknown) {
            fwrite(STDERR, 'Unknown queue: ' . implode(', ', $unknown ?: ['(empty)']) . "\n" . $help);

            return 1;
        }

        try {
            $db = Connection::get()->getDb();
            $queue = QueueFactory::pgQueueClass()::create(null, $setup);
            $worker = new Worker(
                $queue,
                $queues,
                // The queue's own policy: a handler judges the last attempt by it.
                $setup->handlers($queue->getPolicy(), $log),
                in_array(Worker::MAIL, $queues, true)
                    ? new MailPump($db, MailPump::BUDGET_SECONDS, $log, MailPump::SLOW_FAILURE, $setup::MAIL_LOCK)
                    : null,
                $log,
                isset($options['until-idle'])
            );

            return $worker->run();
        } catch (\Throwable $e) {
            // A DB or code error outside a task: the process exits, systemd starts a new one.
            $log('fatal: ' . get_class($e) . ': ' . $e->getMessage());
            $setup->report($e, null, ['queue' => $options['queues']]);

            return 1;
        }
    }

    /** scheduler.php [--once] */
    public static function scheduler(): int
    {
        $options = getopt('', ['once', 'help']);

        if (isset($options['help'])) {
            echo "Usage: php scripts/cli/scheduler.php [--once]\n" .
                "  --once – run every task once and exit\n";

            return 0;
        }

        $setup = Setup::basicCreate();
        $log = static::logger('scheduler');

        try {
            return (new Scheduler(
                Connection::get()->getDb(),
                $setup->schedule($log),
                $log,
                isset($options['once']),
                $setup
            ))->run();
        } catch (\Throwable $e) {
            $log('fatal: ' . get_class($e) . ': ' . $e->getMessage());
            $setup->report($e);

            return 1;
        }
    }

    /** @return \Closure(string): void */
    protected static function logger(string $process): \Closure
    {
        return function (string $message) use ($process) {
            fwrite(STDERR, date('Y-m-d H:i:s') . " [$process] $message\n");
        };
    }
}
