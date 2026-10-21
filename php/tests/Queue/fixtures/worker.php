<?php
// A worker in a process of its own for WorkerTest: php worker.php <autoload> <document root> <mode>
//   sigterm – no --until-idle, the handler works 0.5 s; the test sends SIGTERM mid-task and
//             expects the task done and exit code 0;
//   timeout – a 1 s timeout and a handler sleeping 10 s; expects exit code 1 by SIGALRM with
//             the task left running.
[, $autoload, $documentRoot, $mode] = $argv;
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
require $autoload;
require __DIR__ . '/../../../lib/diLib.php';
require __DIR__ . '/../../../functions.php';
require __DIR__ . '/../TestSetup.php';
require __DIR__ . '/../QueueTestDb.php';

use diCore\Queue\Handler;
use diCore\Queue\PgQueue;
use diCore\Queue\Task;
use diCore\Queue\Worker;
use diCore\Tests\Queue\QueueTestDb;
use diCore\Tests\Queue\TestSetup;

$probe = new class {
    use QueueTestDb;
};
$db = $probe::openQueueDb('di_core_queue_fixture')->getDb();
$setup = new TestSetup(
    $mode === 'timeout' ? [TestSetup::FAST => ['attempts' => 3, 'delays' => [1], 'timeout' => 1]] : null
);

$handler = $mode === 'timeout'
    ? new class implements Handler {
        public function handle(Task $task): void
        {
            sleep(10);
        }
    }
    : new class implements Handler {
        public function handle(Task $task): void
        {
            fwrite(STDOUT, "handling {$task->id}\n");
            // A signal interrupts sleep – sleep on to the end, as a long network call. Woke up
            // noticeably early – the signal came in the middle of the task (the test checks it).
            $until = microtime(true) + 0.5;
            while (($left = $until - microtime(true)) > 0) {
                usleep((int) ($left * 1e6));

                if ($until - microtime(true) > 0.01) {
                    fwrite(STDOUT, "interrupted mid-task\n");
                }
            }
        }
    };

$worker = new Worker(
    new PgQueue($db->getLink(), $setup->policy(), $db, $setup),
    [TestSetup::FAST],
    [TestSetup::FAST => $handler],
    null,
    fn($m) => fwrite(STDOUT, "$m\n"),
    $mode === 'timeout'
);

exit($worker->run());
