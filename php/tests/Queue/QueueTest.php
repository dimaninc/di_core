<?php

namespace diCore\Tests\Queue;

use diCore\Queue\PgQueue;
use diCore\Queue\Policy;
use diCore\Queue\Queue;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/TestSetup.php';
require_once __DIR__ . '/QueueTestDb.php';

/**
 * PgQueue on a live server: reserving, order within a key, abandoned runs, SKIP LOCKED,
 * NOTIFY, purging. Live: DI_CORE_TEST_DB=postgresql://… (QueueTestDb).
 */
final class QueueTest extends TestCase
{
    use QueueTestDb;

    private const Q = TestSetup::FAST;
    const REQUIRED_FUNCTIONS = [];

    public function testPushReserveComplete(): void
    {
        $id = $this->queue->push(self::Q, ['text' => 'Привет']);
        $task = $this->queue->reserve([self::Q]);

        $this->assertSame($id, $task->id);
        $this->assertSame('Привет', $task->payload['text']);
        $this->assertSame(1, $task->attempts);
        $this->assertFalse($task->interrupted);
        $this->assertNull($this->queue->reserve([self::Q]), 'a taken task is not issued twice');

        $this->assertTrue($this->queue->complete($task));
        $this->assertSame('done', $this->jobField($id, 'status'));
        $this->assertNotNull($this->jobField($id, 'finished_at'));
    }

    public function testRetryWaitsForDelay(): void
    {
        $id = $this->queue->push(self::Q, []);
        $this->assertTrue($this->queue->retry($this->queue->reserve([self::Q]), 60, 'network'));

        $this->assertSame(['queued', 'network'], [$this->jobField($id, 'status'), $this->jobField($id, 'error')]);
        $this->assertNull($this->queue->reserve([self::Q]), 'the retry is not due yet');

        $this->pdo->exec('UPDATE ' . TestSetup::TABLE . " SET run_at = now() WHERE id = $id");
        $again = $this->queue->reserve([self::Q]);
        $this->assertSame([$id, 2], [$again->id, $again->attempts]);
    }

    public function testDelayedPushAndSecondsUntilDue(): void
    {
        $this->assertNull($this->queue->secondsUntilDue([self::Q]), 'nothing is expected');

        $id = $this->queue->push(self::Q, [], null, 120);
        $this->assertNull($this->queue->reserve([self::Q]));
        $this->assertEqualsWithDelta(120, $this->queue->secondsUntilDue([self::Q]), 5);

        $this->pdo->exec('UPDATE ' . TestSetup::TABLE . " SET run_at = now() WHERE id = $id");
        $this->assertSame(0.0, $this->queue->secondsUntilDue([self::Q]));
    }

    public function testOrderHoldsAcrossRetryDelay(): void
    {
        // The first failed and waits for a retry – the second of the same key does not overtake it.
        $a = $this->queue->push(self::Q, [], 'order:1');
        $b = $this->queue->push(self::Q, [], 'order:1');
        $other = $this->queue->push(self::Q, [], 'order:2');

        $first = $this->queue->reserve([self::Q]);
        $this->assertSame($a, $first->id);
        $this->queue->retry($first, 60, 'network');

        $this->assertSame($other, $this->queue->reserve([self::Q])->id, 'another key does not wait');
        $this->assertNull($this->queue->reserve([self::Q]), 'b waits for the retry of a');
        // b is due, but a holds it: the due time is a's retry, not "now" (or the worker would
        // poll every second for the whole minute).
        $this->assertGreaterThan(50, $this->queue->secondsUntilDue([self::Q]));

        $this->pdo->exec('UPDATE ' . TestSetup::TABLE . " SET run_at = now() WHERE id = $a");
        $again = $this->queue->reserve([self::Q]);
        $this->assertSame([$a, 2], [$again->id, $again->attempts]);
        $this->assertNull($this->queue->reserve([self::Q]), 'a is running – b waits');

        $this->queue->complete($again);
        $this->assertSame($b, $this->queue->reserve([self::Q])->id);
    }

    public function testPushInsideTransactionKeepsOrderByCommit(): void
    {
        // A pushes inside a transaction and has not committed; B pushes the same key in
        // autocommit. Without the key lock B would be issued before A.
        $this->pdo->exec('BEGIN');
        $a = $this->queue->push(self::Q, ['n' => 'a'], 'order:tx');

        $other = $this->connect();
        $other->exec("SET lock_timeout = '300ms'");
        $b = new PgQueue($other, new Policy($this->setup->rules()), null, $this->setup);

        try {
            $b->push(self::Q, ['n' => 'b'], 'order:tx');
            $this->fail('a push of the same key must wait for the commit of A');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('lock', $e->getMessage());
        }

        $count = fn(string $n) => (int) $other->query('SELECT count(*) FROM ' . TestSetup::TABLE . " WHERE payload->>'n' = '$n'")->fetchColumn();
        $this->assertSame(0, $count('b'), 'the insert of B rolled back whole');
        $this->assertGreaterThan(0, $b->push(self::Q, ['n' => 'x'], 'order:other'), 'another key does not wait');

        $this->pdo->exec('COMMIT');
        $bId = $b->push(self::Q, ['n' => 'b'], 'order:tx');

        $this->assertSame($a, $this->queue->reserve([self::Q])->id);
        $this->assertGreaterThan($a, $bId);
    }

