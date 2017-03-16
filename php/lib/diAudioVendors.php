<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.03.16
 * Time: 17:34
 */
class diAudioVendors
{
	const Own = 0;
	const MixCloud = 1;
	const SoundCloud = 2;

	public static $titles = array(
		self::Own => "Собственное аудио",
		self::MixCloud => "MixCloud",
		//self::SoundCloud => "SoundCloud",
	);

	public static $patterns = array(
		self::MixCloud => '/mixcloud\.com\/((?!widget)[^\/]+)\/((?!iframe)[^\/]+)\/|mixcloud\.com%2F([^%]+)%2F([^%]+)%2F/',
		//self::SoundCloud => "not implemented yet",
	);

	public static function extractInfoFromEmbed($embed)
	{
		$ar = array(
			"vendor" => null,
			"audio_uid" => null,
		);

		foreach (self::$patterns as $vendor => $pattern)
		{
			preg_match($pattern, $embed, $r);

			if ($vendor == self::MixCloud)
			{
				if (isset($r[1]) && isset($r[2]))
				{
					$ar["audio_uid"] = $r[1] . "/" . $r[2];
					$ar["vendor"] = $vendor;
				}

				if (isset($r[3]) && isset($r[4]))
				{
					$ar["audio_uid"] = $r[3] . "/" . $r[4];
					$ar["vendor"] = $vendor;
				}
			}
		}

		return $ar;
	}

	public static function getLink($vendor, $audioUid)
	{
		switch ($vendor)
		{
			case self::MixCloud:
				return sprintf("https://www.mixcloud.com/%s/", $audioUid);
		}

		return null;
	}

	public static function getEmbedLink($vendor, $audioUid)
	{
		switch ($vendor)
		{
			case self::MixCloud:
				return "https://www.mixcloud.com/widget/iframe/?feed=" . urlencode(self::getLink($vendor, $audioUid)) . "&hide_cover=1&light=1";
		}

		return null;
	}

	public static function getEmbedCode($vendor, $audioUid)
	{
		switch ($vendor)
		{
			case self::MixCloud:
				return sprintf("<iframe width=\"100%%\" height=\"120\" src=\"%s\" frameborder=\"0\"></iframe>", self::getEmbedLink($vendor, $audioUid));
		}

		return null;
	}
}