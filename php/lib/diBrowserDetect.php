<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.06.2015
 * Time: 17:23
 */

class diBrowserDetect
{
    protected $userAgent;

    protected $browsers = [
        'msie' => 'Internet Explorer',
        'firefox' => 'Firefox',
        'chrome' => 'Chrome',
        'safari' => 'Safari',
        'opera' => 'Opera',
        'netscape' => 'Netscape',
        'maxthon' => 'Maxthon',
        'konqueror' => 'Konqueror',
        'mobile' => 'Handheld Browser',
    ];

    protected $oses = [
        'windows nt 10' => 'Windows 10',
        'windows nt 6.3' => 'Windows 8.1',
        'windows nt 6.2' => 'Windows 8',
        'windows nt 6.1' => 'Windows 7',
        'windows nt 6.0' => 'Windows Vista',
        'windows nt 5.2' => 'Windows Server 2003/XP x64',
        'windows nt 5.1' => 'Windows XP',
        'windows xp' => 'Windows XP',
        'windows nt 5.0' => 'Windows 2000',
        'windows me' => 'Windows ME',
        'win98' => 'Windows 98',
        'win95' => 'Windows 95',
        'win16' => 'Windows 3.11',
        'macintosh|mac os x' => 'Mac OS X',
        'mac_powerpc' => 'Mac OS 9',
        'linux' => 'Linux',
        'ubuntu' => 'Ubuntu',
        'iphone' => 'iPhone',
        'ipod' => 'iPod',
        'ipad' => 'iPad',
        'android' => 'Android',
        'blackberry' => 'BlackBerry',
        'webos' => 'Mobile',
    ];

    public function __construct($userAgent = null)
    {
        $this->userAgent = $userAgent ?: \diRequest::userAgent();
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    public function getBrowser()
    {
        foreach ($this->browsers as $regex => $value) {
            if (preg_match("/$regex/i", $this->userAgent)) {
                return $value;
            }
        }

        return null;
    }

    public function getOS()
    {
        foreach ($this->oses as $regex => $value) {
            if (preg_match("/$regex/i", $this->userAgent)) {
                return $value;
            }
        }

        return null;
    }
}
