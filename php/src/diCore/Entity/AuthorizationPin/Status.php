<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Tool\SimpleContainer;

class Status extends SimpleContainer
{
    const pending = 0;
    const verified = 1;
    const expired = 2;
    const invalidated = 3;

    public static $names = [
        self::pending => 'pending',
        self::verified => 'verified',
        self::expired => 'expired',
        self::invalidated => 'invalidated',
    ];

    public static $titles = [
        self::pending => 'Ожидает',
        self::verified => 'Подтверждён',
        self::expired => 'Истёк',
        self::invalidated => 'Отменён',
    ];
}
