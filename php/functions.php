<?php
// (q) dimaninc
// this file should be compatible with 5.6

require dirname(__FILE__) . '/lib/diLib.php';

use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

$html_encodings_ar = [
    'CP1251' => 'windows-1251',
    'UTF8' => 'utf-8',
];

// -[ locale stuff ]----------------------------------------------------------------
define('DIENCODING', 'UTF8');

setlocale(LC_COLLATE, 'ru_RU.' . DIENCODING);
setlocale(LC_CTYPE, 'ru_RU.' . DIENCODING);

mb_internal_encoding('UTF-8');
//

// for dierror
define('DIE_NOTICE', 0);
define('DIE_WARNING', 1);
define('DIE_FATAL', 2);

define('SECS_PER_DAY', 86400);

$days_in_mon_ar = [
    false => [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
    true => [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
];

$month_titles = [
    'января',
    'февраля',
    'марта',
    'апреля',
    'мая',
    'июня',
    'июля',
    'августа',
    'сентября',
    'октября',
    'ноября',
    'декабря',
];

$eng_month_titles = [
    'january',
    'february',
    'march',
    'april',
    'may',
    'june',
    'july',
    'august',
    'september',
    'october',
    'november',
    'december',
];

$months_titles_ar = [
    'Январь',
    'Февраль',
    'Март',
    'Апрель',
    'Май',
    'Июнь',
    'Июль',
    'Август',
    'Сентябрь',
    'Октябрь',
    'Ноябрь',
    'Декабрь',
];

$eng_months_titles_ar = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

$months_titles_ar2 = [
    'Января',
    'Февраля',
    'Марта',
    'Апреля',
    'Мая',
    'Июня',
    'Июля',
    'Августа',
    'Сентября',
    'Октября',
    'Ноября',
    'Декабря',
];

$wd_ar = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

$rus_months_ar = [
    1 => 'Январь',
    2 => 'Февраль',
    3 => 'Март',
    4 => 'Апрель',
    5 => 'Май',
    6 => 'Июнь',
    7 => 'Июль',
    8 => 'Август',
    9 => 'Сентябрь',
    10 => 'Октябрь',
    11 => 'Ноябрь',
    12 => 'Декабрь',
];

$rus_months2_ar = [
    1 => 'января',
    2 => 'февраля',
    3 => 'марта',
    4 => 'апреля',
    5 => 'мая',
    6 => 'июня',
    7 => 'июля',
    8 => 'августа',
    9 => 'сентября',
    10 => 'октября',
    11 => 'ноября',
    12 => 'декабря',
];

$prepare_word_trans_table = [
    'клу' => 'clu',
    'кат' => 'cat',
    'ком' => 'com',
    'кон' => 'con',
    'цц' => 'zz',
    'ый' => 'y',
    'а' => 'a',
    'б' => 'b',
    'в' => 'v',
    'г' => 'g',
    'д' => 'd',
    'е' => 'e',
    'ё' => 'e',
    'ж' => 'zh',
    'з' => 'z',
    'и' => 'i',
    'й' => 'y',
    'к' => 'k',
    'л' => 'l',
    'м' => 'm',
    'н' => 'n',
    'о' => 'o',
    'п' => 'p',
    'р' => 'r',
    'с' => 's',
    'т' => 't',
    'у' => 'u',
    'ф' => 'f',
    'х' => 'h',
    'ц' => 'ts',
    'ч' => 'ch',
    'ш' => 'sh',
    'щ' => 'sch',
    'ъ' => '',
    'ы' => 'y',
    'ь' => '',
    'э' => 'e',
    'ю' => 'yu',
    'я' => 'ya',
    "$" => 's',
    '0' => 'o',
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

function get_pics_folder($table, $assetsFolder = 'uploads/')
{
    if (isset($GLOBALS[$table . '_pics_folder'])) {
        return $GLOBALS[$table . '_pics_folder'];
    }

    return StringHelper::slash($assetsFolder) . $table . '/';
}

function get_files_folder($table, $assetsFolder = 'uploads/')
{
    if (isset($GLOBALS[$table . '_files_folder'])) {
        return $GLOBALS[$table . '_files_folder'];
    }

    return StringHelper::slash($assetsFolder) . $table . '/files/';
}

function get_tn_folder($index = '', $folderBase = 'preview')
{
    if ($index < 2) {
        $index = '';
    }

    return $folderBase . $index . '/';
}

function get_orig_folder()
{
    global $orig_folder;

    return isset($orig_folder) ? $orig_folder : 'orig/';
}

function get_big_folder()
{
    global $big_folder;

    return isset($big_folder) ? $big_folder : 'big/';
}

function get_tmp_folder()
{
    global $tmp_folder;

    return isset($tmp_folder) ? $tmp_folder : 'uploads/tmp/';
}

function getSettingsFolder()
{
    global $settings_folder;

    return isset($settings_folder) ? $settings_folder : 'uploads/settings/';
}

function getFilesFolder()
{
    global $files_folder;

    return isset($files_folder) ? $files_folder : 'files/';
}

function getDynamicPicsFolder()
{
    if (isset($GLOBALS['dynamic_pics_folder'])) {
        return $GLOBALS['dynamic_pics_folder'];
    }

    return 'uploads/dynamic_pics/';
}

/** @deprecated */
function str_in($str)
{
    return StringHelper::in($str);
}

/** @deprecated */
function str_out($str, $replaceAmp = false)
{
    return StringHelper::out($str, $replaceAmp);
}

/*
 * Is integer (true for both string and number values)
 */
function isInteger($value)
{
    if (is_null($value)) {
        return false;
    }

    if (!is_scalar($value)) {
        return false;
    }

    if (is_int($value)) {
        return true;
    }

    // negative support
    if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
        return true;
    }

    return ctype_digit($value);
}

/*
 * Is number (string number will return false)
 */
function isNumber($input)
{
    if (is_numeric($input) && !is_string($input)) {
        return true;
    }

    return false;
}

function is_not_null($value)
{
    return !is_null($value);
}

function lead0($num)
{
    if (mb_strlen($num) == 1) {
        $num = '0' . $num;
    }

    return $num;
}

/** @deprecated  */
function add_ending_slash($path)
{
    return StringHelper::slash($path);
}

/** @deprecated  */
function remove_ending_slash($path)
{
    return StringHelper::unslash($path);
}

/** @deprecated  */
function create_folders_chain($start_path, $path_to_create, $mod = 0775)
{
    FileSystemHelper::createTree($start_path, $path_to_create, $mod);
}

/** @deprecated */
function isLeapYear($year)
{
    return diDateTime::isLeapYear($year);
}

/** @deprecated  */
function highlight_urls($text, $cut_len = 0, $cut_all_words = false, $tagAttrs = [])
{
    return StringHelper::wrapUrlWithTag($text, [
        'cutLength' => $cut_len,
        'cutAllWords' => $cut_all_words,
        'tagAttrs' => $tagAttrs,
    ]);
}

/** @deprecated  */
function divide3dig($s, $divider = ',')
{
    return StringHelper::divideThousands($s, $divider);
}

/** @deprecated */
function is_email_valid($email)
{
    return diEmail::isValid($email);
}

function is_back_valid($back)
{
    return !preg_match('/^(https?:\/\/|ftp:\/\/|mailto:)/i', ltrim($back));
}

// ** header encoding added
// each element in attachment_ar should look like this:
// [0] => array(
//          "filename" => "filename.jpg",
//          "content_type" => "image/jpeg",
//          "data" => "[binary_data]"),
/** @deprecated */
function send_email(
    $from,
    $to,
    $subject,
    $message,
    $body_html,
    $attachment_ar = false
) {
    return diEmail::fastSend(
        $from,
        $to,
        $subject,
        $message,
        $body_html,
        $attachment_ar
    );
}

/** @deprecated  */
function str_cut_end($s, $max_len, $trailer = '...')
{
    return StringHelper::cutEnd($s, $max_len, $trailer);
}

/** @deprecated  */
function smart_str_cut_end($s, $max_len, $trailer = '...', $is_utf8 = false)
{
    return StringHelper::smartCutEnd($s, $max_len, $trailer, $is_utf8);
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
    return FileSystemHelper::folderContents($sPath, $dir_in_filename, $recursive);
}

if (!function_exists('glob_recursive')) {
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (
            glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT)
            as $dir
        ) {
            $files = array_merge(
                $files,
                glob_recursive($dir . '/' . basename($pattern), $flags)
            );
        }

        return $files;
    }
}

