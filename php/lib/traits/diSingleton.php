<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 10.05.2016
 * Time: 11:29
 */

trait diSingleton
{
    protected static $instance;

    final public static function getInstance()
    {
        return isset(static::$instance)
            ? static::$instance
            : (static::$instance = new static());
    }

    private function __construct()
    {
        $this->init();
    }

    protected function init()
    {
    }

    public function __wakeup()
    {
    }

    private function __clone()
    {
    }
}
