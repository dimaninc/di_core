<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2016
 * Time: 14:43
 */

namespace diCore\Tool;

abstract class SimpleContainer
{
	public static $names = [];
	public static $titles = [];
	public static $descriptions = [];

	public static function name($id)
	{
		return isset(static::$names[$id])
			? static::$names[$id]
			: null;
	}

	public static function title($id)
	{
		return isset(static::$titles[$id])
			? static::$titles[$id]
			: null;
	}

	public static function description($id)
	{
		return isset(static::$descriptions[$id])
			? static::$descriptions[$id]
			: null;
	}

	public static function id($name)
	{
		if (isInteger($name))
		{
			return isset(static::$names[$name])
				? (int)$name
				: null;
		}

		$id = array_search($name, static::$names);

		if ($id === false)
		{
			$id = defined("static::$name")
				? constant("static::$name")
				: null;
		}

		return $id;
	}

	public static function idByTitle($title)
	{
		$id = array_search($title, static::$titles) ?: null;

		return $id;
	}

	public static function getCollection()
	{
		$ar = [];

		foreach (static::$names as $id => $name)
		{
			$ar[] = [
				'id' => $id,
				'name' => static::name($id),
				'title' => static::title($id),
				'description' => static::description($id),
			];
		}

		return $ar;
	}
}