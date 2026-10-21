<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Setup;
use diCore\Queue\Task;

/**
 * The queue of the tests: its own table and channel, so a consumer's job table and its
 * workers never see the test tasks. Reports are collected instead of sent.
 */
class TestSetup extends Setup
{
    const TABLE = 'di_test_job';
    // With a capital: LISTEN must quote it (an unquoted name is folded to lower case).
    const CHANNEL = 'di_Test_queue';
    // The test copy of di_queue_notify() (QueueTestDb).
    const NOTIFY_FUNCTION = 'di_test_queue_notify';
    const SCHEDULER_LOCK = 'di_test_queue_scheduler';
    const MAIL_LOCK = 'di_test_queue_mail';

    const FAST = 'fast_q';
    const SLOW = 'slow_q';
    const SINGLE = 'single_q';

    /** @var array<int, array{error: \Throwable|string, task: ?Task, context: array}> */
    public array $reports = [];

    public function __construct(private ?array $rules = null)
    {
    }

    public function queues(): array
    {
        return [self::FAST, self::SLOW, self::SINGLE];
    }

    public function rules(): array
    {
        return $this->rules ?? [
            self::FAST => ['attempts' => 3, 'delays' => [7, 70], 'timeout' => 30],
            self::SLOW => ['attempts' => 5, 'delays' => [5, 30], 'timeout' => 300],
            self::SINGLE => ['attempts' => 1, 'delays' => [7], 'timeout' => 30],
        ];
    }

    public function groups(): array
    {
        return ['fast' => [self::FAST, self::SINGLE], 'slow' => [self::SLOW]];
    }

    public function report(\Throwable|string $error, ?Task $task = null, array $context = []): void
    {
        $this->reports[] = ['error' => $error, 'task' => $task, 'context' => $context];
    }

    /** @return string[] */
    public function reportedMessages(): array
    {
        return array_map(
            fn($r) => $r['error'] instanceof \Throwable ? $r['error']->getMessage() : $r['error'],
            $this->reports
        );
    }
}
