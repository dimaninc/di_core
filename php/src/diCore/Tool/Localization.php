<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.03.2017
 * Time: 15:44
 */

namespace diCore\Tool;

use diCore\Data\Config;
use diCore\Entity\Localization\Collection;
use diCore\Entity\Localization\Model;
use diCore\Base\CMS;
use diCore\Helper\FileSystemHelper;
use diCore\Traits\BasicCreate;

class Localization
{
	use BasicCreate;

	const USE_FILE_CACHE = false;
	const DEFAULT_LANGUAGE = 'ru';
	const CACHE_FOLDER = '_cfg/cache/localization/';

	protected static $cacheLanguage;
	protected static $cache = [];

	protected static function getCurrentLanguage()
	{
		/** @var $Z CMS */
		global $Z;

		$language = !empty($Z)
			? $Z->getLanguage()
			: (\diRequest::request('language') ?: \diRequest::request('l') ?: static::DEFAULT_LANGUAGE);

		return $language;
	}

	protected static function checkLanguage($language)
	{
		if (!in_array($language, \diCurrentCMS::$possibleLanguages)) {
			$language = \diCurrentCMS::getBrowserLanguage();
		}

		return $language;
	}

	protected static function getCacheFilename($language)
	{
		return Config::__getPhpFolder() . static::CACHE_FOLDER . $language . '.php';
	}

	public static function getCollection()
    {
        return Collection::create()->orderByName();
    }

	public static function getAllStrings($language)
    {
        $locals = static::getCollection();
        $cache = [];

        /** @var Model $l */
        foreach ($locals as $l) {
            $cache[strtolower($l->getName())] = $l->getValueForLanguage($language);
        }

        return $cache;
    }

	public static function createCache()
	{
		FileSystemHelper::createTree(Config::__getPhpFolder(), static::CACHE_FOLDER, 0777);

		foreach (\diCurrentCMS::$possibleLanguages as $language) {
			$file = '<?php ';

			foreach (static::getAllStrings($language) as $name => $value) {
				$file .= sprintf('self::$cache[\'%s\']=%s;',
					$name,
					\diModel::escapeValueForFile($value)
				);
			}

			file_put_contents(static::getCacheFilename($language), $file);
			chmod(static::getCacheFilename($language), 0777);
		}
	}

	public static function preCache($language)
	{
		if (self::$cache && self::$cacheLanguage === $language) {
			return false;
		}

		if (static::USE_FILE_CACHE) {
            if (!is_file(static::getCacheFilename($language))) {
                static::createCache();
            }

            include static::getCacheFilename($language);
        } else {
            self::$cache = static::getAllStrings($language);
        }

		self::$cacheLanguage = $language;

		return true;
	}

	public static function resetCache()
	{
		self::$cache = [];
		self::$cacheLanguage = null;
	}

	/**
	 * @param $token
	 * @return Model
	 * @throws \Exception
	 */
	protected static function getModel($token)
	{
		$col = Collection::create()->filterByName($token);

		return $col->getFirstItem();
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
		$language = self::checkLanguage($language ?: static::getCurrentLanguage());

		self::preCache($language);

		if ($token === null) {
			return self::$cache;
		} else {
			return isset(self::$cache[$token])
				? self::$cache[$token]
				: ($default !== null ? $default : $token);
		}
	}

	public static function getSignUpError($code)
	{
		$name = \diSignUpErrors::name($code);

		return $name
			? static::get('sign_up_error_' . strtolower($name))
			: 'Sign up error #' . $code;
	}

	public static function getAuthError($code)
	{
		$name = \diAuthErrors::name($code);

		return $name
			? static::get('auth_error_' . strtolower($name))
			: 'Auth error #' . $code;
	}
}