<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Handler;
use diCore\Queue\LazyHandler;
use diCore\Queue\Task;
use PHPUnit\Framework\TestCase;

/**
 * Built at the first task, once; a failure to build is that task's failure, not the worker's.
 */
final class LazyHandlerTest extends TestCase
{
    private function task(): Task
    {
        return new Task('q', 1, [], 1, false);
    }

    public function testBuiltOnceAtTheFirstTask(): void
    {
        $built = 0;
        $handled = 0;
        $lazy = new LazyHandler(function () use (&$built, &$handled) {
            $built++;

            return new class ($handled) implements Handler {
                public function __construct(private int &$handled)
                {
                }

                public function handle(Task $task): void
                {
                    $this->handled++;
                }
            };
        });

        $this->assertSame(0, $built, 'nothing is built before a task');
        $lazy->handle($this->task());
        $lazy->handle($this->task());
        $this->assertSame([1, 2], [$built, $handled]);
    }

    public function testAFailureToBuildIsTheTasksFailure(): void
    {
        $lazy = new LazyHandler(fn() => throw new \RuntimeException('no API key'));

        $this->expectExceptionMessage('no API key');
        $lazy->handle($this->task());
    }
}
