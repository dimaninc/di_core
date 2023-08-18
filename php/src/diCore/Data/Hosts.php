<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.09.2016
 * Time: 11:46
 */

namespace diCore\Data;

use diCore\Helper\StringHelper;

class Hosts
{
    public static $searchBots = [
        'google.com',
        'google.ru',
        'yandex.ru',
        'ya.ru',
        'search.live.com',
        'rambler.ru',
        'mail.ru',
        'nigma.ru',
        'yahoo.com',
        'aport.ru',
        'search.ukr.net',
        'msn.com',
        'badoo.com',
    ];

    public static function isSearchBot($ip = null)
    {
        $ip = $ip ?: get_user_ip();
        $host = $ip ? @gethostbyaddr($ip) : null;

        if (!$host) {
            return false;
        }

        foreach (Hosts::$searchBots as $bot) {
            if (StringHelper::endsWith($host, $bot)) {
                return true;
            }
        }

        return false;
    }
}
