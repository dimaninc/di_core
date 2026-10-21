<?php

namespace diCore\Queue;

/**
 * The queue for business code (controllers, handlers): pushing only. Which store is behind
 * it (Postgres now – PgQueue) the code does not know; the instance comes from
 * QueueFactory::create(). Reserving and outcomes belong to the worker and the scheduler.
 */
interface Queue
{
    /**
     * May be called inside a transaction: the task goes out with the rest of the write or not
     * at all. With $orderKey a push inside a transaction holds the key until its commit, so
     * other pushes of that key wait and the run order is the commit order.
     *
     * @param string|null $orderKey tasks of one key run strictly in push order (e.g.
     *   'order:<id>'). One key – one queue: a worker does not see a holder in another queue
     *   and sleeps until its idle poll (PgQueue::secondsUntilDue()).
     * @return int task id
     */
    public function push(string $queue, array $payload, ?string $orderKey = null, int $delaySeconds = 0): int;
}
