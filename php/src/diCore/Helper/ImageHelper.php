<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.01.2018
 * Time: 17:08
 */

namespace diCore\Helper;

class ImageHelper
{
    const IMAGE_MAGICK = 1;
    const PH_MAGICK = 2;
    const IMAGICK = 3;
    const GD = 4;

    const DEFAULT_BACKGROUND_COLOR = '#000000';

    protected static function vendor()
    {
        if (class_exists('\phMagick\Core\Runner') && false) {
            return self::PH_MAGICK;
        }

        if (class_exists('\Imagick')) {
            return self::IMAGICK;
        }

        if (function_exists('imagepng')) {
            return self::GD;
        }

        throw new \Exception('No image processing modules found');
    }

    public static function rotate(
        $angle,
        $inFilename,
        $outFilename = null,
        $backgroundColor = self::DEFAULT_BACKGROUND_COLOR
    ) {
        $origInFilename = $inFilename;
        $inFilename = realpath($inFilename);

        if (!$inFilename) {
            throw new \Exception("File not found: $origInFilename, $inFilename");
        }

        switch (self::vendor()) {
            case self::PH_MAGICK:
                self::rotatePhMagick(
                    $angle,
                    $inFilename,
                    $outFilename,
                    $backgroundColor
                );
                break;

            case self::IMAGICK:
                self::rotateIMagick(
                    $angle,
                    $inFilename,
                    $outFilename,
                    $backgroundColor
                );
                break;

            case self::GD:
                self::rotateGd($angle, $inFilename, $outFilename, $backgroundColor);
                break;
        }
    }

    public static function rotatePhMagick(
        $angle,
        $inFilename,
        $outFilename = null,
        $backgroundColor = self::DEFAULT_BACKGROUND_COLOR
    ) {
        throw new \Exception('Not yet implemented');
        $phMagick = new \phMagick\Core\Runner($filename);
        $tn = new \phMagick\Action\Resize\Proportional($filename, $filename);
        $tn->setWidth($resultWidth)->setHeight($resultHeight);
        $phMagick->run($tn);
    }

    public static function rotateIMagick(
        $angle,
        $inFilename,
        $outFilename = null,
        $backgroundColor = self::DEFAULT_BACKGROUND_COLOR
    ) {
        if ($outFilename === null) {
            $outFilename = $inFilename;
        }

        $im = new \Imagick($inFilename);
        $im->stripImage();
        $im->rotateImage($backgroundColor, $angle);
        $im->writeImage($outFilename);
        $im->clear();
    }

    public static function clearExifAndFix($fullFilename)
    {
        $currentPermissions = fileperms($fullFilename) & 0777;

        $im = new \Imagick($fullFilename);
        self::autoRotate($im);
        $im->stripImage();
        $im->writeImage($fullFilename);
        $im->clear();
        $im->destroy();

        chmod($fullFilename, $currentPermissions);
    }

