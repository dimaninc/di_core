<?php

namespace diCore\Queue;

/**
 * A temporary failure: retry by the queue's policy, but not sooner than $atLeast seconds
 * (a Retry-After of an external service). Any other handler exception is retried the same way.
 */
class RetryLater extends \RuntimeException
{
    public function __construct(string $reason, public readonly int $atLeast = 0)
    {
        parent::__construct($reason);
    }
}
