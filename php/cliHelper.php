<?php

use diCore\Data\Config;

if (empty($_SERVER['HTTP_HOST']) && isset($_SERVER['HOSTNAME'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HOSTNAME'];
}

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__)));

    if (($x = strpos($_SERVER['DOCUMENT_ROOT'], '/_core/php')) !== false) {
        $_SERVER['DOCUMENT_ROOT'] = substr($_SERVER['DOCUMENT_ROOT'], 0, $x);

        if (is_file($autoload = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
            require $autoload;
        }
    } else {
        if (($x = strpos($_SERVER['DOCUMENT_ROOT'], '/vendor/dimaninc/di_core/php')) !== false) {
            $_SERVER['DOCUMENT_ROOT'] = substr($_SERVER['DOCUMENT_ROOT'], 0, $x);

            if (is_dir($_SERVER['DOCUMENT_ROOT'] . '/htdocs')) {
                $_SERVER['DOCUMENT_ROOT'] .= '/htdocs';
                $beyond = true;
            } else {
                $beyond = false;
            }

            $up = $beyond
                ? '/..'
                : '';

            require $_SERVER['DOCUMENT_ROOT'] . $beyond . '/vendor/autoload.php';;
        } else {
            throw new \Exception('Unknown location: ' . __FILE__);
        }
    }
}

require dirname(__FILE__) . '/functions.php';
require Config::getConfigurationFolder() . '_cfg/common.php';

if (empty($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = Config::getMainPort();
}

$_GET = \diRequest::convertFromCommandLine();
