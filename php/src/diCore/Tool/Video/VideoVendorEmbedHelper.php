<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.11.2020
 * Time: 16:33
 */

namespace diCore\Tool\Video;

use diCore\Entity\Video\Vendor;

class VideoVendorEmbedHelper
{
    public static function replace($html, \diTwig $twig, $options = [])
    {
        $options = extend([
            'template' => '_macro/video_player',
            'extraTemplateVariables' => [],
        ], $options);

        preg_match_all('/<iframe[^>]*(?<!\/)><\/iframe>/', $html, $regs);
        $replaces = [];

        if (!empty($regs[0])) {
            foreach ($regs[0] as $i => $iframeHtml) {
                $info = Vendor::extractInfoFromEmbed($iframeHtml);

                if (
                    $info['vendor'] == Vendor::YouTube &&
                    $info['video_uid']
                ) {
                    $video = [
                        'id' => $i,
                        'title' => '',
                        'vendor' => $info['vendor'],
                        'vendor_video_uid' => $info['video_uid'],
                        'vendor_link' => Vendor::getLink($info['vendor'], $info['video_uid']),
                        'vendor_embed_link' => Vendor::getEmbedLink($info['vendor'], $info['video_uid']),
                    ];

                    $replaces[$iframeHtml] = $twig->parse(
                        $options['template'],
                        extend($options['extraTemplateVariables'], [
                            'video' => $video,
                        ])
                    );
                }
            }
        }

        $html = str_replace(array_keys($replaces), array_values($replaces), $html);

        return $html;
    }
}