<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.04.2016
 * Time: 18:42
 */
class diWebFonts
{
	const FOLDER = 'assets/fonts/web/';

	public static $titlesExtended = [
		'Arial' => 'Arial (встроенный шрифт)',
		'Georgia' => 'Georgia (встроенный шрифт)',
		'Tahoma' => 'Tahoma (встроенный шрифт)',
		'Times New Roman' => 'Times New Roman (встроенный шрифт)',
		'Verdana' => 'Verdana (встроенный шрифт)',
	];

	public static $files = [
		'Arial' => 'arial.ttf',
		'Georgia' => 'georgia.ttf',
		'Tahoma' => 'tahoma.ttf',
		'Times New Roman' => 'times.ttf',
		'Verdana' => 'verdana.ttf',
	];

	public static function exists($fontTitle)
	{
		return isset(self::$files[$fontTitle]);
	}

	public static function getFileForFont($fontTitle)
	{
		return self::exists($fontTitle)
			? self::FOLDER . self::$files[$fontTitle]
			: null;
	}
}