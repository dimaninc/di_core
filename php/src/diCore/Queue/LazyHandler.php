<?php

namespace diCore\Queue;

/**
 * A handler built at its first task: every worker builds the handlers of all queues
 * (Setup::handlers()), and a misconfigured queue must not bring down the worker of the
 * others. A failure to build is an ordinary task failure (retry, then job.error).
 */
final class LazyHandler implements Handler
{
    private ?Handler $handler = null;

    /** @param \Closure(): Handler $make */
    public function __construct(private \Closure $make)
    {
    }

    public function handle(Task $task): void
    {
        ($this->handler ??= ($this->make)())->handle($task);
    }
}
