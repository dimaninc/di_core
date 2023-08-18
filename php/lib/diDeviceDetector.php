<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 25.04.2016
 * Time: 11:33
 */
class diDeviceDetector
{
    const TYPE_PC = 1;
    const TYPE_PHONE = 2;
    const TYPE_TABLET = 3;

    const OS_UNKNOWN = 0;
    const OS_WINDOWS = 1;
    const OS_IOS = 2;
    const OS_ANDROID = 3;

    const className = 'diCustomDeviceDetector';

    public static $types = [
        self::TYPE_PC => 'pc',
        self::TYPE_PHONE => 'phone',
        self::TYPE_TABLET => 'tablet',
    ];

    public static $oses = [
        self::OS_UNKNOWN => '',
        self::OS_WINDOWS => 'windows',
        self::OS_IOS => 'ios',
        self::OS_ANDROID => 'android',
    ];

    /**
     * @var integer
     */
    protected $type = self::TYPE_PC;

    /**
     * @var integer
     */
    protected $os = self::OS_UNKNOWN;

    /**
     * @var Mobile_Detect
     */
    protected $mobileDetect;

    public function __construct()
    {
        $this->mobileDetect = new Mobile_Detect();

        if ($this->mobileDetect->isTablet()) {
            $this->type = self::TYPE_TABLET;
        } elseif ($this->mobileDetect->isMobile()) {
            $this->type = self::TYPE_PHONE;
        }

        if ($this->mobileDetect->is('iOS')) {
            $this->os = self::OS_IOS;
        } elseif ($this->mobileDetect->is('AndroidOS')) {
            $this->os = self::OS_ANDROID;
        }
    }

    /**
     * @return diDeviceDetector
     */
    public static function create()
    {
        $className = class_exists(self::className)
            ? self::className
            : get_called_class();

        $o = new $className();

        return $o;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getOs()
    {
        return $this->os;
    }

    public function isMobile()
    {
        return in_array($this->getType(), [
            self::TYPE_PHONE,
            self::TYPE_TABLET,
        ]);
    }

    public function isDesktop()
    {
        return $this->getType() == self::TYPE_PC;
    }

    public function isAndroid()
    {
        return $this->getOs() == self::OS_ANDROID;
    }

    public function isIOS()
    {
        return $this->getOs() == self::OS_IOS;
    }

    public function getTypeStr()
    {
        return self::$types[$this->type];
    }

    public function getOsStr()
    {
        return self::$oses[$this->os];
    }

    public static function getBrowserAndOs($agt)
    {
        $md = new Mobile_Detect();
        $bd = new diBrowserDetect();

        $device = '?';
        $browser = '?';

        if ($agt) {
            $md->setUserAgent($agt);
            $bd->setUserAgent($agt);

            if ($md->isMobile() || $md->isTablet()) {
                if ($md->isiPhone()) {
                    $device = 'iPhone';
                } elseif ($md->isiPad()) {
                    $device = 'iPad';
                } elseif ($md->isSamsung()) {
                    $device = 'Samsung';
                } elseif ($md->isLG()) {
                    $device = 'LG';
                } elseif ($md->isSony()) {
                    $device = 'Sony';
                } elseif ($md->isNexus()) {
                    $device = 'Nexus';
                } elseif ($md->isHTC()) {
                    $device = 'HTC';
                } elseif ($md->isAndroidOS()) {
                    $device = 'Android';
                }

                if ($md->isOpera()) {
                    $browser = 'Opera';
                } elseif ($md->isSafari()) {
                    $browser = 'Safari';
                } elseif ($md->isChrome()) {
                    $browser = 'Chrome';
                } elseif ($md->isFirefox()) {
                    $browser = 'Firefox';
                } elseif ($md->isSafari()) {
                    $browser = 'Safari';
                } elseif ($md->isIE()) {
                    $browser = 'IE';
                } elseif ($md->isPuffin()) {
                    $browser = 'Puffin';
                } elseif ($md->isAndroidOS()) {
                    $browser = 'Android Browser';
                }
            } else {
                $device = 'PC, ' . $bd->getOS();
                $browser = $bd->getBrowser();
            }
        }

        return [
            'device' => $device,
            'browser' => $browser,
        ];
    }
}