/** @deprecated  */
function get_file_ext($fn)
{
    return StringHelper::fileExtension($fn);
}

// returns an array:
//  "lines" => array of "script" lines
//  "script" => javascript with <script> tags for sending email
//  "email" => e-mail address escaped, so fuken spammerz couldn't know it
//  "a" => array of <a> tag elements "onclick" and "href"
function publish_email(
    $email,
    $doggy_replacer = '&#64',
    $unique_function_name_ending = ''
) {
    $lines = [];
    $a['onclick'] =
        "onclick=\"sendMail" . $unique_function_name_ending . "(this);\"";
    $a['href'] = "href=\"#\"";
    $_email = '';

    $email = trim($email);

    $i = 0;
    $j = 0;
    $parts = [];

    while ($i < mb_strlen($email)) {
        if (
            in_array($email[$i], ['@', '.', '_', '-']) ||
            $i == mb_strlen($email) - 1
        ) {
            if ($i < mb_strlen($email) - 1) {
                $parts[] = mb_substr($email, $j, $i - $j);
                $parts[] = $email[$i];
            } else {
                $parts[] = mb_substr($email, $j, $i - $j + 1);
            }

            $j = $i + 1;
        }

        $i++;
    }

    if (isset($parts[0])) {
        $lines[] =
            "<script type=\"text/javascript\">function sendMail" .
            $unique_function_name_ending .
            '(link) {';
        $lines[] = "mailto = \"" . $parts[0] . "\";";
        $_email .= "mailto = \"" . $parts[0] . "\";";

        for ($k = 1; $k < count($parts); $k++) {
            if ($parts[$k] == '@') {
                $parts[$k] = $doggy_replacer;
            }

            $lines[] = "mailto+=\"" . $parts[$k] . "\";";
            $_email .= "mailto+=\"" . $parts[$k] . "\";";
        }

        $_email =
            "<script type=\"text/javascript\">" .
            $_email .
            'document.write(mailto);</script>';

        $lines[] = "link.href=\"mailto:\"+mailto;";
        $lines[] = 'return true;';
        $lines[] = '}</script>';
    }

    return [
        'lines' => $lines,
        'script' => join("\n", $lines),
        'email' => $_email,
        'a' => $a,
    ];
}

