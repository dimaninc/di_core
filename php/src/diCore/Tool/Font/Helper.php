<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.05.2015
 * Time: 0:29
 */

namespace diCore\Tool\Font;

use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Traits\BasicCreate;

class Helper
{
    use BasicCreate;

    const CHMOD_FILE = 0664;
    const CACHE_FILENAME = 'css/fonts/fonts.css';

    public static $typesAr = [
        'eot' => 'embedded-opentype',
        'otf' => 'otf',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'svg' => 'svg',
    ];

    public static function getCssFilename()
    {
        return Config::getAssetSourcesFolder() . static::CACHE_FILENAME;
    }

    public function storeCss()
    {
        $css = '';

        $fonts = \diCollection::create(Types::font)->orderBy('token');
        /** @var \diFontModel $font */
        foreach ($fonts as $font) {
            $css .= static::getCssForFont($font) . "\n\n";
        }

        $fn = static::getCssFilename();

        file_put_contents($fn, $css);
        chmod($fn, self::CHMOD_FILE);

        return $this;
    }

    public static function storeToCss()
    {
        $o = static::basicCreate();

        $o->storeCss();
    }

    public static function getCssForFont(\diFontModel $font)
    {
        if (!$font->hasTokenSvg()) {
            $font->setTokenSvg($font->getToken());
        }

        //$host = diRequest::protocol() . '://' . diRequest::domain();

        $linesAr = [];
        $folder = $font->getRelated('folder') ?: $font->getPicsFolder();

        foreach (self::$typesAr as $ext => $format) {
            $field = 'file_' . $ext;

            if (!$font->get($field)) {
                continue;
            }

            $suffix = '';

            if ($ext == 'eot') {
                //$suffix = "?#iefix";
            } elseif ($ext == 'svg') {
                $suffix = "#{$font->getTokenSvg()}";
            }

            $linesAr[] = "url('/{$folder}{$font->get($field)}{$suffix}')"; // format('{$format}')
        }

        $lines = join(",\n\t\t", $linesAr);

        // attrs
        $attrAr = [];

        if ($font->hasWeight()) {
            $attrAr[] = "\tfont-weight: " . $font->getWeight() . ";\n";
        }

        if ($font->hasStyle()) {
            $attrAr[] = "\tfont-style: " . $font->getStyle() . ";\n";
        }
        //

        return "@font-face {\n\tfont-family: '{$font->getToken()}';\n\tsrc: $lines;\n" .
            join('', $attrAr) .
            '}';
    }
}
