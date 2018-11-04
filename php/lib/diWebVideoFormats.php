<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.15
 * Time: 15:18
 */
class diWebVideoFormats
{
	const MP4 = 1;
	const M4V = 2;
	const OGV = 3;
	const WEBM = 4;

	public static $extensions = array(
		self::M4V => "m4v",
		self::OGV => "ogv",
		self::WEBM => "webm",
		self::MP4 => "mp4",
	);

	public static $videoTagMimeTypes = array(
		self::M4V => "video/mp4",
		self::OGV => "video/ogg",
		self::WEBM => "video/webm",
		self::MP4 => "video/mp4",
	);
}