function transliterate_rus_to_eng($text, $lowerCase = true)
{
    $trans_table = [
        'клу' => 'clu',
        'кат' => 'cat',
        'ком' => 'com',
        'кон' => 'con',
        'цц' => 'zz',
        'ый' => 'y',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'sch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
        ' ' => '_',

        'КЛУ' => 'CLU',
        'КАТ' => 'CAT',
        'КОМ' => 'COM',
        'КОН' => 'CON',
        'ЦЦ' => 'ZZ',
        'ЫЙ' => 'Y',
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'E',
        'Ж' => 'ZH',
        'З' => 'Z',
        'И' => 'I',
        'Й' => 'Y',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ц' => 'TS',
        'Ч' => 'CH',
        'Ш' => 'SH',
        'Щ' => 'SCH',
        'Ъ' => '',
        'Ы' => 'Y',
        'Ь' => '',
        'Э' => 'E',
        'Ю' => 'YU',
        'Я' => 'YA',
    ];

    if ($lowerCase) {
        $text = mb_strtolower($text);
    }

    return str_replace(array_keys($trans_table), array_values($trans_table), $text);
}

/** @deprecated  */
function get_user_ip()
{
    return \diRequest::getRemoteIp();
}

//
function dierror($text, $status = DIE_FATAL)
{
    $types = [
        DIE_NOTICE => 'Notice: ',
        DIE_WARNING => 'Warning: ',
        DIE_FATAL => 'Fatal error: ',
    ];

    // file stuff
    $ip = get_user_ip();
    $host = $ip ? gethostbyaddr($ip) : '';
    $r = \diRequest::referrer();

    $f = fopen(getLogFolder() . 'log/' . date('Y_m_d') . '-errors.txt', 'a');
    fputs(
        $f,
        date('d.m.Y H:i:s') .
            ", $ip ($host), uri: " .
            \diRequest::requestUri() .
            ", ref: $r, agent: " .
            \diRequest::server('HTTP_USER_AGENT') .
            "\n$text\n\n"
    );
    fclose($f);
    //

    if ($status == DIE_FATAL) {
        die("<br /><br /><b>{$types[$status]}</b> $text");
    } else {
        echo "<br /><br /><b>{$types[$status]}</b> $text";
    }
}

