<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.05.2022
 * Time: 11:44
 */

namespace diCore\Tool\Captcha;

use diCore\Helper\ArrayHelper;
use diCore\Traits\BasicCreate;

class Captcha
{
    use BasicCreate;

    private $uid;
    private $code;

    const sessionKey = 'di-captcha';
    const hashSalt = 'wedontneednoEducation';

    const width = 50;
    const height = 20;

    const bgColor = '#EEEEEE';
    const fontColor = '#33AA33';
    const fontFamily = 'Arial';
    const fontSize = 14;
    const fontAngle = 10;
    const fontX = 5;
    const fontY = 19;

    public function __construct($uid = null)
    {
        $this->uid = $uid ?: get_unique_id();
    }

    public static function isEnabled()
    {
        return true;
    }

    public function getCode()
    {
        if (!$this->code) {
            $data = \diSession::get(static::sessionKey) ?: [];
            $this->code = ArrayHelper::get($data, ['codes', $this->uid]);

            if (!$this->code) {
                $this->code = $this->generateCode();
                if (empty($data['codes'])) {
                    $data['codes'] = [];
                }
                $data['codes'][$this->uid] = $this->code;
                \diSession::set(static::sessionKey, $data);
            }
        }

        return $this->code;
    }

    public function getCodeHash()
    {
        return md5($this->getCode() . static::hashSalt);
    }

    public function getUid()
    {
        return $this->uid;
    }

    protected function generateCode()
    {
        return rand(1000, 9999);
    }

    public function printPng()
    {
        header('Content-Type: image/png');
        header('Expires: Mon, 11 Jul 1999 00:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        $img = imagecreate(static::width, static::height);
        $fontFile = \diPaths::fileSystem() . \diWebFonts::getFileForFont(static::fontFamily);

        imagefill($img, 0, 0, rgb_allocate($img, static::bgColor));
        imagettftext($img, static::fontSize, static::fontAngle, static::fontX, static::fontY,
            rgb_allocate($img, static::fontColor), $fontFile, $this->getCode());

        imagepng($img);
    }
}