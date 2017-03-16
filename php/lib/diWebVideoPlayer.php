<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.12.15
 * Time: 10:34
 */
class diWebVideoPlayer
{
	public static function getFormatRowsHtml($options = [])
	{
		$rows = [];

		$options = extend([
			"getFilenameCallback" => function($format) {},
		], $options);

		foreach (diWebVideoFormats::$extensions as $formatId => $format)
		{
			$file = $options["getFilenameCallback"]($format);

			if (!$file)
			{
				continue;
			}

			$typeAttr = diWebVideoFormats::$videoTagMimeTypes[$formatId]
				? ' type="' . diWebVideoFormats::$videoTagMimeTypes[$formatId] . '"'
				: '';

			$rows[] = '<source src="' . $file . '"' . $typeAttr . '>';
		}

		return join('', $rows);
	}
}