if (!function_exists('htmlspecialchars_decode')) {
    function htmlspecialchars_decode($text)
    {
        return strtr(
            $text,
            array_flip(get_html_translation_table(HTML_SPECIALCHARS))
        );
    }
}

function imagefliphorizontal($image)
{
    $w = imagesx($image);
    $h = imagesy($image);

    $flipped = imagecreatetruecolor($w, $h);

    for ($x = 0; $x < $w; $x++) {
        imagecopy($flipped, $image, $x, 0, $w - $x - 1, 0, 1, $h);
    }

    return $flipped;
}

function imageflipvertical($image)
{
    $w = imagesx($image);
    $h = imagesy($image);

    $flipped = imagecreatetruecolor($w, $h);

    for ($y = 0; $y < $h; $y++) {
        imagecopy($flipped, $image, 0, $y, 0, $h - $y - 1, $w, 1);
    }

    return $flipped;
}

function get_unique_id($length = 32)
{
    srand(intval((float) microtime() * 1000000));
    $hash = md5(rand(0, 9999999));

    return $length > 0 && $length < 32 ? substr($hash, 0, $length) : $hash;
}

function rgb_color($color)
{
    if (is_string($color)) {
        if (substr($color, 0, 1) == '#') {
            $color = substr($color, 1);
        }

        return [
            hexdec(substr($color, 0, 2)),
            hexdec(substr($color, 2, 2)),
            hexdec(substr($color, 4, 2)),
        ];
    } else {
        return $color;
    }
}

function rgb_allocate($image, $color)
{
    list($r, $g, $b) = rgb_color($color);

    $index = imagecolorexact($image, $r, $g, $b);

    return $index == -1 ? imagecolorallocate($image, $r, $g, $b) : $index;
}

/** @deprecated  */
function digit_case($x, $s1, $s2, $s3 = null, $return_only_string = false)
{
    return StringHelper::digitCase($x, $s1, $s2, $s3, $return_only_string);
}

/** @deprecated  */
function pad_left($s, $len, $char)
{
    return StringHelper::leftPad($s, $len, $char);
}

function escape_tpl_brackets($s)
{
    return str_replace(['{', '}'], ['&#123;', '&#125;'], $s);
}

function fix_anchors($s)
{
    return preg_replace(
        '/\<a([^\>]+)href[\x20\t]*\=[\x20\t]*[\'\"]?\#([^\'\"]+)[\'\"]?([^\>]*)\>/i',
        '<a\\1href="' . \diRequest::requestUri() . '#\\2"\\3>',
        $s
    );
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
    return strtr(
        $str,
        'abcdefghijklmnopqrstuvwxyz' .
            "\xE0\xE1\xE2\xE3\xE4\xE5" .
            "\xb8\xe6\xe7\xe8\xe9\xea" .
            "\xeb\xeC\xeD\xeE\xeF\xf0" .
            "\xf1\xf2\xf3\xf4\xf5\xf6" .
            "\xf7\xf8\xf9\xfA\xfB\xfC" .
            "\xfD\xfE\xfF",
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ' .
            "\xC0\xC1\xC2\xC3\xC4\xC5" .
            "\xA8\xC6\xC7\xC8\xC9\xCA" .
            "\xCB\xCC\xCD\xCE\xCF\xD0" .
            "\xD1\xD2\xD3\xD4\xD5\xD6" .
            "\xD7\xD8\xD9\xDA\xDB\xDC" .
            "\xDD\xDE\xDF"
    );
}

function str_to_lower($str)
{
    return strtr(
        $str,
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ' .
            "\xC0\xC1\xC2\xC3\xC4\xC5" .
            "\xA8\xC6\xC7\xC8\xC9\xCA" .
            "\xCB\xCC\xCD\xCE\xCF\xD0" .
            "\xD1\xD2\xD3\xD4\xD5\xD6" .
            "\xD7\xD8\xD9\xDA\xDB\xDC" .
            "\xDD\xDE\xDF",
        'abcdefghijklmnopqrstuvwxyz' .
            "\xE0\xE1\xE2\xE3\xE4\xE5" .
            "\xb8\xe6\xe7\xe8\xe9\xea" .
            "\xeb\xeC\xeD\xeE\xeF\xf0" .
            "\xf1\xf2\xf3\xf4\xf5\xf6" .
            "\xf7\xf8\xf9\xfA\xfB\xfC" .
            "\xfD\xfE\xfF"
    );
}

