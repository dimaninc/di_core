<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.08.2016
 * Time: 16:17
 */

namespace diCore\Helper;

class StringHelper
{
	public static function random($length = 8)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';

		for ($i = 0; $i < $length; $i++)
		{
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}

		return $randomString;
	}

	public static function fixFloatDot($float)
	{
		return str_replace(",", ".", "$float");
	}

	public static function in($str)
	{
		/** @var \diDB $db */
		global $db;

		//$str = trim($str);
		$str = isset($db) ? $db->escape_string($str) : addslashes($str);
		//$str = mysql_real_escape_string($str);

		return $str;
	}

	public static function out($str, $replaceAmp = false)
	{
		if ($replaceAmp)
		{
			$str = str_replace('&', '&amp;', $str);
		}

		$str = str_replace('"', '&quot;', $str);
		//$str = str_replace("'", '&apos;', $str);
		$str = str_replace('<', '&lt;', $str);
		$str = str_replace('>', '&gt;', $str);

		return $str;
	}

	public static function hexToDec($hexStr)
	{
		$arr = str_split($hexStr, 4);
		$dec = [];

		foreach ($arr as $grp)
		{
			$dec[] = str_pad(hexdec($grp), 5, '0', STR_PAD_LEFT);
		}

		return implode('', $dec);
	}

	public static function decToHex($decStr)
	{
		$arr = str_split($decStr, 5);
		$hex = [];

		foreach ($arr as $grp)
		{
			$hex[] = str_pad(dechex($grp), 4, '0', STR_PAD_LEFT);
		}

		return implode('', $hex);
	}

	public static function wysiwygEmpty($s)
	{
		$s = preg_replace("/<\/?(p|br)[^>]*>/xis", "", $s);
		$s = str_replace("&nbsp;", "", $s);
		$s = trim($s);

		return !$s;
	}

	/**
	 * Remove a query string parameter from an URL.
	 *
	 * @param string $url
	 * @param string|array $removeParamNames
	 *
	 * @return string
	 */
	public static function removeQueryStringParameter($url, $removeParamNames = [], $keepParamNames = [])
	{
		if (!is_array($removeParamNames))
		{
			$removeParamNames = [$removeParamNames];
		}

		if (!is_array($keepParamNames))
		{
			$keepParamNames = [$keepParamNames];
		}

		$parsedUrl = parse_url($url);
		$query = [];

		if (isset($parsedUrl['query']))
		{
			parse_str($parsedUrl['query'], $query);

			foreach ($removeParamNames as $name)
			{
				if (isset($query[$name]))
				{
					unset($query[$name]);
				}
			}

			if ($keepParamNames)
			{
				$query = ArrayHelper::filterByKey($query, $keepParamNames);
			}
		}

		$path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
		$query = !empty($query) ? '?' . http_build_query($query) : '';

		$result = $path . $query;

		if (isset($parsedUrl['host']))
		{
			$result = $parsedUrl['host'] . $result;

			if (isset($parsedUrl['scheme']))
			{
				$result = $parsedUrl['scheme'] . '://' . $result;
			}
		}

		return $result;
	}

	public static function startsWith($haystack, $needle)
	{
		return substr($haystack, 0, strlen($needle)) === $needle;
	}

	public static function endsWith($haystack, $needle)
	{
		return substr($haystack, - strlen($needle)) === $needle;
	}

	public static function contains($haystack, $needle)
	{
		return strpos($haystack, $needle) !== false;
	}

	public static function slash($path, $ending = true)
	{
		if ($ending && mb_substr($path, mb_strlen($path) - 1, 1) != "/")
		{
			$path .= "/";
		}
		elseif (!$ending && mb_substr($path, 0, 1) != "/")
		{
			$path = "/" . $path;
		}

		return $path;
	}

	public static function unslash($path, $ending = true)
	{
		if ($ending && mb_substr($path, mb_strlen($path) - 1, 1) == "/")
		{
			$path = mb_substr($path, 0, mb_strlen($path) - 1);
		}
		elseif (!$ending && mb_substr($path, 0, 1) == "/")
		{
			$path = mb_substr($path, 1);
		}

		return $path;
	}

	public static function cutEnd($s, $maxLength, $trailer = '...')
	{
		if (mb_strlen($s) > $maxLength)
		{
			$s = rtrim(mb_substr(ltrim($s), 0, $maxLength - mb_strlen($trailer))) . $trailer;
		}

		return $s;
	}

	public static function smartCutEnd($s, $maxLength, $trailer = '...', $utf8 = true)
	{
		$printedLength = 0;
		$position = 0;
		$tags = [];

		$res = "";

		if (mb_strlen($s) > $maxLength)
		{
			$maxLength -= mb_strlen($trailer);
		}
		else
		{
			$trailer = "";
		}

		// For UTF-8, we need to count multibyte sequences as one character.
		$re = $utf8
			? '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;|[\x80-\xFF][\x80-\xBF]*}'
			: '{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}';

		while ($printedLength < $maxLength && preg_match($re, $s, $match, PREG_OFFSET_CAPTURE, $position))
		{
			list($tag, $tagPosition) = $match[0];

			// Print text leading up to the tag.
			$str = mb_substr($s, $position, $tagPosition - $position);

			if ($printedLength + mb_strlen($str) > $maxLength)
			{
				$res .= mb_substr($str, 0, $maxLength - $printedLength);
				$printedLength = $maxLength;

				break;
			}

			$res .= $str;
			$printedLength += mb_strlen($str);
			if ($printedLength >= $maxLength) break;

			if ($tag[0] == '&' || ord($tag) >= 0x80)
			{
				// Pass the entity or UTF-8 multibyte sequence through unchanged.
				$res .= $tag;
				$printedLength++;
			}
			else
			{
				// Handle the tag.
				$tagName = $match[1][0];
				if ($tag[1] == '/')
				{
					// This is a closing tag.
					$openingTag = array_pop($tags);
					assert($openingTag == $tagName); // check that tags are properly nested.

					$res .= $tag;
				}
				else if ($tag[mb_strlen($tag) - 2] == '/')
				{
					// Self-closing tag.
					$res .= $tag;
				}
				else
				{
					// Opening tag.
					$res .= $tag;
					$tags[] = $tagName;
				}
			}

			// Continue after the tag.
			$position = $tagPosition + mb_strlen($tag);
		}

		// Print any remaining text.
		if ($printedLength < $maxLength && $position < mb_strlen($s))
		{
			$res .= mb_substr($s, $position, $maxLength - $printedLength);
		}

		// Close any open tags.
		while (!empty($tags))
		{
			$res .= sprintf('</%s>', array_pop($tags));
		}

		$res .= $trailer;

		return $res;
	}

	public static function fileBaseName($filename)
	{
		$x = mb_strrpos($filename, '.');

		return $x !== false ? mb_substr($filename, 0, $x) : '';
	}

	public static function fileExtension($filename)
	{
		$x = mb_strrpos($filename, '.');

		return $x !== false ? mb_substr($filename, $x + 1) : '';
	}

	public static function replaceFileExtension($fn, $newExtension = '')
	{
		if ($newExtension && $newExtension{0} != ".")
		{
			$newExtension = "." . $newExtension;
		}

		$x = strrpos($fn, ".");

		if ($x !== false)
		{
			$fn = substr($fn, 0, $x);
		}

		return $fn . $newExtension;
	}

	public static function getTempFolderByFileName($fn, $depth = 2, $partLength = 2)
	{
		$fn = static::replaceFileExtension($fn, '');
		$ar = str_split($fn, $partLength);
		$ar = array_slice($ar, 0, $depth);

		return join('/', $ar) . '/';
	}

	public static function leftPad($string, $length, $char)
	{
		return str_repeat($char, $length - mb_strlen($string)) . $string;
	}

	public static function rightPad($string, $length, $char)
	{
		return $string . str_repeat($char, $length - mb_strlen($string));
	}

	public static function mimeTypeByFilename($filename)
	{
		$ext = strtolower(self::fileExtension($filename));

		if ($ext == 'jpeg' || $ext == 'jpg')
			$contentType = 'image/jpeg';
		elseif ($ext == 'gif' || $ext == 'png')
			$contentType = 'image/' . $ext;
		elseif ($ext == 'swf')
			$contentType = 'application/x-shockwave-flash';
		elseif ($ext == 'exe')
			$contentType = 'application/octet-stream';
		else
			$contentType = 'application/octet-stream';

		return $contentType;
	}
}