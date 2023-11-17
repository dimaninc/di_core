<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:19
 */

namespace diCore\Entity\Video;

use diCore\Admin\Submit;
use diCore\Base\CMS;
use diCore\Data\Types;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getAlbumId
 * @method integer	getVendor
 * @method integer	getVendorVideoUid
 * @method string	getSlugSource
 * @method string	getTitle
 * @method string	getContent
 * @method string	getEmbed
 * @method string	getVideoMp4
 * @method string	getVideoM4v
 * @method string	getVideoOgv
 * @method string	getVideoWebm
 * @method integer	getVideoW
 * @method integer	getVideoH
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method string	getViewsCount
 * @method string	getDate
 * @method integer	getOrderNum
 * @method integer	getVisible
 * @method integer	getTop
 * @method integer	getCommentsEnabled
 * @method string	getCommentsLastDate
 * @method integer	getCommentsCount
 *
 * @method bool hasAlbumId
 * @method bool hasVendor
 * @method bool hasVendorVideoUid
 * @method bool hasSlugSource
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasEmbed
 * @method bool hasVideoMp4
 * @method bool hasVideoM4v
 * @method bool hasVideoOgv
 * @method bool hasVideoWebm
 * @method bool hasVideoW
 * @method bool hasVideoH
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasViewsCount
 * @method bool hasDate
 * @method bool hasOrderNum
 * @method bool hasVisible
 * @method bool hasTop
 * @method bool hasCommentsEnabled
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsCount
 *
 * @method $this setAlbumId($value)
 * @method $this setVendor($value)
 * @method $this setVendorVideoUid($value)
 * @method $this setSlugSource($value)
 * @method $this setTitle($value)
 * @method $this setContent($value)
 * @method $this setEmbed($value)
 * @method $this setVideoMp4($value)
 * @method $this setVideoM4v($value)
 * @method $this setVideoOgv($value)
 * @method $this setVideoWebm($value)
 * @method $this setVideoW($value)
 * @method $this setVideoH($value)
 * @method $this setPic($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setPicT($value)
 * @method $this setPicTnW($value)
 * @method $this setPicTnH($value)
 * @method $this setViewsCount($value)
 * @method $this setDate($value)
 * @method $this setOrderNum($value)
 * @method $this setVisible($value)
 * @method $this setTop($value)
 * @method $this setCommentsEnabled($value)
 * @method $this setCommentsLastDate($value)
 * @method $this setCommentsCount($value)
 */
class Model extends \diModel
{
    const type = Types::video;
    const slug_field_name = self::SLUG_FIELD_NAME;
    protected $table = 'videos';
    protected $customFileFields = [
        'video_mp4',
        'video_m4v',
        'video_ogv',
        'video_webm',
    ];

    protected static $picStoreSettings = [
        'pic' => [
            [
                'type' => Submit::IMAGE_TYPE_PREVIEW,
                'resize' => \diImage::DI_THUMB_CROP,
            ],
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
            ],
        ],
    ];

    public function getToken()
    {
        return $this->hasId()
            ? '[VIDEO-' . str_pad($this->getId(), 6, '0', STR_PAD_LEFT) . ']'
            : null;
    }

    public function getFolderForField($field)
    {
        if (in_array($field, $this->getFileFields())) {
            return $this->getFilesFolder();
        }

        return parent::getFolderForField($field);
    }

    public function getVideoFileByFormat($formatExt)
    {
        return $this->has('video_' . $formatExt)
            ? $this->getFilesFolder() . $this->get('video_' . $formatExt)
            : '';
    }

    public function getHtmlVideoSources()
    {
        return \diWebVideoPlayer::getFormatRowsHtml([
            'getFilenameCallback' => function ($formatId) {
                return $this->getVideoFileByFormat(
                    \diWebVideoFormats::$extensions[$formatId]
                );
            },
        ]);
    }

    public function getTemplateVars()
    {
        $ar = parent::getTemplateVars();

        foreach (\diWebVideoFormats::$extensions as $formatId => $formatExt) {
            if ($this->has('video_' . $formatExt)) {
                $ar['video_' . $formatExt] = $this->getVideoFileByFormat(
                    $formatExt
                );
            }
        }

        return $ar;
    }

    public function getHref()
    {
        return $this->__getPrefixForHref() .
            '/' .
            CMS::ct('videos') .
            '/' .
            $this->getSlug() .
            '/';
    }

    public function getVideoVendorPreview()
    {
        if ($this->getVendor() == Vendor::OWN) {
            return null;
        }

        return Vendor::getPoster(
            $this->getVendor(),
            $this->getVendorVideoUid()
        );
    }

    /**
     * Custom model template vars
     *
     * @return array
     */
    public function getCustomTemplateVars()
    {
        $ar = extend(parent::getCustomTemplateVars(), [
            'token' => $this->getToken(),
        ]);

        if ($this->getVendor() != Vendor::OWN) {
            $ar = extend($ar, [
                'pic_safe' => $this->hasPic()
                    ? \diLib::getSubFolder('force') .
                        $this->getPicsFolder() .
                        $this->getPic()
                    : $this->getVideoVendorPreview(),
                'pic_tn' => $this->getVideoVendorPreview(),
                'pic_tn_safe' => $this->hasPic()
                    ? \diLib::getSubFolder('force') .
                        $this->getPicsFolder() .
                        $this->getTnFolder() .
                        $this->getPic()
                    : $this->getVideoVendorPreview(),
                'vendor_link' => $this->getVendorLink(),
                'vendor_embed_link' => $this->getVendorEmbedLink(),
            ]);
        }

        return $ar;
    }

    public function getVendorLink()
    {
        return Vendor::getLink($this->getVendor(), $this->getVendorVideoUid());
    }

    public function getVendorEmbedLink()
    {
        return Vendor::getEmbedLink(
            $this->getVendor(),
            $this->getVendorVideoUid()
        );
    }

    /**
     * Returns query conditions array for order_num calculating
     *
     * @return array
     */
    public function getQueryArForMove()
    {
        $ar = ["album_id = '{$this->getAlbumId()}'"];

        return $ar;
    }
}