function di_ucwords($s)
{
    $break = 1;
    $s2 = '';

    for ($i = 0; $i < mb_strlen($s); $i++) {
        $ch = $s[$i];

        if (
            (ord($ch) > 64 && ord($ch) < 123) ||
            (ord($ch) > 48 && ord($ch) < 58) ||
            (ord($ch) >= 192 && ord($ch) <= 255) ||
            ord($ch) == 184 ||
            ord($ch) == 168
        ) {
            if ($break) {
                $s2 .= mb_strtoupper($ch);
            } else {
                $s2 .= mb_strtolower($ch);
            }

            $break = 0;
        } else {
            $s2 .= $ch;
            $break = 1;
        }
    }

    return $s2;
}

function json_encode2($a = false)
{
    if (is_null($a)) {
        return 'null';
    }
    if ($a === false) {
        return 'false';
    }
    if ($a === true) {
        return 'true';
    }
    if (is_scalar($a)) {
        if (is_float($a)) {
            // Always use "." for floats.
            return floatval(str_replace(',', '.', strval($a)));
        }

        if (is_string($a)) {
            static $jsonReplaces = [
                ['\\', '/', "\n", "\t", "\r", '\b', "\f", '"'],
                ['\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'],
            ];
            return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
        } else {
            return $a;
        }
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
        if (key($a) !== $i) {
            $isList = false;
            break;
        }
    }
    $result = [];
    if ($isList) {
        foreach ($a as $v) {
            $result[] = json_encode2($v);
        }
        return '[' . join(',', $result) . ']';
    } else {
        foreach ($a as $k => $v) {
            $result[] = json_encode2($k) . ':' . json_encode2($v);
        }
        return '{' . join(',', $result) . '}';
    }
}

if (!function_exists('json_encode')) {
    function json_encode($a = false)
    {
        return json_encode2($a);
    }
}

function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0)
{
    $json = preg_replace(
        "#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t](//).*)#",
        '',
        $json
    );

    if (version_compare(phpversion(), '5.4.0', '>=')) {
        $json = json_decode($json, $assoc, $depth, $options);
    } elseif (version_compare(phpversion(), '5.3.0', '>=')) {
        $json = json_decode($json, $assoc, $depth);
    } else {
        $json = json_decode($json, $assoc);
    }

    return $json;
}

function get_1000_path($id)
{
    $id = strval($id);
    $id = str_repeat('0', 9 - mb_strlen($id)) . $id;

    $path =
        mb_substr($id, 0, 3) .
        '/' .
        mb_substr($id, 3, 3) .
        '/' .
        mb_substr($id, 6, 3) .
        '/';

    return $path;
}

/** @deprecated */
function word_wrap($s, $len, $divider = ' ')
{
    return StringHelper::divideLongWords($s, $len, $divider);
}

function utime()
{
    $time = explode(' ', microtime());
    $usec = (float) $time[0];
    $sec = (float) $time[1];
    return $sec + $usec;
}

/** @deprecated  */
function replace_file_ext($fn, $new_ext = '')
{
    return StringHelper::replaceFileExtension($fn, $new_ext);
}

function ip2bin($ip = null)
{
    if ($ip === null) {
        $ip = get_user_ip();
    }

    //$ips = explode('.', $ip);
    //return ($ips[3] | $ips[2] << 8 | $ips[1] << 16 | $ips[0] << 24);
    return sprintf('%u', ip2long($ip));
}

function bin2ip($bin)
{
    return long2ip($bin ?: 0);
}

/** @deprecated */
function time_passed_by($timestamp, $now = null)
{
    return diDateTime::passedBy($timestamp, $now);
}

function size_in_bytes($size, $mb = 'Mb', $kb = 'kb', $b = ' bytes')
{
    if ($size > 1073741824) {
        return round(($size * 10) / 1073741824) / 10 . 'Gb';
    } elseif ($size > 1048576) {
        return round(($size * 10) / 1048576) / 10 . $mb;
    } elseif ($size > 1024) {
        return round(($size * 10) / 1024) / 10 . $kb;
    } else {
        return $size . $b;
    }
}

