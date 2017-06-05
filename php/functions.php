<?php
/*
    // dimaninc
*/

require dirname(__FILE__) . "/lib/diLib.php";

$html_encodings_ar = [
	"CP1251" => "windows-1251",
	"UTF8" => "utf-8",
];

// -[ locale stuff ]----------------------------------------------------------------
define("DIENCODING", "UTF8");

setlocale(LC_COLLATE, "ru_RU." . DIENCODING);
setlocale(LC_CTYPE, "ru_RU." . DIENCODING);

mb_internal_encoding("UTF-8");
//

// for dierror
define("DIE_NOTICE", 0);
define("DIE_WARNING", 1);
define("DIE_FATAL", 2);
//

define("SECS_PER_DAY", 86400);

$days_in_mon_ar = [
	false => [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
	true => [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
];

$month_titles = [
	"января",
	"февраля",
	"марта",
	"апреля",
	"мая",
	"июня",
	"июля",
	"августа",
	"сентября",
	"октября",
	"ноября",
	"декабря",
];

$eng_month_titles = [
	"january",
	"february",
	"march",
	"april",
	"may",
	"june",
	"july",
	"august",
	"september",
	"october",
	"november",
	"december",
];

$months_titles_ar = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
	"Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

$eng_months_titles_ar = ["January", "February", "March", "April", "May", "June",
	"July", "August", "September", "October", "November", "December"];

$months_titles_ar2 = ["Января", "Февраля", "Марта", "Апреля", "Мая", "Июня",
	"Июля", "Августа", "Сентября", "Октября", "Ноября", "Декабря"];

$wd_ar = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];

$rus_months_ar = [
	1 => "Январь",
	2 => "Февраль",
	3 => "Март",
	4 => "Апрель",
	5 => "Май",
	6 => "Июнь",
	7 => "Июль",
	8 => "Август",
	9 => "Сентябрь",
	10 => "Октябрь",
	11 => "Ноябрь",
	12 => "Декабрь",
];

$rus_months2_ar = [
	1 => "января",
	2 => "февраля",
	3 => "марта",
	4 => "апреля",
	5 => "мая",
	6 => "июня",
	7 => "июля",
	8 => "августа",
	9 => "сентября",
	10 => "октября",
	11 => "ноября",
	12 => "декабря",
];

$prepare_word_trans_table = [
	"клу" => "clu",
	"кат" => "cat",
	"ком" => "com",
	"кон" => "con",
	"цц" => "zz",
	"ый" => "y",
	"а" => "a",
	"б" => "b",
	"в" => "v",
	"г" => "g",
	"д" => "d",
	"е" => "e",
	"ё" => "e",
	"ж" => "zh",
	"з" => "z",
	"и" => "i",
	"й" => "y",
	"к" => "k",
	"л" => "l",
	"м" => "m",
	"н" => "n",
	"о" => "o",
	"п" => "p",
	"р" => "r",
	"с" => "s",
	"т" => "t",
	"у" => "u",
	"ф" => "f",
	"х" => "h",
	"ц" => "ts",
	"ч" => "ch",
	"ш" => "sh",
	"щ" => "sch",
	"ъ" => "",
	"ы" => "y",
	"ь" => "",
	"э" => "e",
	"ю" => "yu",
	"я" => "ya",
	"$" => "s",
	"0" => "o",
//   " " => "_",
];

/** @deprecated */
function get_absolute_path($target = null)
{
	/** @var \diCore\Data\Paths $className */
	$className = \diLib::getChildClass(\diCore\Data\Paths::class);

	return $className::fileSystem($target);
}

/** @deprecated */
function get_http_path($target = null)
{
	/** @var \diCore\Data\Paths $className */
	$className = \diLib::getChildClass(\diCore\Data\Paths::class);

	return $className::http($target);
}

function get_pics_folder($table)
{
	if (isset($GLOBALS[$table."_pics_folder"]))
	{
		return $GLOBALS[$table."_pics_folder"];
	}

	return "uploads/{$table}/";
}

function get_files_folder($table)
{
	if (isset($GLOBALS[$table."_files_folder"]))
	{
		return $GLOBALS[$table."_files_folder"];
	}

	return "uploads/{$table}/files/";
}

function get_tn_folder($index = "")
{
	if ($index < 2)
	{
		$index = "";
	}

	return "preview{$index}/";
}

function get_orig_folder()
{
	global $orig_folder;

	return $orig_folder;
}

function get_big_folder()
{
	global $big_folder;

	return $big_folder;
}

function get_tmp_folder()
{
	global $tmp_folder;

	return $tmp_folder;
}

function getSettingsFolder()
{
	global $settings_folder;

	return isset($settings_folder) ? $settings_folder : 'uploads/settings/';
}

function getFilesFolder()
{
	global $files_folder;

	return $files_folder;
}

function getDynamicPicsFolder()
{
	if (isset($GLOBALS["dynamic_pics_folder"]))
	{
		return $GLOBALS["dynamic_pics_folder"];
	}

	return "uploads/dynamic_pics/";
}

/** @deprecated */
function str_in($str)
{
	return diStringHelper::in($str);
}

/** @deprecated */
function str_out($str, $replaceAmp = false)
{
	return diStringHelper::out($str, $replaceAmp);
}

function isInteger($value)
{
	return !is_int($value) ? ctype_digit($value) : true;
}

function lead0($num)
{
	if (mb_strlen($num) == 1)
	{
		$num = "0".$num;
	}

	return $num;
}

/** @deprecated  */
function add_ending_slash($path)
{
	return \diCore\Helper\StringHelper::slash($path);
}

/** @deprecated  */
function remove_ending_slash($path)
{
	return \diCore\Helper\StringHelper::unslash($path);
}

/** @deprecated  */
function create_folders_chain($start_path, $path_to_create, $mod = 0775)
{
	\diCore\Helper\FileSystemHelper::createTree($start_path, $path_to_create, $mod);
}

/** @deprecated */
function isLeapYear($year)
{
	return diDateTime::isLeapYear($year);
}

// $cut_len - words with length greater than $cut_len will get cut up to the len
//            if 0 - no cutting
// $cut_all_words - if set 'true', all words in $text get cut. if 'false' - only urls
function highlight_urls($text, $cut_len = 0, $cut_all_words = false, $paramz = array("target" => "_blank"))
{
	$lines_ar = explode("\n", $text);

	for ($i = 0; $i < sizeof($lines_ar); $i++)
	{
		$words_ar = explode(" ", $lines_ar[$i]);

		for ($j = 0; $j < sizeof($words_ar); $j++)
		{
			$words_ar[$j] = trim($words_ar[$j]);

			$prefix = mb_strtolower(mb_substr($words_ar[$j], 0, 8));

			if (
				mb_substr($prefix, 0, 7) == "http://" ||
				mb_substr($prefix, 0, 8) == "https://" ||
				mb_substr($prefix, 0, 6) == "ftp://" ||
				mb_substr($prefix, 0, 4) == "www."
			)
			{
				if (mb_substr($prefix, 0, 4) == "www.") $words_ar[$j] = "http://".$words_ar[$j];

				$s_paramz = "";
				foreach ($paramz as $n => $v)
				{
					$s_paramz .= " ".$n."=\"".$v."\"";
				}

				$inner_text = (mb_strlen($words_ar[$j]) > $cut_len && $cut_len > 0)
					? mb_substr($words_ar[$j], 0, $cut_len - 3)."..."
					: $words_ar[$j];

				$words_ar[$j] = "<a href=\"".$words_ar[$j]."\"".$s_paramz.">".$inner_text."</a>";
			}
			elseif ($cut_all_words && $cut_len > 0 && mb_strlen($words_ar[$j]) > $cut_len)
			{
				$words_ar[$j] = mb_substr($words_ar[$j], 0, $cut_len);
			}
		}

		$lines_ar[$i] = join(" ", $words_ar);
	}

	$text = join("\n", $lines_ar);

	return $text;
}

function divide3dig($s, $divider = ",")
{
  $x = mb_strpos($s, ".");

  if ($x === false)
    $x = mb_strlen($s);

  $s2 = mb_substr($s, $x);
  $s = mb_substr($s, 0, $x);

  $ss = "";
  $start = mb_strlen($s) - 3;

  for ($i = 0; $i < ceil(mb_strlen($s) / 3); $i++)
  {
    $len = 3;

    if ($start < 0)
    {
      $len += $start;
      $start = 0;
    }

    $ss = mb_substr($s, $start, $len).$divider.$ss;

    $start -= 3;
  }

  $ss = mb_substr($ss, 0, mb_strlen($ss) - mb_strlen($divider));

  return $ss.$s2;
}

/** @deprecated */
function is_email_valid($email)
{
  return diEmail::isValid($email);
}

function is_back_valid($back)
{
  return !preg_match("/^(https?:\/\/|ftp:\/\/|mailto:)/i", ltrim($back));
}

// ** header encoding added
// each element in attachment_ar should look like this:
// [0] => array(
//          "filename" => "filename.jpg",
//          "content_type" => "image/jpeg",
//          "data" => "[binary_data]"),
/** @deprecated */
function send_email($from, $to, $subject, $message, $body_html, $attachment_ar = false)
{
	return diEmail::fastSend($from, $to, $subject, $message, $body_html, $attachment_ar);
}

function str_cut_end($s, $max_len, $trailer = "...")
{
	return \diCore\Helper\StringHelper::cutEnd($s, $max_len, $trailer);
}

function smart_str_cut_end($s, $max_len, $trailer = "...", $is_utf8 = false)
{
	return \diCore\Helper\StringHelper::smartCutEnd($s, $max_len, $trailer, $is_utf8);
}

/**
 * @deprecated
 * @param $sPath
 * @param bool $dir_in_filename
 * @param bool $recursive
 * @return array        array("f" => array(files...), "d" => array(directories...))
 */
function get_dir_array($sPath, $dir_in_filename = false, $recursive = false)
{
	return \diCore\Helper\FileSystemHelper::folderContents($sPath, $dir_in_filename, $recursive);
}

if (!function_exists('glob_recursive'))
{
	// Does not support flag GLOB_BRACE
	function glob_recursive($pattern, $flags = 0)
	{
		$files = glob($pattern, $flags);

		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
		{
			$files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
		}

		return $files;
	}
}

/** @deprecated  */
function get_file_ext($fn)
{
	return \diCore\Helper\StringHelper::fileExtension($fn);
}

// returns an array:
//  "lines" => array of "script" lines
//  "script" => javascript with <script> tags for sending email
//  "email" => e-mail address escaped, so fuken spammerz couldn't know it
//  "a" => array of <a> tag elements "onclick" and "href"
function publish_email($email, $doggy_replacer = "&#64", $unique_function_name_ending = "")
{
  $lines = array();
  $a["onclick"] = "onclick=\"sendMail".$unique_function_name_ending."(this);\"";
  $a["href"] = "href=\"#\"";
  $_email = "";

  $email = trim($email);

  $i = 0;
  $j = 0;
  $parts = array();

  while ($i < mb_strlen($email))
  {
    if (in_array($email[$i], array("@",".","_","-")) || $i == mb_strlen($email) - 1)
    {
      if ($i < mb_strlen($email) - 1)
      {
        $parts[] = mb_substr($email, $j, $i - $j);
        $parts[] = $email[$i];
      }
      else
      {
        $parts[] = mb_substr($email, $j, $i - $j + 1);
      }

      $j = $i + 1;
    }

    $i++;
  }

  if (isset($parts[0]))
  {
    $lines[] = "<script type=\"text/javascript\">function sendMail".$unique_function_name_ending."(link) {";
    $lines[] = "mailto = \"".$parts[0]."\";";
    $_email .= "mailto = \"".$parts[0]."\";";

    for ($k = 1; $k < count($parts); $k++)
    {
      if ($parts[$k] == "@") $parts[$k] = $doggy_replacer;

      $lines[] = "mailto+=\"".$parts[$k]."\";";
      $_email .= "mailto+=\"".$parts[$k]."\";";
    }

    $_email = "<script type=\"text/javascript\">".$_email."document.write(mailto);</script>";

    $lines[] = "link.href=\"mailto:\"+mailto;";
    $lines[] = "return true;";
    $lines[] = "}</script>";
  }

  return array("lines" => $lines, "script" => join("\n", $lines), "email" => $_email, "a" => $a);
}

function transliterate_rus_to_eng($text)
{
	$trans_table = array(
		"клу" => "clu",
		"кат" => "cat",
		"ком" => "com",
		"кон" => "con",
		"цц" => "zz",
		"ый" => "y",
		"а" => "a",
		"б" => "b",
		"в" => "v",
		"г" => "g",
		"д" => "d",
		"е" => "e",
		"ё" => "e",
		"ж" => "zh",
		"з" => "z",
		"и" => "i",
		"й" => "y",
		"к" => "k",
		"л" => "l",
		"м" => "m",
		"н" => "n",
		"о" => "o",
		"п" => "p",
		"р" => "r",
		"с" => "s",
		"т" => "t",
		"у" => "u",
		"ф" => "f",
		"х" => "h",
		"ц" => "ts",
		"ч" => "ch",
		"ш" => "sh",
		"щ" => "sch",
		"ъ" => "",
		"ы" => "y",
		"ь" => "",
		"э" => "e",
		"ю" => "yu",
		"я" => "ya",
		" " => "_",

		"КЛУ" => "clu",
		"КАТ" => "cat",
		"КОМ" => "com",
		"КОН" => "con",
		"ЦЦ" => "zz",
		"ЫЙ" => "y",
		"А" => "a",
		"Б" => "b",
		"В" => "v",
		"Г" => "g",
		"Д" => "d",
		"Е" => "e",
		"Ё" => "e",
		"Ж" => "zh",
		"З" => "z",
		"И" => "i",
		"Й" => "y",
		"К" => "k",
		"Л" => "l",
		"М" => "m",
		"Н" => "n",
		"О" => "o",
		"П" => "p",
		"Р" => "r",
		"С" => "s",
		"Т" => "t",
		"У" => "u",
		"Ф" => "f",
		"Х" => "h",
		"Ц" => "ts",
		"Ч" => "ch",
		"Ш" => "sh",
		"Щ" => "sch",
		"Ъ" => "",
		"Ы" => "y",
		"Ь" => "",
		"Э" => "e",
		"Ю" => "yu",
		"Я" => "ya",
	);

	$text = mb_strtolower($text);
	return str_replace(array_keys($trans_table), array_values($trans_table), $text);
}

function get_user_ip()
{
	return \diRequest::server("REMOTE_ADDR");

	if (!empty($_SERVER["HTTP_CLIENT_IP"]))
	{
		$ip = $_SERVER["HTTP_CLIENT_IP"];
	}
	elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
	{
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	else
	{
		$ip = $_SERVER["REMOTE_ADDR"];
	}

	return $ip;
}

//
function dierror($text, $status = DIE_FATAL)
{
  $types = array(
    DIE_NOTICE => "Notice: ",
    DIE_WARNING => "Warning: ",
    DIE_FATAL => "Fatal error: ",
  );

  // file stuff
  $ip = get_user_ip();
  $host = $ip ? gethostbyaddr($ip) : '';
  $r = \diRequest::referrer();

  $f = fopen(getLogFolder() . "log/".date("Y_m_d")."-errors.txt", "a");
  fputs($f, date("d.m.Y H:i:s") . ", $ip ($host), uri: " . \diRequest::requestUri() .
	  ", ref: $r, agent: " . \diRequest::server("HTTP_USER_AGENT") . "\n$text\n\n");
  fclose($f);
  //

  if ($status == DIE_FATAL)
    die("<br /><br /><b>{$types[$status]}</b> $text");
  else
    echo("<br /><br /><b>{$types[$status]}</b> $text");
}

if (!function_exists("htmlspecialchars_decode"))
{
  function htmlspecialchars_decode($text)
  {
    return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
  }
}

function imagefliphorizontal($image)
{
  $w = imagesx($image);
  $h = imagesy($image);

  $flipped = imagecreatetruecolor($w, $h);

  for ($x = 0; $x < $w; $x++)
  {
    imagecopy($flipped, $image, $x, 0, $w - $x - 1, 0, 1, $h);
  }

  return $flipped;
}

function imageflipvertical($image)
{
  $w = imagesx($image);
  $h = imagesy($image);

  $flipped = imagecreatetruecolor($w, $h);

  for ($y = 0; $y < $h; $y++)
  {
    imagecopy($flipped, $image, 0, $y, 0, $h - $y - 1, $w, 1);
  }

  return $flipped;
}

function get_unique_id($length = 32)
{
	srand((double)microtime() * 1000000);
	$hash = md5(rand(0, 9999999));

	return $length > 0 && $length < 32
		? substr($hash, 0, $length)
		: $hash;
}

function rgb_color($color)
{
  if (is_string($color))
  {
    if (substr($color, 0, 1) == "#") $color = substr($color, 1);

    return array(
      hexdec(substr($color, 0, 2)),
      hexdec(substr($color, 2, 2)),
      hexdec(substr($color, 4, 2))
    );
  }
  else
  {
    return $color;
  }
}

function rgb_allocate($image, $color)
{
  list($r, $g, $b) = rgb_color($color);

  $index = imagecolorexact($image, $r, $g, $b);

  return ($index == -1 ? imagecolorallocate($image, $r, $g, $b) : $index);
}

function digit_case($x, $s1, $s2, $s3 = false, $return_only_string = false)
{
  if ($s3 === false) $s3 = $s2;

  $x0 = $x;
  $x = $x % 100;

  if ($x % 10 == 1 && $x != 11)
    return $return_only_string ? $s1 : "$x0 $s1";
  elseif ($x % 10 >= 2 && $x % 10 <= 4 && $x != 12 && $x != 13 && $x != 14)
    return $return_only_string ? $s2 : "$x0 $s2";
  else
    return $return_only_string ? $s3 : "$x0 $s3";
}

function pad_left($s, $len, $char)
{
  while (mb_strlen($s) < $len)
    $s = $char.$s;

  return $s;
}

function escape_tpl_brackets($s)
{
  return str_replace(array("{","}"),array("&#123;","&#125;"),$s);
}

function fix_anchors($s)
{
  return preg_replace('/\<a([^\>]+)href[\x20\t]*\=[\x20\t]*[\'\"]?\#([^\'\"]+)[\'\"]?([^\>]*)\>/i', '<a\\1href="'.\diRequest::requestUri().'#\\2"\\3>', $s);
}

/** @deprecated */
function imagecreate_func_name($img_type)
{
	return diImage::createFunction($img_type);
}

/** @deprecated */
function imagestore_func_name($img_type)
{
	return diImage::storeFunction($img_type);
}

function str_to_upper($str)
{
  return strtr($str,
  "abcdefghijklmnopqrstuvwxyz".
  "\xE0\xE1\xE2\xE3\xE4\xE5".
  "\xb8\xe6\xe7\xe8\xe9\xea".
  "\xeb\xeC\xeD\xeE\xeF\xf0".
  "\xf1\xf2\xf3\xf4\xf5\xf6".
  "\xf7\xf8\xf9\xfA\xfB\xfC".
  "\xfD\xfE\xfF",
  "ABCDEFGHIJKLMNOPQRSTUVWXYZ".
  "\xC0\xC1\xC2\xC3\xC4\xC5".
  "\xA8\xC6\xC7\xC8\xC9\xCA".
  "\xCB\xCC\xCD\xCE\xCF\xD0".
  "\xD1\xD2\xD3\xD4\xD5\xD6".
  "\xD7\xD8\xD9\xDA\xDB\xDC".
  "\xDD\xDE\xDF"
  );
}

function str_to_lower($str)
{
  return strtr($str,
  "ABCDEFGHIJKLMNOPQRSTUVWXYZ".
  "\xC0\xC1\xC2\xC3\xC4\xC5".
  "\xA8\xC6\xC7\xC8\xC9\xCA".
  "\xCB\xCC\xCD\xCE\xCF\xD0".
  "\xD1\xD2\xD3\xD4\xD5\xD6".
  "\xD7\xD8\xD9\xDA\xDB\xDC".
  "\xDD\xDE\xDF",
  "abcdefghijklmnopqrstuvwxyz".
  "\xE0\xE1\xE2\xE3\xE4\xE5".
  "\xb8\xe6\xe7\xe8\xe9\xea".
  "\xeb\xeC\xeD\xeE\xeF\xf0".
  "\xf1\xf2\xf3\xf4\xf5\xf6".
  "\xf7\xf8\xf9\xfA\xfB\xfC".
  "\xfD\xfE\xfF"
  );
}

function di_ucwords($s)
{
  $break = 1;
  $s2 = "";

  for ($i = 0; $i < mb_strlen($s); $i++)
  {
    $ch = $s{$i};

    if ((ord($ch) > 64 && ord($ch) < 123) || (ord($ch) > 48 && ord($ch) < 58) || (ord($ch) >= 192 && ord($ch) <= 255) || ord($ch) == 184 || ord($ch) == 168)
    {
      if ($break) $s2 .= mb_strtoupper($ch);
      else $s2 .= mb_strtolower($ch);

      $break = 0;
    }
    else
    {
      $s2 .= $ch;
      $break = 1;
    }
  }

  return $s2;
}

function json_encode2($a = false)
{
  if (is_null($a)) return 'null';
  if ($a === false) return 'false';
  if ($a === true) return 'true';
  if (is_scalar($a))
  {
    if (is_float($a))
    {
      // Always use "." for floats.
      return floatval(str_replace(",", ".", strval($a)));
    }

    if (is_string($a))
    {
      static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
      return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
    }
    else
      return $a;
  }
  $isList = true;
  for ($i = 0, reset($a); $i < count($a); $i++, next($a))
  {
    if (key($a) !== $i)
    {
      $isList = false;
      break;
    }
  }
  $result = array();
  if ($isList)
  {
    foreach ($a as $v) $result[] = json_encode2($v);
    return '[' . join(',', $result) . ']';
  }
  else
  {
    foreach ($a as $k => $v) $result[] = json_encode2($k).':'.json_encode2($v);
    return '{' . join(',', $result) . '}';
  }
}

if (!function_exists('json_encode'))
{
  function json_encode($a = false)
  {
    return json_encode2($a);
  }
}

function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0)
{
  $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t](//).*)#", "", $json);

  if (version_compare(phpversion(), '5.4.0', '>='))
    $json = json_decode($json, $assoc, $depth, $options);
  elseif (version_compare(phpversion(), '5.3.0', '>='))
    $json = json_decode($json, $assoc, $depth);
  else
    $json = json_decode($json, $assoc);

  return $json;
}

function get_1000_path($id)
{
  $id = strval($id);
  $id = str_repeat("0", 9 - mb_strlen($id)).$id;

  $path = mb_substr($id, 0, 3)."/".mb_substr($id, 3, 3)."/".mb_substr($id, 6, 3)."/";

  return $path;
}

function word_wrap($s, $len, $divider = " ")
{
  $r_char = ">";

  $lines = array("");
  $lines2 = array("");

  $ar0 = preg_split("/[ \r\n]/", $s);
  $ar1 = array();
  $ar2 = array();
  $ar3 = array();

  for ($i = 0; $i < count($ar0); $i++)
  {
    if ($ar0[$i] == "") continue;

    $ar1[] = $ar0[$i];
    $ar2[] = preg_replace("/\&(\#[0-9]{2,5}|[a-zA-Z]{2,8})\;/", $r_char, $ar0[$i]);

    preg_match_all("/\&(\#[0-9]{2,5}|[a-zA-Z]{2,8})\;/", $ar0[$i], $r);
    $ar3[] = $r[0];
  }

  for ($i = 0; $i < count($ar1); $i++)
  {
    if (mb_strlen($lines2[count($lines2) - 1]) + 1 + mb_strlen($ar2[$i]) <= $len)
    {
      $lines[count($lines) - 1] .= " ".$ar1[$i];
      $lines2[count($lines2) - 1] .= " ".$ar2[$i];
    }
    else
    {
      if (mb_strlen($ar2[$i]) <= $len)
      {
        $lines[count($lines)] = $ar1[$i];
        $lines2[count($lines2)] = $ar2[$i];
      }
      else
      {
        $cc = 0;

        while (mb_strlen($ar2[$i]) > 0)
        {
          $tmp1 = substr($ar2[$i], 0, $len);
          $tmp2 = $tmp1;

          for ($j = 0; $j < mb_strlen($tmp1); $j++)
          {
            if (mb_substr($tmp1, $j, 1) == $r_char)
            {
              $tmp1 = mb_substr($tmp1, 0, $j).$ar3[$i][$cc].mb_substr($tmp1, $j + 1);
              $j += mb_strlen($ar3[$i][$cc]) - 1;
              $cc++;
            }
          }

          $ar1[$i] = mb_substr($ar1[$i], mb_strlen($tmp1));
          $ar2[$i] = mb_substr($ar2[$i], mb_strlen($tmp2));

          $lines[count($lines)] = $tmp1;
          $lines2[count($lines2)] = $tmp2;
        }
      }
    }
  }

  return join($divider, $lines);
}

function utime()
{
  $time = explode(" ", microtime());
  $usec = (double)$time[0];
  $sec = (double)$time[1];
  return $sec + $usec;
}

/** @deprecated  */
function replace_file_ext($fn, $new_ext = "")
{
	return \diCore\Helper\StringHelper::replaceFileExtension($fn, $new_ext);
}

function ip2bin($ip = null)
{
	if ($ip === null)
	{
		$ip = get_user_ip();
	}

	//$ips = explode('.', $ip);
	//return ($ips[3] | $ips[2] << 8 | $ips[1] << 16 | $ips[0] << 24);
	return sprintf('%u', ip2long($ip));
}

function bin2ip($bin)
{
  return long2ip($bin);
}

/** @deprecated */
function time_passed_by($timestamp, $now = null)
{
	return diDateTime::passedBy($timestamp, $now);
}

function size_in_bytes($size, $mb = "Mb", $kb = "kb", $b = " bytes")
{
	if ($size > 1073741824) return (round($size * 10 / 1073741824) / 10)."Gb";
	elseif ($size > 1048576) return (round($size * 10 / 1048576) / 10).$mb;
	elseif ($size > 1024) return (round($size * 10 / 1024) / 10).$kb;
	else return $size.$b;
}

function str_filesize($size)
{
  return size_in_bytes($size, "Мб", "кб", " байт");
}

function get_age($d, $m, $y)
{
  return $y ? date("Y") - $y - (date("md") < lead0($m).lead0($d) ? 1 : 0) : 0;
}

function clean_filename($fn)
{
  $fn = transliterate_rus_to_eng($fn);
  $fn = preg_replace("/[^a-zA-Z0-9-\._\(\)\[\]]/", "", $fn);

  return $fn ? $fn : "New_folder";
}

function get_uri_glue($uri)
{
  return strpos($uri, "?") === false ? "?" : "&";
}

/* classes stuff */

/** @deprecated */
function get_path_to_classes($prefix, $root = null)
{
	$root = $root ?: \diCore\Data\Config::getConfigurationFolder();

	$path = $root."/_cfg/classes/";

	if ($prefix) $path .= add_ending_slash($prefix);

	return $path;
}

if (!function_exists("require_class"))
{
	/** @deprecated */
	function require_class($class_name, $path_prefix = "")
	{
		return diLib::inc($class_name, $path_prefix);
	}
}

/** @deprecated */
function require_interface($interface_name, $path_prefix = "")
{
  require_once get_path_to_classes($path_prefix) . "_interface_" . mb_strtolower($interface_name) . ".php";
}

// ----------------------------------------------------------------------

function check_uploaded_file($full_fn, $orig_fn = "", $types_ar = array())
{
  $typed_allowed_ext_ar = array(
    "pic" => array("jpeg","jpg","png","gif","swf"),
    "audio" => array("mp3","ogg","ac3"),
    "video" => array("avi","flv","mp4"),
    "arc" => array("rar","zip","gz"),
    "office" => array("doc","docx","xls","xlsx","pdf"),
  );

  if ($types_ar && !is_array($types_ar))
    $types_ar = array($types_ar);

  $ar = array();

  if ($types_ar)
  {
    $ar = array();

    foreach ($types_ar as $t)
    {
      if (isset($typed_allowed_ext_ar[$t]))
        $ar = array_merge($ar, $typed_allowed_ext_ar[$t]);
    }
  }
  else
  {
    foreach ($typed_allowed_ext_ar as $k => $v)
    {
      $ar = array_merge($ar, $v);
    }
  }

  $ext = mb_strtolower(get_file_ext($orig_fn ? $orig_fn : $full_fn));

  return in_array($ext, $ar);
}

function escape_bad_html($s, $allowed = "p|br|b|i|u|a|img|object|embed|param|iframe")
{
  $s = preg_replace("/<((?!\/?($allowed)\b)[^>]*)>/xis", '&lt;\1&gt;', $s);

  preg_match_all("/<iframe[^>]*>/", $s, $regs);

  foreach ($regs[0] as $tag)
  {
    if (strpos($tag, " src=\"http://www.youtube.com/") === false)
      $s = str_replace($tag, str_out($tag), $s);
  }

  return $s;
}

/** @deprecated */
function wysiwyg_empty($s)
{
	return diStringHelper::wysiwygEmpty($s);
}

function print_json($ar, $printHeaders = true)
{
	global $html_encodings_ar;

	//text/plain
	if ($printHeaders)
	{
		header("Content-type: application/json; charset={$html_encodings_ar[DIENCODING]}");
		header("Expires: Mon, 11 Jul 1999 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
	}

	echo json_encode2($ar);
}

function dierror2($text, $module = "")
{
  $ip = get_user_ip();
  $host = gethostbyaddr($ip);

  if ($module)
    $module = "[$module]";

  $f = fopen(getLogFolder() . "log/".date("Y_m_d")."-errors.txt", "a");
  fputs($f, date("d.m.Y H:i:s") . " $module $ip ($host), uri: " . \diRequest::requestUri() .
	  ", agent: " . \diRequest::server("HTTP_USER_AGENT") . "\n$text\n\n");
  fclose($f);

  die("$text");
}

function getLogFolder()
{
	return \diCore\Data\Config::getLogFolder();
}

function simple_debug($message, $module = "", $fnSuffix = "")
{
	$fn = getLogFolder() . "log/debug/".date("Y_m_d").$fnSuffix.".txt";

	if ($module)
	{
		$module = " [$module]";
	}

	$f = fopen($fn, "a");
	fputs($f, date("[d.m.Y H:i:s]")."{$module} $message\n");
	fclose($f);

	chmod($fn, 0777);
}

function var_debug()
{
	$fn = getLogFolder() . "log/debug/".date("Y_m_d").".txt";

	$f = fopen($fn, "a");
	fputs($f, date("[d.m.Y H:i:s] ").var_export(func_get_args(), true)."\n");
	fclose($f);

	chmod($fn, 0777);
}

function cron_debug($script)
{
	$fn = getLogFolder() . "log/cron/".date("Y_m_d").".txt";

	$f = fopen($fn, "a");
	fputs($f, date("[d.m.Y H:i:s]")." {$script}\n");
	fclose($f);

	chmod($fn, 0777);
}

function extend()
{
	$args = func_get_args();
	$extended = array();

	if (is_array($args) && count($args))
	{
		foreach ($args as $array)
		{
			if (is_array($array) || is_object($array))
			{
				$extended = array_replace($extended, (array)$array);
			}
		}
	}

	return $extended;
}

function utf($s)
{
  return iconv("cp1251", "utf-8", $s);
}

function _utf($s)
{
  return iconv("utf-8", "cp1251", $s);
}

function ee($s)
{
	return str_replace(array("ё", "Ё"), array("е", "Е"), $s);
}

function lc_all_but_first_letters($s, $only_sentence_uc = false)
{
    $space_ar = array(" ", "\t", "\n", "\r");
    $sentence_end_ar = array(false, ".", ",", "?", "!");
    $word_end_ar = array_merge($space_ar, $sentence_end_ar);

    $prev_ar = array();
    $s2 = "";

    $uc_allowed = function($prev_ar) use($only_sentence_uc, $space_ar, $sentence_end_ar, $word_end_ar) {
		if (count($prev_ar) == 0)
			return true;

    	if (!$only_sentence_uc)
    		return in_array($prev_ar[count($prev_ar) - 1], $word_end_ar, true);

		for ($i = count($prev_ar) - 1; $i >= 0; $i--)
		{
			if (in_array($prev_ar[$i], $space_ar, true))
				continue;

			if (in_array($prev_ar[$i], $sentence_end_ar, true))
				return true;

			return false;
		}

	    return false;
    };

	for ($i = 0; $i < mb_strlen($s); $i++)
	{
	    $c = mb_substr($s, $i, 1);

		$s2 .= $uc_allowed($prev_ar) ? $c : mb_strtolower($c);

		$prev_ar[] = $c;
	}

	return $s2;
}

function camelize($scored, $lcFirst = true)
{
	$s = implode("", array_map("ucfirst", array_map("strtolower", explode("_", $scored))));

	return $lcFirst ? lcfirst($s) : $s;
}

function underscore($cameled)
{
	return implode("_", array_map("strtolower", preg_split('/([A-Z]{1}[^A-Z]*)/', $cameled, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY)));
}

if (!function_exists('http_build_url'))
{
	define('HTTP_URL_REPLACE', 1);              // Replace every part of the first URL when there's one of the second URL
	define('HTTP_URL_JOIN_PATH', 2);            // Join relative paths
	define('HTTP_URL_JOIN_QUERY', 4);           // Join query strings
	define('HTTP_URL_STRIP_USER', 8);           // Strip any user authentication information
	define('HTTP_URL_STRIP_PASS', 16);          // Strip any password authentication information
	define('HTTP_URL_STRIP_AUTH', 32);          // Strip any authentication information
	define('HTTP_URL_STRIP_PORT', 64);          // Strip explicit port numbers
	define('HTTP_URL_STRIP_PATH', 128);         // Strip complete path
	define('HTTP_URL_STRIP_QUERY', 256);        // Strip query string
	define('HTTP_URL_STRIP_FRAGMENT', 512);     // Strip any fragments (#identifier)
	define('HTTP_URL_STRIP_ALL', 1024);         // Strip anything but scheme and host

	/**
	 * Build a URL.
	 *
	 * The parts of the second URL will be merged into the first according to
	 * the flags argument.
	 *
	 * @param mixed $url (part(s) of) an URL in form of a string or
	 *                       associative array like parse_url() returns
	 * @param mixed $parts same as the first argument
	 * @param int $flags a bitmask of binary or'ed HTTP_URL constants;
	 *                       HTTP_URL_REPLACE is the default
	 * @param array $new_url if set, it will be filled with the parts of the
	 *                       composed url like parse_url() would return
	 * @return string
	 */
	function http_build_url($url, $parts = array(), $flags = HTTP_URL_REPLACE, &$new_url = array())
	{
		is_array($url) || $url = parse_url($url);
		is_array($parts) || $parts = parse_url($parts);

		isset($url['query']) && is_string($url['query']) || $url['query'] = null;
		isset($parts['query']) && is_string($parts['query']) || $parts['query'] = null;

		$keys = array('user', 'pass', 'port', 'path', 'query', 'fragment');

		// HTTP_URL_STRIP_ALL and HTTP_URL_STRIP_AUTH cover several other flags.
		if ($flags & HTTP_URL_STRIP_ALL)
		{
			$flags |= HTTP_URL_STRIP_USER | HTTP_URL_STRIP_PASS
				| HTTP_URL_STRIP_PORT | HTTP_URL_STRIP_PATH
				| HTTP_URL_STRIP_QUERY | HTTP_URL_STRIP_FRAGMENT;
		}
		elseif ($flags & HTTP_URL_STRIP_AUTH)
		{
			$flags |= HTTP_URL_STRIP_USER | HTTP_URL_STRIP_PASS;
		}

		// Schema and host are alwasy replaced
		foreach (array('scheme', 'host') as $part)
		{
			if (isset($parts[$part]))
			{
				$url[$part] = $parts[$part];
			}
		}

		if ($flags & HTTP_URL_REPLACE)
		{
			foreach ($keys as $key)
			{
				if (isset($parts[$key]))
				{
					$url[$key] = $parts[$key];
				}
			}
		}
		else
		{
			if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH))
			{
				if (isset($url['path']) && substr($parts['path'], 0, 1) !== '/')
				{
					// Workaround for trailing slashes
					$url['path'] .= 'a';
					$url['path'] = rtrim(
							str_replace(basename($url['path']), '', $url['path']),
							'/'
						) . '/' . ltrim($parts['path'], '/');
				}
				else
				{
					$url['path'] = $parts['path'];
				}
			}

			if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY))
			{
				if (isset($url['query']))
				{
					parse_str($url['query'], $url_query);
					parse_str($parts['query'], $parts_query);

					$url['query'] = http_build_query(
						array_replace_recursive(
							$url_query,
							$parts_query
						)
					);
				}
				else
				{
					$url['query'] = $parts['query'];
				}
			}
		}

		if (isset($url['path']) && $url['path'] !== '' && substr($url['path'], 0, 1) !== '/')
		{
			$url['path'] = '/' . $url['path'];
		}

		foreach ($keys as $key)
		{
			$strip = 'HTTP_URL_STRIP_' . strtoupper($key);
			if ($flags & constant($strip))
			{
				unset($url[$key]);
			}
		}

		$parsed_string = '';

		if (!empty($url['scheme']))
		{
			$parsed_string .= $url['scheme'] . '://';
		}

		if (!empty($url['user']))
		{
			$parsed_string .= $url['user'];

			if (isset($url['pass']))
			{
				$parsed_string .= ':' . $url['pass'];
			}

			$parsed_string .= '@';
		}

		if (!empty($url['host']))
		{
			$parsed_string .= $url['host'];
		}

		if (!empty($url['port']))
		{
			$parsed_string .= ':' . $url['port'];
		}

		if (!empty($url['path']))
		{
			$parsed_string .= $url['path'];
		}

		if (!empty($url['query']))
		{
			$parsed_string .= '?' . $url['query'];
		}

		if (!empty($url['fragment']))
		{
			$parsed_string .= '#' . $url['fragment'];
		}

		$new_url = $url;

		return $parsed_string;
	}
}

function utf8_wordwrap($string, $width = 75, $break = "\n", $cut = false)
{
	if ($cut)
	{
		// Match anything 1 to $width chars long followed by whitespace or EOS,
		// otherwise match anything $width chars long
		$search = '/(.{1,'.$width.'})(?:\s|$)|(.{'.$width.'})/uS';
		$replace = '$1$2'.$break;
	}
	else
	{
		// Anchor the beginning of the pattern with a look ahead
		// to avoid crazy backtracking when words are longer than $width
		$search = '/(?=\s)(.{1,'.$width.'})(?:\s|$)/uS';
		$replace = '$1'.$break;
	}

	return preg_replace($search, $replace, $string);
}