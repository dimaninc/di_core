<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.04.2016
 * Time: 16:57
 */
class diImagickHelper
{
	public static function getDraw($options = [])
	{
		$options = extend([
			"font" => null,
			"size" => null,
			"color" => null,
			"gravity" => null,
		], $options);

		$draw = new \ImagickDraw();

		if ($options["font"] !== null)
		{
			$draw->setFont($options["font"]);
		}

		if ($options["size"] !== null)
		{
			$draw->setFontSize($options["size"]);
		}

		if ($options["color"] !== null)
		{
			$draw->setFillColor($options["color"]);
		}

		if ($options["gravity"] !== null)
		{
			$draw->setGravity($options["gravity"]);
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
			"font" => null,
			"size" => null,
			"color" => null,
			"x" => null,
			"y" => null,
			"angle" => 0,
		], $options);

		$draw = self::getDraw($options);
		//$metrics = $im->queryFontMetrics($draw, $text);
		//$delta = ($metrics["textHeight"] ?: $options["size"]);
		$delta = $options["size"];

		$im->annotateImage($draw, $options["x"], $options["y"] + $delta, $options["angle"], $text);
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
			"return" => false, // if true, an image returns
			"font" => null,
			"size" => null,
			"color" => null,
			"w" => null,
			"h" => null,
			"x" => null,
			"y" => null,
			"fontLineHeight" => null,
			"defaultLineHeight" => null,
			"tuneCoordinatesCallback" => function($options, $realHeight) {
				$deltaX = $deltaY = 0;

				// Y-coordinate for center gravity
				if ($options["gravity"] == "center" || $options["gravity"] == Imagick::GRAVITY_CENTER)
				{
					if ($realHeight < $options["h"])
					{
						$deltaY = ($options["h"] - $realHeight) >> 1;
					}
				}

				return [$deltaX, $deltaY];
			},
			"gravity" => null,
		], $options);

		$draw = new \ImagickDraw();
		$draw->setFont($options["font"]);
		$draw->setFontSize($options["size"]);
		$draw->setFillColor($options["color"]);
		//$draw->setTextUnderColor("#FFFF00");

		$info = self::getWordWrapInfo($im, $draw, $text, $options["w"]);
		$lines = $info["lines"];
		$lineHeight = $info["lineHeight"];

		simple_debug("{$options["font"]} lineHeight calculated = $lineHeight, gotten from settings = " . $options["fontLineHeight"]);

		if ($options["fontLineHeight"] && (!$lineHeight || $lineHeight > $options["fontLineHeight"]))
		{
			$lineHeight = $options["fontLineHeight"];
		}

		$lineHeight = $lineHeight ?: $options["size"] * $options["fontLineHeight"];

		simple_debug("final lineHeight = $lineHeight");

		$label = new \Imagick();
		$label->newImage($options["w"], $options["h"] ?: $lineHeight * 2, self::transparentColor()); //"none"

		list($deltaX, $deltaY) = $options["tuneCoordinatesCallback"]
			? $options["tuneCoordinatesCallback"]($options, $lineHeight * count($lines))
			: [0, 0];

		for ($i = 0; $i < count($lines); $i++)
		{
			$deltaX = 0;

			if ($options["gravity"] == "center" || $options["gravity"] == Imagick::GRAVITY_CENTER)
			{
				$metrics = $im->queryFontMetrics($draw, $lines[$i]);
				$textWidth = $metrics['textWidth'];

				if ($textWidth < $options["w"])
				{
					$deltaX = ($options["w"] - $textWidth) >> 1;
				}
			}

			$x = $deltaX;
			// ($i + 1) - adding an extra line-height, because imagick uses Y coordinate as a lower base line for text
			$y = ($i + 1) * $lineHeight + $deltaY;

			simple_debug("[$x, $y]: {$lines[$i]}");

			$label->annotateImage($draw, $x, $y, 0, $lines[$i]);
		}

		$draw->clear();

		if ($options["return"])
		{
			return $label;
		}
		else
		{
			$im->compositeImage($label, \Imagick::COMPOSITE_DEFAULT, $options["x"], $options["y"]);
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
		if (!$draw->getFont())
		{
			throw new \Exception("Font not provided");
		}

		$words = $maxWidth ? explode(" ", $text) : [$text];
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

			$metrics = $image->queryFontMetrics($draw, $currentLine . ($i < count($words) - 1 ? ' ' . $words[$i + 1] : ""));

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
			"lines" => $lines,
			"lineHeight" => $lineHeight,
			"width" => $realMaxWidth,
			"height" => count($lines) * $lineHeight,
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