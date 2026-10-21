<?php

namespace diCore\Queue;

use diCore\Entity\MailQueue\Collection as Letters;
use diCore\Tool\Mail\Queue as MailQueue;

/**
 * Sending the core's mail_queue from a worker (Worker::MAIL in its queues): letters get
 * there from the core itself, there is no task in job. Delivery is the core's
 * (Tool\Mail\Sender of the project). Under a named lock: the core has none, and two
 * processes would send one letter twice.
 *
 * The same process serves other queues, so a pass is bounded:
 * - by a time budget: letters one by one, past the budget – exit with "more left", the next
 *   portion goes on the worker's next round, between tasks;
 * - by a slow failure: a letter did not go and the attempt took SLOW_FAILURE seconds or
 *   more – SMTP is down or hangs; the pass stops until the next one (Worker::MAIL_EVERY),
 *   or every letter would wait for the SMTP timeout and the other queues for all of them.
 *   A quick failure (address refused) does not stop the pass.
 *
 * Neither bound interrupts a letter in flight: one send can hold the worker for the SMTP
 * client's own timeout (PHPMailer's default is 300 s). Keep the project's Sender timeout at
 * a few seconds, or put Worker::MAIL into a group whose queues can wait that long. Not an
 * alarm: the core hides a letter (visible = 0) before sending it, so a process killed
 * mid-session leaves it hidden and not marked sent – nobody knows whether it went, and it
 * is not retried.
 *
 * The core does not retry: a letter that did not go stays in mail_queue hidden (visible = 0)
 * and is seen on the mail page of the admin.
 */
class MailPump
{
    const BUDGET_SECONDS = 3.0;
    const SLOW_FAILURE = 2.0;

    /** @var callable(string): void|null */
    private $log;

    public function __construct(
        private \diDB $db,
        private float $budget = self::BUDGET_SECONDS,
        ?callable $log = null,
        private float $slowFailure = self::SLOW_FAILURE,
        private string $lock = Setup::MAIL_LOCK
    ) {
        $this->log = $log;
    }

    /**
     * @return array{0: int, 1: bool} [letters sent, letters left for the next round].
     *   [0, false] without sending – when another process sends.
     */
    public function run(): array
    {
        if (!$this->db->acquireNamedLock($this->lock, 0)) {
            return [0, false];
        }

        try {
            $started = microtime(true);
            $sent = 0;
            $tried = 0;

            while ($this->pending()) {
                // At least one letter per pass – with a zero budget too.
                if ($tried && microtime(true) - $started >= $this->budget) {
                    return [$sent, true];
                }

                $tried++;
                $before = microtime(true);

                if ($this->sendOne()) {
                    $sent++;
                } elseif (microtime(true) - $before >= $this->slowFailure) {
                    $this->log('mail: slow failure, SMTP looks unavailable; next pass in a minute');

                    return [$sent, false];
                }
            }

            return [$sent, false];
        } finally {
            $this->db->releaseNamedLock($this->lock);
        }
    }

    protected function pending(): bool
    {
        return Letters::createActual()->count() > 0;
    }

    protected function sendOne(): bool
    {
        return MailQueue::basicCreate()->sendAllSafe(1) > 0;
    }

    private function log(string $message): void
    {
        if ($this->log) {
            ($this->log)($message);
        }
    }
}
