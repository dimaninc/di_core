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
				//'possible_get_params' => [],
			],
			'href' => 'Ссылка',
			'user' => 'Текстовый раздел',
		],

		'en' => [
			'home' => [
				'title' => 'Homepage',
				'logged_in' => false,
				//'possible_get_params' => [],
			],
			'href' => 'Link',
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
			self::$cachedTypes = extend(static::$basicTypes[$language], static::getTypes());

			foreach (self::$cachedTypes as $type => &$settings) {
				if (!is_array($settings)) {
					$settings = [
						'title' => $settings,
					];
				}

				$settings = extend([
					'title' => null,
					'logged_in' => false,
					'possible_get_params' => [],
				], $settings);
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
