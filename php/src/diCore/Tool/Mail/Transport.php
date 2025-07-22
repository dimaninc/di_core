<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 19:21
 */

namespace diCore\Tool\Mail;

use diCore\Tool\SimpleContainer;

class Transport extends SimpleContainer
{
    const SMTP = 1;
    const SENDMAIL = 2;
    const CUSTOM = 10;

    public static $titles = [
        self::SMTP => 'SMTP',
        self::SENDMAIL => 'sendmail',
        self::CUSTOM => 'Custom',
    ];

    public static $names = [
        self::SMTP => 'SMTP',
        self::SENDMAIL => 'SENDMAIL',
        self::CUSTOM => 'CUSTOM',
    ];
}
