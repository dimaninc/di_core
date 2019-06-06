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
	const Facebook = 4;
	const Odnoklassniki = 5;
	const Vk = 6;

	public static $titles = [
		self::Own => "Собственное видео",
		self::YouTube => "YouTube",
		self::Vimeo => "Vimeo",
        self::RuTube => 'RuTube',
        //self::Facebook => 'Facebook', // todo: embed
        self::Odnoklassniki => 'Odnoklassniki',
        self::Vk => 'VK', // todo: embed https://toster.ru/q/414920 https://toster.ru/q/233109
	];

	protected static $patterns = [
		self::YouTube => '/^.*((youtu\.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?\"\']*).*/',
		self::Vimeo => '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/',
        self::RuTube => '#(https?://)rutube\.ru/video/([^/]+)/#',
        self::Facebook => '#facebook\.com/watch/\?v=(\d+)#',
        self::Odnoklassniki => '#//ok\.ru/video(embed)?/(\d+)#',
        self::Vk => '#//vk\.com/(video(\d+)_(\d+)|video_ext\.php\?oid=-?(\d+)&id=(\d+))#',
	];

	protected static $links = [
        self::YouTube => 'https://www.youtube.com/watch?v=%s',
        self::Vimeo => 'https://vimeo.com/%s',
        self::RuTube => 'https://rutube.ru/video/%s/',
        self::Facebook => 'https://www.facebook.com/watch/?v=%s',
        self::Odnoklassniki => 'https://ok.ru/video/%s',
        self::Vk => 'https://vk.com/video-%s',
    ];

    protected static $embedLinks = [
        self::YouTube => 'https://www.youtube.com/embed/%s',
        self::Vimeo => 'https://player.vimeo.com/video/%s',
        self::RuTube => 'https://rutube.ru/play/embed/%s',
        self::Facebook => 'https://www.facebook.com/watch/?v=%s',
        self::Odnoklassniki => 'https://ok.ru/videoembed/%s?nochat=1',
        //self::Vk => 'https://vk.com/video_ext.php?oid=%s&id=%s&hash=%s&hd=2',
        self::Vk => 'https://vk.com/video_ext.php?oid=%s',
    ];

	public static function extractInfoFromEmbed($embed)
	{
		$ar = [
			'vendor' => null,
			'video_uid' => null,
		];

        foreach (self::$patterns as $vendor => $pattern) {
            preg_match($pattern, $embed, $r);
            $idx = null;

            switch ($vendor) {
                case self::YouTube:
                    $idx = 7;
                    break;

                case self::Vimeo:
                    $idx = 5;
                    break;

                case self::RuTube:
                case self::Odnoklassniki:
                    $idx = 2;
                    break;

                case self::Facebook:
                    $idx = 1;
                    break;

                case self::Vk:
                    if (!empty($r[2]) && !empty($r[3])) {
                        // video link
                        $ar['video_uid'] = $r[2] . '_' . $r[3];
                        $ar['vendor'] = $vendor;
                    } elseif (!empty($r[4]) && !empty($r[5])) {
                        // video link
                        $ar['video_uid'] = $r[4] . '_' . $r[5];
                        $ar['vendor'] = $vendor;
                    }
                    break;
            }

            if ($idx && !empty($r[$idx])) {
                $ar['video_uid'] = $r[$idx];
                $ar['vendor'] = $vendor;

                break;
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
		switch ($vendor) {
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
		switch ($vendor) {
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
        switch ($vendor) {
            case self::YouTube:
                $rawData = self::getFile("http://youtube.com/get_video_info?video_id=" . $videoUid);
                if ($rawData) {
                    parse_str($rawData, $data);

                    //"view_count"
                    if (isset($data["title"])) {
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
		switch ($vendor) {
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
		return !empty(self::$links[$vendor])
            ? sprintf(self::$links[$vendor], $videoUid)
            : null;
	}

	public static function getEmbedLink($vendor, $videoUid)
	{
        return !empty(self::$embedLinks[$vendor])
            ? sprintf(self::$embedLinks[$vendor], $videoUid)
            : null;
	}
}