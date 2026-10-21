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

    /**
     * A queue on its own connection – for an entry point without the CMS (webhook intake). There
     * diLib is not loaded, so pass the project's Setup: Setup::basicCreate() can't find it, and a
     * project's PgQueue subclass isn't found either (a project with one builds it itself).
     */
    public static function forPdo(\PDO $pdo, ?Setup $setup = null): Queue
    {
        return static::pgQueueClass()::forPdo($pdo, $setup);
    }

    /** @return class-string<PgQueue> */
    public static function pgQueueClass(): string
    {
        // diLib lives outside the psr-4 autoload and is loaded by the CMS bootstrap only.
        return class_exists(\diLib::class, false) ? \diLib::getChildClass(PgQueue::class) : PgQueue::class;
    }
}