    public function testFailedDoesNotHoldOrder(): void
    {
        $a = $this->queue->push(self::Q, [], 'order:1');
        $b = $this->queue->push(self::Q, [], 'order:1');

        $this->queue->fail($this->queue->reserve([self::Q]), 'blocked');
        $this->assertSame('failed', $this->jobField($a, 'status'));
        $this->assertSame($b, $this->queue->reserve([self::Q])->id);
    }

    public function testAbandonedRunIsReissuedAsInterrupted(): void
    {
        $id = $this->queue->push(self::Q, []);
        $first = $this->queue->reserve([self::Q]);
        $this->assertNull($this->queue->reserve([self::Q]), 'the lock has not expired');

        $this->pdo->exec('UPDATE ' . TestSetup::TABLE . " SET locked_until = now() - interval '1 second' WHERE id = $id");
        $second = $this->queue->reserve([self::Q]);

        $this->assertSame([$id, 2, true], [$second->id, $second->attempts, $second->interrupted]);
        $this->assertFalse($this->queue->complete($first), 'the abandoned run does not overwrite the new one');
        $this->assertSame('running', $this->jobField($id, 'status'));
        $this->assertTrue($this->queue->complete($second));
    }

    public function testLockedRowIsSkipped(): void
    {
        $a = $this->queue->push(self::Q, []);
        $b = $this->queue->push(self::Q, []);

        // Another connection holds a under a row lock, as a worker in the middle of a reserve.
        $other = $this->connect();
        $other->beginTransaction();
        $other->query('SELECT id FROM ' . TestSetup::TABLE . " WHERE id = $a FOR UPDATE")->fetchAll();

        try {
            $this->assertSame($b, $this->queue->reserve([self::Q])->id);
        } finally {
            $other->rollBack();
        }

        $this->assertSame($a, $this->queue->reserve([self::Q])->id);
    }

    public function testQueuesAreSeparate(): void
    {
        $slow = $this->queue->push(TestSetup::SLOW, []);
        $this->assertNull($this->queue->reserve($this->setup->groups()['fast']));
        $this->assertSame($slow, $this->queue->reserve($this->setup->groups()['slow'])->id);
    }

    public function testStatementLimitsAndOpenTransaction(): void
    {
        $this->queue->limitStatements(30);
        $this->assertSame('24s', $this->pdo->query('SHOW statement_timeout')->fetchColumn());
        $this->assertSame('24s', $this->pdo->query('SHOW lock_timeout')->fetchColumn());

        $this->assertFalse($this->queue->endTask(), 'there was no transaction');
        $this->assertSame('0', $this->pdo->query('SHOW statement_timeout')->fetchColumn());

        $this->pdo->exec('BEGIN');
        $this->assertTrue($this->queue->endTask(), 'an open transaction is rolled back');
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testInsertNotifiesListener(): void
    {
        $listener = $this->otherQueue();
        $listener->listen();
        $this->assertSame([], $listener->wait(0));

        $this->queue->push(self::Q, []);
        $this->queue->push(TestSetup::SLOW, []);

        $woke = $listener->wait(2000);
        sort($woke);
        $this->assertSame([self::Q, TestSetup::SLOW], $woke);
    }

    public function testNotifyFunctionTakesAFixedPayloadForATableWithoutQueue(): void
    {
        // mail_queue has no queue column: the trigger passes 'mail' as its second argument, and
        // the NEW.queue branch must not fail that insert.
        $this->pdo->exec('DROP TABLE IF EXISTS di_test_letter');
        $this->pdo->exec('CREATE TABLE di_test_letter (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY)');
        $this->pdo->exec("CREATE TRIGGER trg__di_test_letter__notify AFTER INSERT ON di_test_letter
            FOR EACH ROW EXECUTE FUNCTION di_queue_notify('" . TestSetup::CHANNEL . "', 'mail')");
        $listener = $this->otherQueue();
        $listener->listen();

        try {
            $this->pdo->exec('INSERT INTO di_test_letter DEFAULT VALUES');
            $this->assertSame(['mail'], $listener->wait(2000));
        } finally {
            $this->pdo->exec('DROP TABLE di_test_letter');
        }
    }

    public function testUnknownQueueIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->queue->push('mail', []);
    }

    public function testPurgeFinished(): void
    {
        $t = TestSetup::TABLE;
        $ids = [];
        foreach (['done' => 8, 'failed' => 31] as $status => $days) {
            foreach ([$days, $days - 2] as $age) {
                $ids["$status-$age"] = $id = $this->queue->push(self::Q, []);
                $this->pdo->exec("UPDATE $t SET status = '$status', run_at = now() - interval '$age days' WHERE id = $id");
            }
        }
        $queued = $this->queue->push(self::Q, []);
        $this->pdo->exec("UPDATE $t SET run_at = now() - interval '90 days' WHERE id = $queued");

        $this->assertSame(2, $this->queue->purgeFinished(7, 30, 1), 'batches of one go on until done');
        $left = $this->pdo->query("SELECT id FROM $t ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame([$ids['done-6'], $ids['failed-29'], $queued], array_map('intval', $left));
    }

    public function testBusinessCodeSeesTheInterface(): void
    {
        $this->assertInstanceOf(Queue::class, PgQueue::forPdo($this->pdo, $this->setup));
    }
}
