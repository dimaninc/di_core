<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 11.12.2017
 * Time: 16:01
 */

namespace diCore\Entity\MailPlan;

use diCore\Tool\SimpleContainer;

class Mode extends SimpleContainer
{
    const standard = 1;
    const test = 2;

    public static $names = [
        self::standard => 'standard',
        self::test => 'test',
    ];

    public static $titles = [
        self::standard => 'Standard',
        self::test => 'Test',
    ];
}
