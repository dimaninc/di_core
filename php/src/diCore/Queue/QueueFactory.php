<?php

namespace diCore\Queue;

/**
 * The one place business code gets the queue from: a change of the store is an edit here.
 * A project's `<Namespace>\Queue\PgQueue` subclass, if any, is picked up.
 */
class QueueFactory
{
    public static function create(): Queue
    {
        return static::pgQueueClass()::create();
    }

    /** A queue on its own connection – for an entry point without the CMS (webhook intake). */
    public static function forPdo(\PDO $pdo): Queue
    {
        return static::pgQueueClass()::forPdo($pdo);
    }

    /** @return class-string<PgQueue> */
    public static function pgQueueClass(): string
    {
        return \diLib::getChildClass(PgQueue::class);
    }
}
