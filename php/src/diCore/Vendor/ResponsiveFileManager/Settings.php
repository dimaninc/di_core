<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.11.2017
 * Time: 19:11
 */

namespace diCore\Vendor\ResponsiveFileManager;

use diCore\Traits\BasicCreate;

class Settings
{
	use BasicCreate;

	public function get()
	{
		return [
			'maxWidth' => 4000,
			'maxHeight' => 3000,
		];
	}

	public function getByKey($key)
	{
		$ar = $this->get();

		return isset($ar[$key])
			? $ar[$key]
			: null;
	}

	public function processImage($targetFile)
	{
		$fn = realpath($targetFile);
		list($w, $h, $t) = is_file($fn) ? getimagesize($fn) : [0, 0, 0];

		if ($t >= 1 && $t <= 3 && $w * $h > $this->getByKey('maxWidth') * $this->getByKey('maxHeight') && class_exists('IMagick'))
		{
			$im = new \Imagick($fn);
			$im->resizeImage($this->getByKey('maxWidth'), $this->getByKey('maxHeight'), \Imagick::FILTER_CATROM, 1, true);
			$im->writeImage();
			$im->clear();
			$im->destroy();
			unset($im);
		}

		return $this;
	}
}