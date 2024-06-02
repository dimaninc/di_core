<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:22
 */

namespace diCore\Tool;

use diCore\Base\CMS;
use diCore\Data\Config;
use diCore\Helper\StringHelper;

class Logger
{
    use \diSingleton;

    const SUB_FOLDER = 'log/debug/';
    const EXTENSION = '.txt';
    const DATE_TIME_FORMAT = '[d.m.Y H:i:s]';
    const CHMOD = 0777;

    const PURPOSE_SIMPLE = 1;
    const PURPOSE_VARIABLE = 2;

    protected $uid;

    protected $logToStdout = false;

    protected function init()
    {
        $this->uid = StringHelper::random(10);
    }

    public function enableLogToStdout()
    {
        $this->logToStdout = true;

        return $this;
    }

    public function disableLogToStdout()
    {
        $this->logToStdout = false;

        return $this;
    }

    public function getFolder()
    {
        return Config::getLogFolder();
    }

    protected function getFilename($purpose, $fnSuffix = '')
    {
        if (CMS::isHardDebug()) {
            $fnSuffix .= '-hard';
        }

        return \diDateTime::format('Y_m_d') . $fnSuffix . static::EXTENSION;
    }

    protected function getFullFilename($purpose, $fnSuffix = '')
    {
        return $this->getFolder() .
            static::SUB_FOLDER .
            $this->getFilename($purpose, $fnSuffix);
    }

    protected function getDateTime($purpose)
    {
        return \diDateTime::format(static::DATE_TIME_FORMAT);
    }

    protected function saveLine($line, $purpose, $fnSuffix = '')
    {
        $fn = $this->getFullFilename($purpose, $fnSuffix);

        $data =
            $this->uid . '> ' . $this->getDateTime($purpose) . ' ' . $line . "\n";

        if ($this->logToStdout) {
            echo $data;
        }

        $f = fopen($fn, 'a');
        fputs($f, $data);
        fclose($f);

        @chmod($fn, static::CHMOD);

        return $this;
    }

    public function log($message, $module = '', $fnSuffix = '')
    {
        if ($module) {
            $module = "[$module] ";
        }

        $this->saveLine($module . $message, self::PURPOSE_SIMPLE, $fnSuffix);

        return $this;
    }

    public function variable()
    {
        $arguments = func_get_args();

        foreach ($arguments as $arg) {
            $this->saveLine(
                print_r($arg, true) ?: var_export($arg, true),
                self::PURPOSE_VARIABLE
            );
        }

        return $this;
    }
}
