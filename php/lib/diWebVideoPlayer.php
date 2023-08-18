<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.12.15
 * Time: 10:34
 */
class diWebVideoPlayer
{
    public static function getFormatRowsHtml($options = [])
    {
        $rows = [];

        $options = extend(
            [
                'getFilenameCallback' => function ($format) {},
                'getTypeCallback' => null,
            ],
            $options
        );

        $defaultTypeCallback = function ($formatId, $fillMp4 = false) {
            return $fillMp4 || $formatId != \diWebVideoFormats::MP4
                ? ' type="' .
                        \diWebVideoFormats::$videoTagMimeTypes[$formatId] .
                        '"'
                : '';
        };

        foreach (\diWebVideoFormats::$extensions as $formatId => $format) {
            $file = $options['getFilenameCallback']($formatId);

            if (!$file) {
                continue;
            }

            $typeAttr = $options['getTypeCallback']
                ? $options['getTypeCallback']($formatId, $defaultTypeCallback)
                : $defaultTypeCallback($formatId);

            $rows[] = '<source src="' . $file . '"' . $typeAttr . '>';
        }

        return join('', $rows);
    }
}
