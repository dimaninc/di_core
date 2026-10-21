<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Policy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rule shapes are checked when the policy is built: a wrong one would fail late – a zero lock
 * re-issuing a running task, a TypeError after a run killing the worker.
 */
final class PolicyTest extends TestCase
{
    public static function badRules(): array
    {
        $ok = ['attempts' => 3, 'delays' => [5, 30], 'timeout' => 60];

        return [
            'timeout 0' => [['timeout' => 0] + $ok],
            'no delays' => [['delays' => []] + $ok],
            'a delay is not an int' => [['delays' => [5, '30']] + $ok],
            'a negative delay' => [['delays' => [-1]] + $ok],
            'attempts 0' => [['attempts' => 0] + $ok],
            'attempts missing' => [array_diff_key($ok, ['attempts' => 1])],
        ];
    }

    #[DataProvider('badRules')]
    public function testBadRuleIsRefused(array $rule): void
    {
        $this->expectExceptionMessage("Queue 'q': a rule needs attempts >= 1, timeout >= 1");
        new Policy(['q' => $rule]);
    }

    public function testDelays(): void
    {
        $policy = new Policy(['q' => ['attempts' => 5, 'delays' => [5, 30], 'timeout' => 60]]);

        $this->assertSame([5, 30, 30], [$policy->delay('q', 1), $policy->delay('q', 2), $policy->delay('q', 4)], 'the last one repeats');
        $this->assertSame(100, $policy->delay('q', 1, 100), 'Retry-After above the policy wins');
        $this->assertSame(120, $policy->lockSeconds('q'));
    }
}
