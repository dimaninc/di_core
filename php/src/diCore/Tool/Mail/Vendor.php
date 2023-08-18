<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:10
 */

namespace diCore\Tool\Mail;

use diCore\Tool\SimpleContainer;

class Vendor extends SimpleContainer
{
    const google = 1;
    const yandex = 2;
    const microsoft = 3;
    const mailru = 4;
    const masterhost = 5;
    const own = 50;

    public static $titles = [
        self::google => 'Google',
        self::yandex => 'Yandex',
        self::microsoft => 'Microsoft',
        self::mailru => 'Mail.ru',
        self::masterhost => 'Masterhost',
        self::own => 'Own SMTP',
    ];

    public static $names = [
        self::google => 'google',
        self::yandex => 'yandex',
        self::microsoft => 'microsoft',
        self::mailru => 'mailru',
        self::masterhost => 'masterhost',
        self::own => 'own',
    ];

    protected static $smtpHosts = [
        self::google => 'smtp.gmail.com',
        self::yandex => 'smtp.yandex.ru',
        self::microsoft => 'smtp.office365.com',
        self::mailru => 'ssl://smtp.mail.ru',
        self::masterhost => 'smtp.masterhost.ru',
    ];

    protected static $smtpPorts = [
        self::google => [
            true => 587,
            false => 25,
        ],
        self::yandex => [
            true => 25,
            false => 25,
        ],
        self::microsoft => [
            true => 587,
            false => 25,
        ],
        self::mailru => [
            true => 465,
            false => 25,
        ],
        self::masterhost => [
            true => 465,
            false => 25,
        ],
    ];

    public static function smtpHost($id)
    {
        return static::$smtpHosts[$id] ?? null;
    }

    public static function smtpPort($id, $secure)
    {
        return static::$smtpPorts[$id][$secure] ?? null;
    }
}
