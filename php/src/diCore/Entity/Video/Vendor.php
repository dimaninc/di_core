<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.04.2020
 * Time: 12:08
 */

namespace diCore\Entity\Video;

use diCore\Helper\StringHelper;
use diCore\Tool\SimpleContainer;

class Vendor extends SimpleContainer
{
    const OWN = 0;
    const YOU_TUBE = 1;
    const VIMEO = 2;
    const RU_TUBE = 3;
    const FACEBOOK = 4;
    const ODNOKLASSNIKI = 5;
    const VK = 6;

    const Own = self::OWN;
    const YouTube = self::YOU_TUBE;
    const Vimeo = self::VIMEO;
    const RuTube = self::RU_TUBE;
    const Facebook = self::FACEBOOK;
    const Odnoklassniki = self::ODNOKLASSNIKI;
    const Vk = self::VK;

    public static $names = [
        self::OWN => 'OWN',
        self::YOU_TUBE => 'YOU_TUBE',
        self::VIMEO => 'VIMEO',
        self::RU_TUBE => 'RU_TUBE',
        self::FACEBOOK => 'FACEBOOK',
        self::ODNOKLASSNIKI => 'ODNOKLASSNIKI',
        self::VK => 'VK',
    ];

    public static $titles = [
        self::OWN => 'Собственное видео',
        self::YOU_TUBE => 'YouTube',
        self::VIMEO => 'Vimeo',
        self::RU_TUBE => 'RuTube',
        //self::FACEBOOK => 'Facebook', // todo: embed
        self::ODNOKLASSNIKI => 'Odnoklassniki',
        self::VK => 'VK', // todo: embed https://toster.ru/q/414920 https://toster.ru/q/233109
    ];

    const VK_EMBED_NEW = 'https://vk.com/video_ext.php?oid=%s&id=%s&hd=2';

    protected static $patterns = [
        self::YOU_TUBE =>
            '/^.*((youtu\.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??(v=)?([^#\&\?\"\']+)/',
        self::VIMEO =>
            '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([0-9]{6,11})[?]?.*/',
        self::RU_TUBE => '#(https?://)rutube\.ru/video/(private/)?([^/]+)/#',
        self::FACEBOOK => '#facebook\.com/watch/\?v=(\d+)#',
        self::ODNOKLASSNIKI => '#//ok\.ru/video(embed)?/(\d+)#',
        self::VK =>
            '#//(vk\.com|vkvideo\.ru)/(video(-?\d+)_(\d+)|video_ext\.php\?oid=-?(\d+)&id=(\d+))#',
    ];

    protected static $links = [
        self::YOU_TUBE => 'https://www.youtube.com/watch?v=%s',
        self::VIMEO => 'https://vimeo.com/%s',
        self::RU_TUBE => 'https://rutube.ru/video/%s/',
        self::FACEBOOK => 'https://www.facebook.com/watch/?v=%s',
        self::ODNOKLASSNIKI => 'https://ok.ru/video/%s',
        self::VK => 'https://vkvideo.ru/video-%s',
    ];

    protected static $embedLinks = [
        self::YOU_TUBE => 'https://www.youtube.com/embed/%s',
        self::VIMEO => 'https://player.vimeo.com/video/%s',
        self::RU_TUBE => 'https://rutube.ru/play/embed/%s',
        self::FACEBOOK => 'https://www.facebook.com/watch/?v=%s',
        self::ODNOKLASSNIKI => 'https://ok.ru/videoembed/%s?nochat=1',
        //self::VK => 'https://vk.com/video_ext.php?oid=%s&id=%s&hash=%s&hd=2',
        self::VK => 'https://vkvideo.ru/video_ext.php?oid=%s',
    ];

