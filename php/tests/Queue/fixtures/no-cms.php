<?php
// An entry point without the CMS for QueueFactoryTest: php no-cms.php <autoload>. Only Composer's
// autoload – no diLib; prints the queue class and the queues of its Setup.
[, $autoload] = $argv;
require $autoload;
require __DIR__ . '/../TestSetup.php';

$queue = \diCore\Queue\QueueFactory::forPdo(new \PDO('sqlite::memory:'), new \diCore\Tests\Queue\TestSetup());

echo json_encode([
    'diLib' => class_exists(\diLib::class, false),
    'class' => get_class($queue),
    'queues' => $queue->getSetup()->queues(),
]);
