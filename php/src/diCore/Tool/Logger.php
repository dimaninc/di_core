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
use diCore\Data\Environment;
use diCore\Helper\StringHelper;

class Logger
{
    use \diSingleton;

    const SUB_FOLDER = 'log/debug/';
    const EXTENSION = '.txt';
    // native php does not support "u", that's why we use our own %мс3%
    const DATE_TIME_FORMAT = '[d.m.Y H:i:s.%мс3%]';
    const CHMOD = 0777;

    const PURPOSE_SIMPLE = 1;
    const PURPOSE_VARIABLE = 2;

    protected $uid;

    protected $logToStdout = false;

    protected $startTimestamp = null;
    protected $speedLines = [];
    protected $isFirstLine = true;

    protected function init()
    {
        $this->uid = StringHelper::random(10);
        $this->startTimestamp = utime();
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

    protected function makeLine($line, $purpose)
    {
        $this->isFirstLine = false;

        return "$this->uid> {$this->getDateTime($purpose)} $line\n";
    }

    protected function printLine($line, $purpose, $fnSuffix = '')
    {
        return $this->storeLine(
            $this->makeLine($line, $purpose),
            $purpose,
            $fnSuffix
        );
    }

    protected function storeLine($line, $purpose, $fnSuffix = '')
    {
        $fn = $this->getFullFilename($purpose, $fnSuffix);

        if ($this->logToStdout) {
            echo $line;
        }

        $f = fopen($fn, 'a');
        fputs($f, $line);
        fclose($f);

        @chmod($fn, static::CHMOD);

        $this->isFirstLine = false;

        return $this;
    }

    public function log($message, $module = '', $fnSuffix = '')
    {
        if ($module) {
            $module = "[$module] ";
        }

        $this->printLine($module . $message, self::PURPOSE_SIMPLE, $fnSuffix);

        return $this;
    }

    public function variable(...$arguments)
    {
        foreach ($arguments as $arg) {
            $this->printLine(
                print_r($arg, true) ?: var_export($arg, true),
                self::PURPOSE_VARIABLE
            );
        }

        return $this;
    }

    public function speed($message, $module = '')
    {
        if ($module) {
            $module = "[$module] ";
        }

        $line = "$module$message";

        if ($this->isFirstLine) {
            $ip = \diRequest::getRemoteIp();
            $domain = \diRequest::domain();
            $line = "$ip->$domain $line";
        }

        if (Environment::shouldLogOnlySlowSpeed()) {
            $this->speedLines[] = $this->makeLine($line, self::PURPOSE_SIMPLE);
        } else {
            $this->printLine($line, self::PURPOSE_SIMPLE, '-speed');
        }

        return $this;
    }

    public function speedFinish($message, $module = '')
    {
        $timeDifference = utime() - ($this->startTimestamp ?? 0);

        if ($module) {
            $module = "[$module] ";
        }

        if (
            Environment::shouldLogOnlySlowSpeed() &&
            $timeDifference >= Environment::getSlowSpeedValue()
        ) {
            foreach ($this->speedLines as $l) {
                $this->storeLine($l, self::PURPOSE_SIMPLE, '-speed');
            }

            $message = "{$timeDifference}s: $message";
            $this->printLine($module . $message, self::PURPOSE_SIMPLE, '-speed');
        }

        return $this;
    }
}