    public static function extractInfoFromEmbed($embed)
    {
        $ar = [
            'vendor' => null,
            'video_uid' => null,
            'private' => false,
        ];

        foreach (self::$patterns as $vendor => $pattern) {
            preg_match($pattern, $embed, $r);
            $idx = null;

            switch ($vendor) {
                case self::YOU_TUBE:
                    $idx = 8;
                    break;

                case self::VIMEO:
                    $idx = 5;
                    break;

                case self::RU_TUBE:
                    $idx = 3;
                    $ar['private'] = !empty($r[2]);
                    break;

                case self::ODNOKLASSNIKI:
                    $idx = 2;
                    break;

                case self::FACEBOOK:
                    $idx = 1;
                    break;

                case self::VK:
                    if (!empty($r[3]) && !empty($r[4])) {
                        // video link
                        $ar['video_uid'] = $r[3] . '_' . $r[4];
                        $ar['vendor'] = $vendor;
                    } elseif (!empty($r[5]) && !empty($r[6])) {
                        // video link
                        $ar['video_uid'] = $r[5] . '_' . $r[6];
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
        $url = "http://vimeo.com/api/v2/video/$videoUid.php";

        return unserialize(self::getFile($url));
    }

    private static function getRuTubeData($videoUid)
    {
        $url = "https://rutube.ru/api/video/$videoUid?format=json";

        return (array) json_decode(self::getFile($url));
    }

    public static function getDurationInSeconds($vendor, $videoUid)
    {
        switch ($vendor) {
            case self::RU_TUBE:
                $info = self::getRuTubeData($videoUid);

                return $info['duration'] ?? null;
        }

        return null;
    }

    public static function getThumbnail($vendor, $videoUid)
    {
        switch ($vendor) {
            case self::YOU_TUBE:
                return sprintf('//img.youtube.com/vi/%s/default.jpg', $videoUid); //http:

            case self::VIMEO:
                $info = self::getVimeoData($videoUid);

                return isset($info[0]['thumbnail_medium'])
                    ? str_replace(
                        'http://',
                        'https://',
                        $info[0]['thumbnail_medium']
                    )
                    : null;

            case self::RU_TUBE:
                return self::getPoster($vendor, $videoUid);
        }

        return null;
    }

    public static function getPoster($vendor, $videoUid)
    {
        switch ($vendor) {
            case self::YOU_TUBE:
                return sprintf('//img.youtube.com/vi/%s/0.jpg', $videoUid); //http:

            case self::VIMEO:
                $info = self::getVimeoData($videoUid);

                return isset($info[0]['thumbnail_large'])
                    ? str_replace('http://', 'https://', $info[0]['thumbnail_large'])
                    : null;

            case self::RU_TUBE:
                $info = self::getRuTubeData($videoUid);

                return isset($info['thumbnail_url']) ? $info['thumbnail_url'] : null;
        }

        return null;
    }

    public static function getTitle($vendor, $videoUid)
    {
        switch ($vendor) {
            case self::YOU_TUBE:
                $rawData = self::getFile(
                    'http://youtube.com/get_video_info?video_id=' . $videoUid
                );
                if ($rawData) {
                    parse_str($rawData, $data);

                    //"view_count"
                    if (isset($data['title'])) {
                        return $data['title'];
                    }
                }
                break;

            case self::VIMEO:
                $info = self::getVimeoData($videoUid);

                return isset($info[0]['title']) ? $info[0]['title'] : null;

            case self::RU_TUBE:
                $info = self::getRuTubeData($videoUid);

                return isset($info['title']) ? $info['title'] : null;
        }

        return null;
    }

    public static function getDescription($vendor, $videoUid)
    {
        switch ($vendor) {
            case self::YOU_TUBE:
                break;

            case self::VIMEO:
                $info = self::getVimeoData($videoUid);

                return isset($info[0]['description'])
                    ? $info[0]['description']
                    : null;

            case self::RU_TUBE:
                $info = self::getRuTubeData($videoUid);

                return isset($info['description']) ? $info['description'] : null;
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
        if ($vendor == self::VK) {
            $props = explode('_', $videoUid);

            if (count($props) === 2) {
                if (StringHelper::startsWith($props[0], 'video-')) {
                    $props[0] = mb_substr($props[0], 5);
                }

                list($oid, $id) = $props;

                return sprintf(self::VK_EMBED_NEW, $oid, $id);
            }
        }

        return !empty(self::$embedLinks[$vendor])
            ? sprintf(self::$embedLinks[$vendor], $videoUid)
            : null;
    }
}
