<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.10.2016
 * Time: 13:19
 */

namespace diCore\Tool\Image;


class IM extends Base
{
	/** @var \Imagick */
	private $im;

	public function __construct($fn)
	{
		$this->im = new \Imagick($fn);
	}

	public function save($fn, $quality)
	{
		$this->getIm()->setImageFilename($fn);
		$this->getIm()->writeImage();

		return $this;
	}

	public function saveJpeg($fn, $quality)
	{
		$this->getIm()->setFormat('jpg');
		$this->getIm()->setImageCompression(\Imagick::COMPRESSION_JPEG);
		$this->getIm()->setImageCompressionQuality($quality);

		return $this->save($fn, $quality);
	}

	public function resize($width, $height)
	{
		$this->getIm()->resizeImage($width, $height, \Imagick::FILTER_CATROM, 1);

		return $this;
	}

	/**
	 * @return \Imagick
	 */
	private function getIm()
	{
		return $this->im;
	}
}