function str_filesize($size)
{
    return size_in_bytes($size, 'Мб', 'кб', ' байт');
}

function get_age($d, $m, $y)
{
    return $y ? date('Y') - $y - (date('md') < lead0($m) . lead0($d) ? 1 : 0) : 0;
}

function clean_filename($fn, $lowerCase = true)
{
    $fn = transliterate_rus_to_eng($fn, $lowerCase);
    $fn = preg_replace('/[^a-zA-Z0-9-\._\(\)\[\]]/', '', $fn);

    return $fn ? $fn : 'New_folder';
}

function get_uri_glue($uri)
{
    return strpos($uri, '?') === false ? '?' : '&';
}

/* classes stuff */

/** @deprecated */
function get_path_to_classes($prefix, $root = null)
{
    $root = $root ?: \diCore\Data\Config::getConfigurationFolder();

    $path = $root . '/_cfg/classes/';

    if ($prefix) {
        $path .= add_ending_slash($prefix);
    }

    return $path;
}

if (!function_exists('require_class')) {
    /** @deprecated */
    function require_class($class_name, $path_prefix = '')
    {
        return diLib::inc($class_name, $path_prefix);
    }
}

/** @deprecated */
function require_interface($interface_name, $path_prefix = '')
{
    require_once get_path_to_classes($path_prefix) .
        '_interface_' .
        mb_strtolower($interface_name) .
        '.php';
}

// ----------------------------------------------------------------------

function check_uploaded_file($full_fn, $orig_fn = '', $types_ar = [])
{
    $typed_allowed_ext_ar = [
        'pic' => ['jpeg', 'jpg', 'png', 'gif', 'swf'],
        'audio' => ['mp3', 'ogg', 'ac3'],
        'video' => ['avi', 'flv', 'mp4'],
        'arc' => ['rar', 'zip', 'gz'],
        'office' => ['doc', 'docx', 'xls', 'xlsx', 'pdf'],
    ];

    if ($types_ar && !is_array($types_ar)) {
        $types_ar = [$types_ar];
    }

    $ar = [];

    if ($types_ar) {
        $ar = [];

        foreach ($types_ar as $t) {
            if (isset($typed_allowed_ext_ar[$t])) {
                $ar = array_merge($ar, $typed_allowed_ext_ar[$t]);
            }
        }
    } else {
        foreach ($typed_allowed_ext_ar as $k => $v) {
            $ar = array_merge($ar, $v);
        }
    }

    $ext = mb_strtolower(
        StringHelper::fileExtension($orig_fn ? $orig_fn : $full_fn)
    );

    return in_array($ext, $ar);
}

function escape_bad_html($s, $allowed = 'p|br|b|i|u|a|img|object|embed|param|iframe')
{
    $s = preg_replace("/<((?!\/?($allowed)\b)[^>]*)>/xis", '&lt;\1&gt;', $s);

    preg_match_all('/<iframe[^>]*>/', $s, $regs);

    foreach ($regs[0] as $tag) {
        if (strpos($tag, " src=\"http://www.youtube.com/") === false) {
            $s = str_replace($tag, StringHelper::out($tag), $s);
        }
    }

    return $s;
}

/** @deprecated */
function wysiwyg_empty($s)
{
    return StringHelper::wysiwygEmpty($s);
}

/** @deprecated  */
function print_json($ar, $printHeaders = true)
{
    StringHelper::printJson($ar, $printHeaders);
}

function dierror2($text, $module = '')
{
    $ip = get_user_ip();
    $host = gethostbyaddr($ip);

    if ($module) {
        $module = "[$module]";
    }

    $f = fopen(getLogFolder() . 'log/' . date('Y_m_d') . '-errors.txt', 'a');
    fputs(
        $f,
        date('d.m.Y H:i:s') .
            " $module $ip ($host), uri: " .
            \diRequest::requestUri() .
            ', agent: ' .
            \diRequest::server('HTTP_USER_AGENT') .
            "\n$text\n\n"
    );
    fclose($f);

    die("$text");
}

