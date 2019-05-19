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
	const RuTube = 3;

	public static $titles = [
		self::Own => "Собственное видео",
		self::YouTube => "YouTube",
		self::Vimeo => "Vimeo",
        self::RuTube => 'RuTube',
	];

	public static $patterns = [
		self::YouTube => '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?\"\']*).*/',
		self::Vimeo => '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/',
        self::RuTube => '#(https?://)rutube.ru/video/([^/]+)/#',
	];

	public static function extractInfoFromEmbed($embed)
	{
		$ar = [
			'vendor' => null,
			'video_uid' => null,
		];

		foreach (self::$patterns as $vendor => $pattern)
		{
			preg_match($pattern, $embed, $r);

			if ($vendor == self::YouTube && isset($r[7]))
			{
				$ar['video_uid'] = $r[7];
				$ar['vendor'] = $vendor;
			}
			elseif ($vendor == self::Vimeo && isset($r[5]))
			{
				$ar['video_uid'] = $r[5];
				$ar['vendor'] = $vendor;
			}
            elseif ($vendor == self::RuTube && isset($r[2]))
            {
                $ar['video_uid'] = $r[2];
                $ar['vendor'] = $vendor;
            }
		}

		return $ar;
	}

	private static function getFile($url)
	{
		return @file_get_contents($url);
	}

	private static function getVimeoData($videoUid)
	{
		return unserialize(self::getFile(sprintf("http://vimeo.com/api/v2/video/%s.php", $videoUid)));
	}

	private static function getRuTubeData($videoUid)
    {
        return (array)json_decode(self::getFile(sprintf('https://rutube.ru/api/video/%s?format=json', $videoUid)));
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
					? str_replace('http://', 'https://', $info[0]["thumbnail_medium"])
					: null;

            case self::RuTube:
                return self::getPoster($vendor, $videoUid);
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

            case self::RuTube:
                $info = self::getRuTubeData($videoUid);

                return isset($info['thumbnail_url'])
                    ? $info['thumbnail_url']
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

			case self::Vimeo:
				$info = self::getVimeoData($videoUid);

				return isset($info[0]['title'])
					? $info[0]['title']
					: null;

            case self::RuTube:
                $info = self::getRuTubeData($videoUid);

                return isset($info['title'])
                    ? $info['title']
                    : null;
		}

		return null;
	}

	public static function getDescription($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				break;

			case self::Vimeo:
				$info = self::getVimeoData($videoUid);

				return isset($info[0]['description'])
					? $info[0]['description']
					: null;

            case self::RuTube:
                $info = self::getRuTubeData($videoUid);

                return isset($info['description'])
                    ? $info['description']
                    : null;
		}

		return null;
	}

	public static function getLink($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf('https://www.youtube.com/watch?v=%s', $videoUid);

			case self::Vimeo:
				return sprintf('https://vimeo.com/%s', $videoUid);

            case self::RuTube:
                return sprintf('https://rutube.ru/video/%s/', $videoUid);
		}

		return null;
	}

	public static function getEmbedLink($vendor, $videoUid)
	{
		switch ($vendor)
		{
			case self::YouTube:
				return sprintf('https://www.youtube.com/embed/%s', $videoUid);

			case self::Vimeo:
				return sprintf('https://player.vimeo.com/video/%s', $videoUid);

            case self::RuTube:
                return sprintf('https://rutube.ru/play/embed/%s', $videoUid);
		}

		return null;
	}
}