<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.03.2017
 * Time: 15:44
 */

namespace diCore\Tool;

use diCore\Entity\Localization\Model;
use diCore\Base\CMS;

class Localization
{
	const DEFAULT_LANGUAGE = 'ru';

	protected static $cache = [];

	protected static function getCurrentLanguage()
	{
		/** @var $Z CMS */
		global $Z;

		$language = !empty($Z)
			? $Z->getLanguage()
			: (\diRequest::request("language") ?: \diRequest::request("l") ?: static::DEFAULT_LANGUAGE);

		return $language;
	}

	protected static function checkLanguage($language)
	{
		if (!in_array($language, \diCurrentCMS::$possibleLanguages))
		{
			$language = \diCurrentCMS::getBrowserLanguage();
		}

		return $language;
	}

	// todo: cache this in php-file
	public static function preCache()
	{
		if (self::$cache)
		{
			return false;
		}

		/** @var \diCore\Entity\Localization\Collection $locals */
		$locals = \diCollection::create(\diTypes::localization);
		$locals->orderByName();
		/** @var Model $l */
		foreach ($locals as $l)
		{
			self::$cache[strtolower($l->getName())] = $l;
		}

		return true;
	}

	public static function resetCache()
	{
		self::$cache = [];
	}

	/**
	 * @param $token
	 * @return Model
	 * @throws \Exception
	 */
	protected static function getModel($token)
	{
		$token = strtolower($token);

		return isset(self::$cache[$token])
			? self::$cache[$token]
			: \diModel::create(\diTypes::localization);
	}

	/**
	 * @param null|string $token    if null, the whole collection for chosen language returned
	 * @param string $language
	 * @param string $default
	 *
	 * @return array|null|string
	 */
	public static function get($token = null, $language = null, $default = null)
	{
		self::preCache();

		$language = self::checkLanguage($language ?: static::getCurrentLanguage());

		$field = \diModel::getLocalizedFieldName('value', $language);

		if ($token === null)
		{
			$ar = [];

			/**
			 * @var string $key
			 * @var Model $m
			 */
			foreach (self::$cache as $key => $m)
			{
				$ar[$key] = $m->get($field);
			}

			return $ar;
		}
		else
		{
			$m = self::getModel($token);

			return $m->exists()
				? $m->get($field)
				: ($default !== null ? $default : $token);
		}
	}

	public static function getSignUpError($code)
	{
		$name = \diSignUpErrors::name($code);

		return $name
			? static::get("sign_up_error_" . strtolower($name))
			: "Sign up error #" . $code;
	}

	public static function getAuthError($code)
	{
		$name = \diAuthErrors::name($code);

		return $name
			? static::get("auth_error_" . strtolower($name))
			: "Auth error #" . $code;
	}
}