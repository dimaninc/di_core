<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 12.12.2017
 * Time: 19:44
 */

namespace diCore\Helper;

class Post
{
    const POST_FORM_DATA = 1;
    const POST_RAW = 2;

    const workMode = self::POST_RAW;

    public static function getWorkMode()
    {
        return static::workMode;
    }

    public static function getVar($name, $default = null)
    {
        switch (static::workMode) {
            case self::POST_FORM_DATA:
                return static::getFormData($name, $default);

            case self::POST_RAW:
                return static::getRaw($name, $default);

            default:
                throw new \Exception(
                    'Unsupported work mode: ' . static::workMode
                );
        }
    }

    protected static function getFormData($name, $default = null)
    {
        return \diRequest::post($name, $default);
    }

    protected static function getRaw($name, $default = null)
    {
        return \diRequest::rawPost($name, $default);
    }
}
