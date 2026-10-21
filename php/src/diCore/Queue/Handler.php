<?php

namespace diCore\Queue;

/**
 * A queue handler. Returned without an exception – the task is done; RetryLater or any
 * exception – a retry by the policy; PermanentFailure – failed at once.
 *
 * The exception text goes as is to the worker journal and to job.error: no personal data
 * (names, emails, file contents) and no tokens in it. An exception carrying such content
 * (a Postgres DETAIL, an external API answer) is wrapped by the handler into its own – with
 * ids, without the content.
 */
interface Handler
{
    public function handle(Task $task): void;
}