    public static function autoRotate(\Imagick $image, $background = '#000000')
    {
        switch ($image->getImageOrientation()) {
            case \Imagick::ORIENTATION_TOPRIGHT:
                $image->flopImage();
                break;
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $image->rotateImage($background, 180);
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:
                $image->flopImage();
                $image->rotateImage($background, 180);
                break;
            case \Imagick::ORIENTATION_LEFTTOP:
                $image->flopImage();
                $image->rotateImage($background, -90);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $image->rotateImage($background, 90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:
                $image->flopImage();
                $image->rotateImage($background, 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $image->rotateImage($background, -90);
                break;
            default: // Invalid orientation
            case \Imagick::ORIENTATION_TOPLEFT:
                break;
        }
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    public static function rotateGd(
        $angle,
        $inFilename,
        $outFilename = null,
        $backgroundColor = self::DEFAULT_BACKGROUND_COLOR
    ) {
        if ($outFilename === null) {
            $outFilename = $inFilename;
        }

        $img = new \diImage();
        $source = $img->open($inFilename);

        if ($img->isImageType(\diImage::TYPE_PNG)) {
            imagealphablending($source, false);
            imagesavealpha($source, true);
            $color = imagecolorallocatealpha($source, 0, 0, 0, 127);
        } else {
            $color = rgb_allocate($source, $backgroundColor);
        }

        $rotation = imagerotate($source, -$angle, $color);

        if ($img->isImageType(\diImage::TYPE_PNG)) {
            imagealphablending($rotation, false);
            imagesavealpha($rotation, true);
        }

        $img->store($outFilename, $rotation);

        imagedestroy($source);
        imagedestroy($rotation);
    }

    public static function watermark(
        $watermarkFilename,
        $inFilename,
        $outFilename = null,
        $x = 'right',
        $y = 'bottom'
    ) {
        $watermarkFilename = realpath($watermarkFilename);
        $inFilename = realpath($inFilename);

        switch (self::vendor()) {
            case self::PH_MAGICK:
                self::watermarkPhMagick(
                    $watermarkFilename,
                    $inFilename,
                    $outFilename,
                    $x,
                    $y
                );
                break;

            case self::IMAGICK:
                self::watermarkIMagick(
                    $watermarkFilename,
                    $inFilename,
                    $outFilename,
                    $x,
                    $y
                );
                break;

            case self::GD:
                self::watermarkGd(
                    $watermarkFilename,
                    $inFilename,
                    $outFilename,
                    $x,
                    $y
                );
                break;
        }
    }

    public static function calculateWatermarkCoordinates(
        $watermarkFilename,
        $inFilename,
        $x,
        $y
    ) {
        list($srcWidth, $srcHeight) = is_file($inFilename)
            ? getimagesize($inFilename)
            : [0, 0, 0];
        list($wmWidth, $wmHeight) = is_file($watermarkFilename)
            ? getimagesize($watermarkFilename)
            : [0, 0, 0];

        if ($x == 'left') {
            $x = 0;
        } elseif ($x == 'right') {
            $x = $srcWidth - $wmWidth;
        } elseif ($x == 'center') {
            $x = $srcWidth - $wmWidth >> 1;
        } else {
            $x = $x >= 0 ? $x : $srcWidth - $wmWidth + (int) $x;
        }

        if ($y == 'top') {
            $y = 0;
        } elseif ($y == 'bottom') {
            $y = $srcHeight - $wmHeight;
        } elseif ($y == 'center') {
            $y = $srcHeight - $wmHeight >> 1;
        } else {
            $y = $y >= 0 ? $y : $srcHeight - $wmHeight + (int) $y;
        }

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    public static function watermarkPhMagick(
        $watermarkFilename,
        $inFilename,
        $outFilename = null,
        $x = 'right',
        $y = 'bottom'
    ) {
        throw new \Exception('Not yet implemented');
    }

    public static function watermarkIMagick(
        $watermarkFilename,
        $inFilename,
        $outFilename = null,
        $x = 'right',
        $y = 'bottom'
    ) {
        if ($outFilename === null) {
            $outFilename = $inFilename;
        }

        $xy = self::calculateWatermarkCoordinates(
            $watermarkFilename,
            $inFilename,
            $x,
            $y
        );

        $wm = new \Imagick($watermarkFilename);
        $im = new \Imagick($inFilename);

        $im->compositeImage($wm, \Imagick::COMPOSITE_OVER, $xy['x'], $xy['y']);
        $im->writeImage($outFilename);

        $wm->clear();
        $im->clear();
    }

    public static function watermarkGd(
        $watermarkFilename,
        $inFilename,
        $outFilename = null,
        $x = 'right',
        $y = 'bottom'
    ) {
        if ($outFilename === null) {
            $outFilename = $inFilename;
        }

        $xy = self::calculateWatermarkCoordinates(
            $watermarkFilename,
            $inFilename,
            $x,
            $y
        );

        $img = new \diImage();
        $img->open($inFilename);
        $img->merge_wm($watermarkFilename, $xy['x'], $xy['y']);
        $img->store($outFilename);
        $img->close();
    }
}
