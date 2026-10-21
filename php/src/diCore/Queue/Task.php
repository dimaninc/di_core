<?php

namespace diCore\Queue;

/**
 * A reserved task: a row of the job table. Not final: a project with a source of its own
 * (PgQueue subclass) adds its fields in a subclass.
 */
class Task
{
    public function __construct(
        public readonly string $queue,
        public readonly int $id,
        public readonly array $payload,
        // Number of the current run, from 1.
        public readonly int $attempts,
        // The previous run did not finish (the worker died or hit the timeout): the handler
        // decides whether to repeat – an external call may have happened already.
        public readonly bool $interrupted,
        public readonly ?string $orderKey = null
    ) {
    }
}
