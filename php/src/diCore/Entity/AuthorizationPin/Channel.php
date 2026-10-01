<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Tool\SimpleContainer;

/**
 * How the code reaches its target. Delivery itself is the project's job; the channel
 * is stored so a confirmation says how it happened.
 */
class Channel extends SimpleContainer
{
    const email = 1;
    const sms = 2;
    const call = 3;
    const telegram = 4;
    const max = 5;

    public static $names = [
        self::email => 'email',
        self::sms => 'sms',
        self::call => 'call',
        self::telegram => 'telegram',
        self::max => 'max',
    ];

    public static $titles = [
        self::email => 'Email',
        self::sms => 'SMS',
        self::call => 'Звонок',
        self::telegram => 'Telegram',
        self::max => 'MAX',
    ];
}
