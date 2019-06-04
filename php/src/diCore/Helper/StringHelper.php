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
		return str_replace(',', '.', "$float");
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
		$s = str_replace('&nbsp;', '', $s);
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

	public static function getUrlParamGlue($url)
	{
		return self::contains($url, '?') ? '&' : '?';
	}

	public static function startsWith($haystack, $needle)
	{
		if (is_array($haystack))
		{
			foreach ($haystack as $h) if (self::startsWith($h, $needle)) return true;
			return false;
		}

		if (is_array($needle))
		{
			foreach ($needle as $n) if (self::startsWith($haystack, $n)) return true;
			return false;
		}

		return substr($haystack, 0, strlen($needle)) === $needle;
	}

	public static function endsWith($haystack, $needle)
	{
		if (is_array($haystack))
		{
			foreach ($haystack as $h) if (self::endsWith($h, $needle)) return true;
			return false;
		}

		if (is_array($needle))
		{
			foreach ($needle as $n) if (self::endsWith($haystack, $n)) return true;
			return false;
		}

		return substr($haystack, - strlen($needle)) === $needle;
	}

	public static function contains($haystack, $needle)
	{
		if (is_array($haystack))
		{
			foreach ($haystack as $h) if (self::contains($h, $needle)) return true;
			return false;
		}

		if (is_array($needle))
		{
			foreach ($needle as $n) if (self::contains($haystack, $n)) return true;
			return false;
		}

		return strpos($haystack, $needle) !== false;
	}

	public static function slash($path, $ending = true)
	{
		if ($ending && mb_substr($path, mb_strlen($path) - 1, 1) != '/')
		{
			$path .= '/';
		}
		elseif (!$ending && mb_substr($path, 0, 1) != '/')
		{
			$path = '/' . $path;
		}

		return $path;
	}

	public static function unslash($path, $ending = true)
	{
		if ($ending && mb_substr($path, mb_strlen($path) - 1, 1) == '/')
		{
			$path = mb_substr($path, 0, mb_strlen($path) - 1);
		}
		elseif (!$ending && mb_substr($path, 0, 1) == '/')
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

		$res = '';

		if (mb_strlen($s) > $maxLength)
		{
			$maxLength -= mb_strlen($trailer);
		}
		else
		{
			$trailer = '';
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

    public static function wrapUrlWithTag($text, $options = [])
    {
        $options = extend([
            'cutLength' => 0, // words with length greater will get cut up to the len, if 0 - no cutting
            'cutAllWords' => false, // if true - all words in $text get cut, if 'false' - only urls
            'tagAttrs' => [ // attributes for tags
                'target' => '_blank',
            ],
            'emails' => false, // wrap emails with tags
        ], $options);

        $lines = explode("\n", $text);

        for ($i = 0; $i < sizeof($lines); $i++) {
            $words = explode(' ', $lines[$i]);

            for ($j = 0; $j < sizeof($words); $j++) {
                $words[$j] = trim($words[$j]);
                $len = mb_strlen($words[$j]);

                $prefix = mb_strtolower(mb_substr($words[$j], 0, 8));
                $isEmail = \diEmail::isValid($words[$j]);

                if (
                    mb_substr($prefix, 0, 7) == 'http://' ||
                    mb_substr($prefix, 0, 8) == 'https://' ||
                    mb_substr($prefix, 0, 6) == 'ftp://' ||
                    mb_substr($prefix, 0, 4) == 'www.' ||
                    ($options['emails'] && $isEmail)
                ) {
                    $href = $words[$j];

                    if ($isEmail) {
                        $href = 'mailto:' . $href;
                    } else {
                        if (mb_substr($prefix, 0, 4) == 'www.') {
                            $href = 'http://' . $href;
                        }
                    }

                    $options['tagAttrs']['href'] = $href;
                    $attributes = \diCore\Helper\ArrayHelper::toAttributesString($options['tagAttrs']);

                    $innerText = $len > $options['cutLength'] && $options['cutLength'] > 0
                        ? mb_substr($words[$j], 0, $options['cutLength'] - 3) . '...'
                        : $words[$j];

                    $words[$j] = '<a ' . $attributes . '>' . $innerText . '</a>';
                } elseif ($options['cutAllWords'] && $options['cutLength'] > 0 && $len > $options['cutLength']) {
                    $words[$j] = mb_substr($words[$j], 0, $options['cutLength']);
                }
            }

            $lines[$i] = join(' ', $words);
        }

        $text = join("\n", $lines);

        return $text;
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
		if ($newExtension && $newExtension{0} != '.')
		{
			$newExtension = '.' . $newExtension;
		}

		$x = strrpos($fn, '.');

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

    public static function ucFirst($string)
    {
        $len = mb_strlen($string);
        $first = mb_substr($string, 0, 1);
        $rest = mb_substr($string, 1, $len - 1);

        return mb_strtoupper($first) . $rest;
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

	public static function digitCase($x, $s1, $s2, $s3 = null, $returnOnlySuffix = false)
	{
		if ($s3 === null)
		{
			$s3 = $s2;
		}

		$x0 = $x;
		$x = $x % 100;

		if ($x % 10 == 1 && $x != 11)
			return $returnOnlySuffix ? $s1 : "$x0 $s1";
		elseif ($x % 10 >= 2 && $x % 10 <= 4 && $x != 12 && $x != 13 && $x != 14)
			return $returnOnlySuffix ? $s2 : "$x0 $s2";
		else
			return $returnOnlySuffix ? $s3 : "$x0 $s3";
	}

	public static function divideThousands($x, $divider = ',', $length = 3)
	{
		$x = strval($x);

		$dotPos = mb_strpos($x, '.');
		if ($dotPos === false)
		{
			$dotPos = mb_strlen($x);
		}

		$fractionPart = mb_substr($x, $dotPos);
		$x = mb_substr($x, 0, $dotPos);

		$res = '';
		$start = mb_strlen($x) - $length;
		$steps = ceil(mb_strlen($x) / $length);

		for ($i = 0; $i < $steps; $i++)
		{
			$len = $length;

			if ($start < 0)
			{
				$len += $start;
				$start = 0;
			}

			$res = mb_substr($x, $start, $len) . $divider . $res;
			$start -= 3;
		}

		$res = mb_substr($res, 0, mb_strlen($res) - mb_strlen($divider));

		return $res . $fractionPart;
	}
}