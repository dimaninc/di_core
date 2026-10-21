<?php

namespace diCore\Queue;

use diCore\Tool\SimpleContainer;

/**
 * Values of job.status – the CHECK in sql/postgres/job.sql holds the same set.
 */
class Status extends SimpleContainer
{
    const queued = 'queued';
    const running = 'running';
    const done = 'done';
    const failed = 'failed';

    public static $names = [
        self::queued => 'queued',
        self::running => 'running',
        self::done => 'done',
        self::failed => 'failed',
    ];

    public static $titles = [
        self::queued => 'В очереди',
        self::running => 'Выполняется',
        self::done => 'Готово',
        self::failed => 'Ошибка',
    ];
}
