<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 12.04.2016
 * Time: 11:37
 */

class diContentTypesBasic
{
    protected static $basicTypes = [
        'ru' => [
            'home' => [
                'title' => 'Заглавная страница',
                'logged_in' => false,
                // 'possibleGetParams' => [],
            ],
            'href' => 'Ссылка',
            'reset_password' => 'Сброс пароля',
            'enter_new_password' => 'Введение нового пароля',
            'user' => 'Текстовый раздел',
        ],

        'en' => [
            'home' => [
                'title' => 'Homepage',
                'logged_in' => false,
                // 'possibleGetParams' => [],
            ],
            'href' => 'Link',
            'reset_password' => 'Reset password',
            'enter_new_password' => 'Enter new password',
            'user' => 'Text page',
        ],
    ];

    protected static $types = [];

    private static $cachedTypes = null;

    /**
     * Can be overridden
     *
     * @return array
     */
    protected static function getTypes()
    {
        return static::$types;
    }

    public static function get($language = 'ru')
    {
        if (self::$cachedTypes === null) {
            self::$cachedTypes = extend(
                static::$basicTypes[$language],
                static::getTypes()
            );

            foreach (self::$cachedTypes as $type => &$settings) {
                if (!is_array($settings)) {
                    $settings = [
                        'title' => $settings,
                    ];
                }

                $settings = extend(
                    [
                        'title' => null,
                        'logged_in' => false,
                        'possibleGetParams' => [],
                    ],
                    $settings
                );
            }
        }

        return self::$cachedTypes;
    }

    public static function exists($type)
    {
        return isset(static::get()[$type]);
    }

    public static function getParam($type, $param)
    {
        return static::get()[$type][$param] ?? null;
    }
}
