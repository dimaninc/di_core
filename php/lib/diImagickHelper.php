<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.04.2016
 * Time: 16:57
 */

use diCore\Tool\Logger;

class diImagickHelper
{
	public static function getDraw($options = [])
	{
		$options = extend([
			'font' => null,
			'size' => null,
			'color' => null,
			'gravity' => null,
		], $options);

		$draw = new \ImagickDraw();

		if ($options['font'] !== null) {
			$draw->setFont($options['font']);
		}

		if ($options['size'] !== null) {
			$draw->setFontSize($options['size']);
		}

		if ($options['color'] !== null) {
			$draw->setFillColor($options['color']);
		}

		if ($options['gravity'] !== null) {
			$draw->setGravity($options['gravity']);
		}

		return $draw;
	}

	/**
	 * @param Imagick $im
	 * @param $text
	 * @param array $options
	 */
	public static function printText(\Imagick $im, $text, $options = [])
	{
		$options = extend([
			'font' => null,
			'size' => null,
			'color' => null,
			'x' => null,
			'y' => null,
			'angle' => 0,
		], $options);

		$draw = self::getDraw($options);
		//$metrics = $im->queryFontMetrics($draw, $text);
		//$delta = ($metrics['textHeight'] ?: $options['size']);
		$delta = $options['size'];

		$im->annotateImage($draw, $options['x'], $options['y'] + $delta, $options['angle'], $text);
	}

	/**
	 * Don't forget to ->clear() result of this function after use
	 *
	 * @param Imagick $im
	 * @param $text
	 * @param array $options
	 * @return Imagick|null
	 */
	public static function printWrappedText(\Imagick $im, $text, $options = [])
	{
		$options = extend([
			'return' => false, // if true, an image returns
			'font' => null,
			'size' => null,
			'color' => null,
			'w' => null,
			'h' => null,
			'x' => null,
			'y' => null,
			'fontLineHeight' => null,
			'defaultLineHeight' => null,
			'tuneCoordinatesCallback' => function($options, $realHeight) {
				$deltaX = $deltaY = 0;

				// Y-coordinate for center gravity
				if ($options['gravity'] == 'center' || $options['gravity'] == Imagick::GRAVITY_CENTER) {
					if ($realHeight < $options['h']) {
						$deltaY = ($options['h'] - $realHeight) >> 1;
					}
				}

				return [$deltaX, $deltaY];
			},
			'gravity' => null,
            'extendImageIfNeeded' => false,
            'debug' => false,
		], $options);

		$draw = new \ImagickDraw();
		$draw->setFont($options['font']);
		$draw->setFontSize($options['size']);
		$draw->setFillColor($options['color']);
		//$draw->setTextUnderColor('#FFFF00');

        $width = $options['w'];
        $height = $options['h'];

		$info = self::getWordWrapInfo($im, $draw, $text, $width);
		$lines = $info['lines'];
		$lineHeight = $info['lineHeight'];

		if ($options['debug']) {
            Logger::getInstance()->log(
                "{$options['font']} lineHeight calculated = $lineHeight, gotten from settings = " . $options['fontLineHeight']
            );
            Logger::getInstance()->variable(
                '$info', $info
            );
        }

		if ($options['fontLineHeight'] && (!$lineHeight || $lineHeight > $options['fontLineHeight'])) {
			$lineHeight = $options['fontLineHeight'];
		}

		$lineHeight = $lineHeight ?: $options['size'] * $options['fontLineHeight'];

        $options['debug'] && Logger::getInstance()->log("final lineHeight = $lineHeight");

        if ($options['extendImageIfNeeded']) {
            if ($info['width'] > $width) {
                $width = $info['width'];
            }

            if ($lineHeight > $height) {
                $height = $lineHeight * count($lines) * 2;
            }
        }

		$label = new \Imagick();
		$label->newImage($width, $height ?: $lineHeight * count($lines) * 2, self::transparentColor());

		list($deltaX, $deltaY) = $options['tuneCoordinatesCallback']
			? $options['tuneCoordinatesCallback']($options, $lineHeight * count($lines))
			: [0, 0];

		for ($i = 0; $i < count($lines); $i++) {
			$deltaX = 0;

			if (
			    $options['gravity'] == 'center' ||
                $options['gravity'] == Imagick::GRAVITY_CENTER
            ) {
				$metrics = $im->queryFontMetrics($draw, $lines[$i]);
				$textWidth = $metrics['textWidth'];

				if ($textWidth < $width) {
					$deltaX = ($width - $textWidth) >> 1;
				}
			}

			$x = $deltaX;
			// ($i + 1) - adding an extra line-height, because imagick uses Y coordinate as a lower base line for text
			$y = ($i + 1) * $lineHeight + $deltaY;

            $options['debug'] && Logger::getInstance()->log("[$x, $y]: {$lines[$i]}");

			$label->annotateImage($draw, $x, $y, 0, $lines[$i]);
		}

		$draw->clear();

		if ($options['extendImageIfNeeded']) {
		    // trim
		    $label->trimImage(0);
            $label->setImagePage(0, 0, 0, 0);

            // extend back to needed size
            $extWidth = max($options['w'], $label->getImageWidth());
            $extHeight = max($options['h'], $label->getImageHeight());
            $x = round(($extWidth - $label->getImageWidth()) / 2);
            $y = round(($extHeight - $label->getImageHeight()) / 2);

            $label->setImageBackgroundColor(self::transparentColor());
            $label->extentImage($extWidth, $extHeight, -$x, -$y);
        }

		if ($options['return']) {
			return $label;
		} else {
			$im->compositeImage($label, \Imagick::COMPOSITE_DEFAULT, $options['x'], $options['y']);
			$label->clear();

			return null;
		}
	}

	/**
	 * @param Imagick $image
	 * @param ImagickDraw $draw
	 * @param $text
	 * @param $maxWidth
	 * @return array            Lines and and their heights
	 */
	public static function getWordWrapInfo(\Imagick $image, \ImagickDraw $draw, $text, $maxWidth = null)
	{
		if (!$draw->getFont()) {
			throw new \Exception('Font not provided');
		}

		$words = $maxWidth ? explode(' ', $text) : [$text];
		$lines = [];
		$i = 0;
		$lineHeight = 0;
		$realMaxWidth = 0;

		while ($i < count($words))
		{
			$currentLine = $words[$i];

			/*
			if ($i == count($words) - 1)
			{
				$lines[] = $currentLine;

				break;
			}
			*/

			$metrics = $image->queryFontMetrics($draw, $currentLine . ($i < count($words) - 1 ? ' ' . $words[$i + 1] : ''));

			while ($metrics['textWidth'] <= $maxWidth && $i < count($words) - 1)
			{
				$currentLine .= ' ' . $words[++$i];

				if ($i == count($words) - 1)
				{
					break;
				}

				$metrics = $image->queryFontMetrics($draw, $currentLine . ' ' . $words[$i + 1]);
			}

			$lines[] = $currentLine;
			$i++;

			if ($metrics['textHeight'] > $lineHeight)
			{
				$lineHeight = $metrics['textHeight'];
			}

			if ($metrics['textWidth'] > $realMaxWidth)
			{
				$realMaxWidth = $metrics['textWidth'];
			}
		}

		return [
			'lines' => $lines,
			'lineHeight' => $lineHeight,
			'width' => $realMaxWidth,
			'height' => count($lines) * $lineHeight,
		];
	}

	/**
	 * @return \ImagickPixel
	 */
	public static function transparentColor()
	{
		return new \ImagickPixel('#00000000');
	}
}