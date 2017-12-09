<?php

namespace diCore\Payment\Tinkoff;

use diCore\Tool\Logger;

class Debug
{
    public static function trace($arg = null, $die = false)
    {
        if (!$arg) $arg = '';
        $arg = print_r($arg, true);
        echo '<pre>' . $arg . '</pre>';
        if ($die) die();
    }

    public static function log($arg = null, $die = false)
    {
        if (!$arg) return false;
        if ( ! is_string($arg)) {
            $arg = print_r($arg, true);
        }
        Logger::getInstance()->log($arg, 'Tinkoff', '-tinkoff');
        if ($die) die();
        return true;
    }
}