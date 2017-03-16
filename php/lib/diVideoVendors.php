<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.10.15
 * Time: 14:34
 */
class diVideoVendors
{
	const Own = 0;
	const YouTube = 1;
	const Vimeo = 2;

	public static $titles = [
		self::Own => "Собственное видео",
		self::YouTube => "YouTube",
		self::Vimeo => "Vimeo",
	];

	public static $patterns = [
		self::YouTube => '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?\"\']*).*/',
		self::Vimeo => '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/',
	];

	public static function extractInfoFromEmbed($embed)
	{
		$ar = [
			"vendor" => null,
			"video_uid" => null,
		];

		foreach (self::$patterns as $vendor => $pattern)
		{
			preg_match($pattern, $embed, $r);

			if ($vendor == self::YouTube && isset($r[7]))
			{
				$ar["video_uid"] = $r[7];
				$ar["vendor"] = $vendor;
			}

			if ($vendor == self::Vimeo && isset($r[5]))
			{
				$ar["video_uid"] = $r[5];
				$ar["vendor"] = $vendor;
			}
		}

		return $ar;
	}

	private static function getFile($url)
	{
		return file_get_contents($url);
	}

	private static function getVimeoData($videoUid)
	{
		return unserialize(self::getFile(sprintf("http://vimeo.com/api/v2/video/%s.php", $videoUid)));
	}

	public static function getThumbnail($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf("//img.youtube.com/vi/%s/default.jpg", $videoUid); //http:

			case self::Vimeo:
				$info = self::getVimeoData($videoUid);

				return isset($info[0]["thumbnail_medium"])
					? $info[0]["thumbnail_medium"]
					: null;
		}

		return null;
	}

	public static function getPoster($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf("//img.youtube.com/vi/%s/0.jpg", $videoUid); //http:

			case self::Vimeo:
				$info = self::getVimeoData($videoUid);

				return isset($info[0]["thumbnail_large"])
					? str_replace('http://', 'https://', $info[0]["thumbnail_large"])
					: null;
		}

		return null;
	}

	public static function getTitle($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				$rawData = self::getFile("http://youtube.com/get_video_info?video_id=" . $videoUid);
				if ($rawData)
				{
					parse_str($rawData, $data);

					//"view_count"
					if (isset($data["title"]))
					{
						return $data["title"];
					}
				}
			break;
		}

		return null;
	}

	public static function getLink($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf("https://www.youtube.com/watch?v=%s", $videoUid);

			case self::Vimeo:
				return sprintf("https://vimeo.com/%s", $videoUid);
		}

		return null;
	}

	public static function getEmbedLink($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf("https://www.youtube.com/embed/%s", $videoUid);

			case self::Vimeo:
				return sprintf("https://player.vimeo.com/video/%s", $videoUid);
		}

		return null;
	}
}