/** @deprecated  */
function getLogFolder()
{
    return \diCore\Tool\Logger::getInstance()->getFolder();
}

/** @deprecated  */
function simple_debug($message, $module = '', $fnSuffix = '')
{
    \diCore\Tool\Logger::getInstance()->log($message, $module, $fnSuffix);
}

/** @deprecated  */
function var_debug(...$arguments)
{
    $logger = \diCore\Tool\Logger::getInstance();
    call_user_func_array([$logger, 'variable'], $arguments);
}

function cron_debug($script)
{
    $fn = getLogFolder() . 'log/cron/' . date('Y_m_d') . '.txt';

    $f = fopen($fn, 'a');
    fputs($f, date('[d.m.Y H:i:s]') . " {$script}\n");
    fclose($f);

    chmod($fn, 0777);
}

function extend(...$args)
{
    $extended = [];

    foreach ($args as $array) {
        if (is_iterable($array)) {
            $extended = array_replace($extended, (array) $array);
        }
    }

    return $extended;
}

function utf($s)
{
    return iconv('cp1251', 'utf-8', $s);
}

function _utf($s)
{
    return iconv('utf-8', 'cp1251', $s);
}

function ee($s)
{
    return str_replace(['ё', 'Ё'], ['е', 'Е'], $s);
}

function lc_all_but_first_letters($s, $only_sentence_uc = false)
{
    $space_ar = [' ', "\t", "\n", "\r", '-', '+'];
    $sentence_end_ar = [false, '.', ',', '?', '!'];
    $word_end_ar = array_merge($space_ar, $sentence_end_ar);

    $prev_ar = [];
    $s2 = '';

    $uc_allowed = function ($prev_ar) use (
        $only_sentence_uc,
        $space_ar,
        $sentence_end_ar,
        $word_end_ar
    ) {
        if (count($prev_ar) == 0) {
            return true;
        }

        if (!$only_sentence_uc) {
            return in_array($prev_ar[count($prev_ar) - 1], $word_end_ar, true);
        }

        for ($i = count($prev_ar) - 1; $i >= 0; $i--) {
            if (in_array($prev_ar[$i], $space_ar, true)) {
                continue;
            }

            if (in_array($prev_ar[$i], $sentence_end_ar, true)) {
                return true;
            }

            return false;
        }

        return false;
    };

    for ($i = 0; $i < mb_strlen($s); $i++) {
        $c = mb_substr($s, $i, 1);

        $s2 .= $uc_allowed($prev_ar) ? $c : mb_strtolower($c);

        $prev_ar[] = $c;
    }

    return $s2;
}

function camelize($scored, $lcFirst = true)
{
    if (!$scored) {
        return '';
    }

    $s = implode(
        '',
        array_map('ucfirst', array_map('strtolower', explode('_', $scored)))
    );

    return $lcFirst ? lcfirst($s) : $s;
}

function underscore($camelized)
{
    if (!$camelized) {
        return '';
    }

    return implode(
        '_',
        array_map(
            'strtolower',
            preg_split(
                '/([A-Z]{1}[^A-Z]*)/',
                $camelized,
                -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
            )
        )
    );
}

