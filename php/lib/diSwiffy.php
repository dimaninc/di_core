<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.09.2015
 * Time: 18:10
 */

class diSwiffy
{
	const dimensionsRegEx = "/<div id=\"swiffycontainer\" style=\"width: (\d+)px; height: (\d+)px\">/i";

	public static function normalizeFilename($filename)
	{
		return preg_replace('/\?.*$|#.*/', '', $filename);
	}

	public static function is($filename)
	{
		return strtolower(get_file_ext(self::normalizeFilename($filename))) == "html";
	}

	public static function getDimensions($filename)
	{
		if (preg_match(self::dimensionsRegEx, file_get_contents(self::normalizeFilename($filename)), $regs))
		{
			return array($regs[1], $regs[2], diImage::TYPE_SWIFFY);
		}
		else
		{
			return array(0, 0, diImage::TYPE_HTML5);
		}
	}

	public static function getHtml($filename, $w = null, $h = null)
	{
		if ($w === null || $h === null)
		{
			list($w, $h) = self::getDimensions(diPaths::fileSystem() . $filename);
		}

		if (!$w && !$h)
		{
			$w = $h = "100%";
		}

		if (is_numeric($w))
		{
			$w = $w . "px";
		}

		if (is_numeric($h))
		{
			$h = $h . "px";
		}

		return "<iframe src=\"$filename\" style=\"width: {$w}; height: {$h}; border: 0;\"></iframe>";
	}
}