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
		if (class_exists('\phMagick\Core\Runner') && false)
		{
			return self::PH_MAGICK;
		}
		elseif (class_exists('\Imagick'))
		{
			return self::IMAGICK;
		}
		elseif (function_exists('imagepng'))
		{
			return self::GD;
		}

		throw new \Exception('No image processing modules found');
	}

	public static function rotate($angle, $inFilename, $outFilename = null, $backgroundColor = self::DEFAULT_BACKGROUND_COLOR)
	{
		$inFilename = realpath($inFilename);

		switch (self::vendor())
		{
			case self::PH_MAGICK:
				self::rotatePhMagick($angle, $inFilename, $outFilename, $backgroundColor);
				break;

			case self::IMAGICK:
				self::rotateIMagick($angle, $inFilename, $outFilename, $backgroundColor);
				break;

			case self::GD:
				self::rotateGd($angle, $inFilename, $outFilename, $backgroundColor);
				break;
		}
	}

	public static function rotatePhMagick($angle, $inFilename, $outFilename = null, $backgroundColor = self::DEFAULT_BACKGROUND_COLOR)
	{
		throw new \Exception('Not yet implemented');
		$phMagick = new \phMagick\Core\Runner($filename);
		$tn = new \phMagick\Action\Resize\Proportional($filename, $filename);
		$tn->setWidth($resultWidth)->setHeight($resultHeight);
		$phMagick->run($tn);
	}

	public static function rotateIMagick($angle, $inFilename, $outFilename = null, $backgroundColor = self::DEFAULT_BACKGROUND_COLOR)
	{
		$im = new \Imagick($inFilename);
		$im->rotateImage($backgroundColor, $angle);
		$im->writeImage($outFilename);
		$im->clear();
	}

	public static function rotateGd($angle, $inFilename, $outFilename = null, $backgroundColor = self::DEFAULT_BACKGROUND_COLOR)
	{
		if ($outFilename === null)
		{
			$outFilename = $inFilename;
		}

		$img = new \diImage();
		$source = $img->open($inFilename);

		if ($img->isImageType(\diImage::TYPE_PNG))
		{
			imagealphablending($source, false);
			imagesavealpha($source, true);
			$color = imagecolorallocatealpha($source, 0, 0, 0, 127);
		}
		else
		{
			$color = rgb_allocate($source, $backgroundColor);
		}

		$rotation = imagerotate($source, $angle, $color);

		if ($img->isImageType(\diImage::TYPE_PNG))
		{
			imagealphablending($rotation, false);
			imagesavealpha($rotation, true);
		}

		$img->store($outFilename, $rotation);

		imagedestroy($source);
		imagedestroy($rotation);
	}
}