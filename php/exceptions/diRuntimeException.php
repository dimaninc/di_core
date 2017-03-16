<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 15:54
 */

diLib::incInterface("diException");

class diRuntimeException extends \RuntimeException implements diException
{
} 