if (!function_exists('http_build_url')) {
    define('HTTP_URL_REPLACE', 1); // Replace every part of the first URL when there's one of the second URL
    define('HTTP_URL_JOIN_PATH', 2); // Join relative paths
    define('HTTP_URL_JOIN_QUERY', 4); // Join query strings
    define('HTTP_URL_STRIP_USER', 8); // Strip any user authentication information
    define('HTTP_URL_STRIP_PASS', 16); // Strip any password authentication information
    define('HTTP_URL_STRIP_AUTH', 32); // Strip any authentication information
    define('HTTP_URL_STRIP_PORT', 64); // Strip explicit port numbers
    define('HTTP_URL_STRIP_PATH', 128); // Strip complete path
    define('HTTP_URL_STRIP_QUERY', 256); // Strip query string
    define('HTTP_URL_STRIP_FRAGMENT', 512); // Strip any fragments (#identifier)
    define('HTTP_URL_STRIP_ALL', 1024); // Strip anything but scheme and host

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
    function http_build_url(
        $url,
        $parts = [],
        $flags = HTTP_URL_REPLACE,
        &$new_url = []
    ) {
        is_array($url) || ($url = parse_url($url));
        is_array($parts) || ($parts = parse_url($parts));

        (isset($url['query']) && is_string($url['query'])) || ($url['query'] = null);
        (isset($parts['query']) && is_string($parts['query'])) ||
            ($parts['query'] = null);

        $keys = ['user', 'pass', 'port', 'path', 'query', 'fragment'];

        // HTTP_URL_STRIP_ALL and HTTP_URL_STRIP_AUTH cover several other flags.
        if ($flags & HTTP_URL_STRIP_ALL) {
            $flags |=
                HTTP_URL_STRIP_USER |
                HTTP_URL_STRIP_PASS |
                HTTP_URL_STRIP_PORT |
                HTTP_URL_STRIP_PATH |
                HTTP_URL_STRIP_QUERY |
                HTTP_URL_STRIP_FRAGMENT;
        } elseif ($flags & HTTP_URL_STRIP_AUTH) {
            $flags |= HTTP_URL_STRIP_USER | HTTP_URL_STRIP_PASS;
        }

        // Schema and host are alwasy replaced
        foreach (['scheme', 'host'] as $part) {
            if (isset($parts[$part])) {
                $url[$part] = $parts[$part];
            }
        }

        if ($flags & HTTP_URL_REPLACE) {
            foreach ($keys as $key) {
                if (isset($parts[$key])) {
                    $url[$key] = $parts[$key];
                }
            }
        } else {
            if (isset($parts['path']) && $flags & HTTP_URL_JOIN_PATH) {
                if (isset($url['path']) && substr($parts['path'], 0, 1) !== '/') {
                    // Workaround for trailing slashes
                    $url['path'] .= 'a';
                    $url['path'] =
                        rtrim(
                            str_replace(basename($url['path']), '', $url['path']),
                            '/'
                        ) .
                        '/' .
                        ltrim($parts['path'], '/');
                } else {
                    $url['path'] = $parts['path'];
                }
            }

            if (isset($parts['query']) && $flags & HTTP_URL_JOIN_QUERY) {
                if (isset($url['query'])) {
                    parse_str($url['query'], $url_query);
                    parse_str($parts['query'], $parts_query);

                    $url['query'] = http_build_query(
                        array_replace_recursive($url_query, $parts_query)
                    );
                } else {
                    $url['query'] = $parts['query'];
                }
            }
        }

        if (
            isset($url['path']) &&
            $url['path'] !== '' &&
            substr($url['path'], 0, 1) !== '/'
        ) {
            $url['path'] = '/' . $url['path'];
        }

        foreach ($keys as $key) {
            $strip = 'HTTP_URL_STRIP_' . strtoupper($key);
            if ($flags & constant($strip)) {
                unset($url[$key]);
            }
        }

        $parsed_string = '';

        if (!empty($url['scheme'])) {
            $parsed_string .= $url['scheme'] . '://';
        }

        if (!empty($url['user'])) {
            $parsed_string .= $url['user'];

            if (isset($url['pass'])) {
                $parsed_string .= ':' . $url['pass'];
            }

            $parsed_string .= '@';
        }

        if (!empty($url['host'])) {
            $parsed_string .= $url['host'];
        }

        if (!empty($url['port'])) {
            $parsed_string .= ':' . $url['port'];
        }

        if (!empty($url['path'])) {
            $parsed_string .= $url['path'];
        }

        if (!empty($url['query'])) {
            $parsed_string .= '?' . $url['query'];
        }

        if (!empty($url['fragment'])) {
            $parsed_string .= '#' . $url['fragment'];
        }

        $new_url = $url;

        return $parsed_string;
    }
}

function utf8_wordwrap($string, $width = 75, $break = "\n", $cut = false)
{
    if ($cut) {
        // Match anything 1 to $width chars long followed by whitespace or EOS,
        // otherwise match anything $width chars long
        $search = '/(.{1,' . $width . '})(?:\s|$)|(.{' . $width . '})/uS';
        $replace = '$1$2' . $break;
    } else {
        // Anchor the beginning of the pattern with a look ahead
        // to avoid crazy backtracking when words are longer than $width
        $search = '/(?=\s)(.{1,' . $width . '})(?:\s|$)/uS';
        $replace = '$1' . $break;
    }

    return preg_replace($search, $replace, $string);
}
