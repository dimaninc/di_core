<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.05.2019
 * Time: 12:04
 */

namespace diCore\Payment;

use diCore\Tool\Logger;

abstract class BaseHelper
{
    const testMode = false;

    const login = null;
    const password = null;

    const loginDemo = null;
    const passwordDemo = null;

    const childClassName = 'Settings';
    const system = null;
    const logSuffix = '-payment';

    protected $options = [
        'onSuccessPayment' => null,
    ];

    /**
     * @return $this
     */
    public static function create($options = [])
    {
        $className = \diLib::getChildClass(static::class, static::childClassName);

        $helper = new $className($options);

        return $helper;
    }

    public function __construct($options = [])
    {
        $this->options = extend($this->options, $options);
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, System::name(static::system), static::logSuffix);
    }

    public static function getLogin()
    {
        return static::testMode
            ? static::loginDemo
            : static::login;
    }

    public static function getPassword()
    {
        return static::testMode
            ? static::passwordDemo
            : static::password;
    }
}