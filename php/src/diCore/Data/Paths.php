<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 19:42
 */

namespace diCore\Data;

use diCore\Helper\StringHelper;

class Paths
{
	const PROTOCOL = null; // got from $_SERVER
	const DOMAIN = null; // got from $_SERVER

	public static function fileSystem($target = null, $endingSlashNeeded = true, $field = null)
	{
        $subFolder = \diLib::getSubFolder();

        if ($subFolder)
        {
            $subFolder = '/' . $subFolder;
        }

		return \diRequest::server('DOCUMENT_ROOT') . $subFolder . ($endingSlashNeeded ? '/' : '');
	}

	public static function http($target = null, $endingSlashNeeded = true, $field = null)
	{
        $path = '';

        if (\diLib::getSubFolder())
        {
            $path = '' . \diLib::getSubFolder() . '/' . $path;

            $path = $endingSlashNeeded
                ? StringHelper::slash($path, true)
                : StringHelper::unslash($path, true);
        }

		return $path;
	}

	public static function domain()
	{
		return static::DOMAIN ?: \diRequest::domain();
	}

	public static function defaultHttp()
	{
		$protocol = static::PROTOCOL ?: (\diRequest::isHttps() ? 'https' : 'http');

		return $protocol . '://' . static::domain();
	}
}
