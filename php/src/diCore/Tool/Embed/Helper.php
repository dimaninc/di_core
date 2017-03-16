<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.06.2016
 * Time: 23:29
 */

namespace diCore\Tool\Embed;

class Helper
{
	protected static $queryParamsToRemove = [];
	protected static $identifyParams = [];

	public static function getQueryParamsToRemove()
	{
		return static::$queryParamsToRemove;
	}

	public static function is()
	{
		foreach (static::$identifyParams as $name => $value)
		{
			if (\diRequest::get($name) == $value)
			{
				return true;
			}
		}

		return false;
	}
}