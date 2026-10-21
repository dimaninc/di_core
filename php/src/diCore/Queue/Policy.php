<?php

namespace diCore\Queue;

/**
 * Attempts, retry delays and timeouts per queue (rules – see Setup::rules()). An instance,
 * not constants: a test substitutes a timeout without waiting for the real one.
 */
class Policy
{
    /** @param array<string, array{attempts: int, delays: int[], timeout: int}> $rules */
    public function __construct(private array $rules)
    {
        // A wrong shape fails late and badly: timeout 0 disables the alarm and makes the lock
        // zero (a second worker re-issues the running task at once), empty delays make
        // delay() a TypeError after the run – the worker dies with the task left running.
        foreach ($rules as $queue => $rule) {
            $int = fn($v, int $min) => is_int($v) && $v >= $min;
            $delays = $rule['delays'] ?? null;

            if (
                !$int($rule['attempts'] ?? null, 1) ||
                !$int($rule['timeout'] ?? null, 1) ||
                !is_array($delays) ||
                !$delays ||
                array_filter($delays, fn($d) => !$int($d, 0))
            ) {
                throw new \InvalidArgumentException(
                    "Queue '$queue': a rule needs attempts >= 1, timeout >= 1 and a non-empty list of delays >= 0"
                );
            }
        }
    }

    public function has(string $queue): bool
    {
        return isset($this->rules[$queue]);
    }

    public function attempts(string $queue): int
    {
        return $this->rule($queue)['attempts'];
    }

    public function timeout(string $queue): int
    {
        return $this->rule($queue)['timeout'];
    }

    /**
     * How long a task stays locked to its worker. Twice the timeout: a worker past the
     * timeout exits by itself (Worker), so before the lock expires a second run of the same
     * task is impossible.
     */
    public function lockSeconds(string $queue): int
    {
        return $this->timeout($queue) * 2;
    }

    /**
     * Pause before the next attempt after failed attempt number $attempt (from 1). $atLeast –
     * what an external service (Retry-After) or the handler asked for: never wait less.
     */
    public function delay(string $queue, int $attempt, int $atLeast = 0): int
    {
        $delays = $this->rule($queue)['delays'];
        $policy = $delays[min(max($attempt, 1), count($delays)) - 1];

        return max($policy, $atLeast);
    }

    private function rule(string $queue): array
    {
        if (!isset($this->rules[$queue])) {
            throw new \InvalidArgumentException("Unknown queue '$queue'");
        }

        return $this->rules[$queue];
    }
}
