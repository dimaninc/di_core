<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:19
 */

namespace diCore\Entity\Video;

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
 * @method Model setAlbumId($value)
 * @method Model setVendor($value)
 * @method Model setVendorVideoUid($value)
 * @method Model setSlugSource($value)
 * @method Model setTitle($value)
 * @method Model setContent($value)
 * @method Model setEmbed($value)
 * @method Model setVideoMp4($value)
 * @method Model setVideoM4v($value)
 * @method Model setVideoOgv($value)
 * @method Model setVideoWebm($value)
 * @method Model setVideoW($value)
 * @method Model setVideoH($value)
 * @method Model setPic($value)
 * @method Model setPicW($value)
 * @method Model setPicH($value)
 * @method Model setPicT($value)
 * @method Model setPicTnW($value)
 * @method Model setPicTnH($value)
 * @method Model setViewsCount($value)
 * @method Model setDate($value)
 * @method Model setOrderNum($value)
 * @method Model setVisible($value)
 * @method Model setTop($value)
 * @method Model setCommentsEnabled($value)
 * @method Model setCommentsLastDate($value)
 * @method Model setCommentsCount($value)
 */
class Model extends \diModel
{
	const type = Types::video;
	const slug_field_name = self::SLUG_FIELD_NAME;
	protected $table = 'videos';
	protected $customFileFields = ['video_mp4', 'video_m4v', 'video_ogv', 'video_webm'];

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
			'getFilenameCallback' => function($formatId) {
				return $this->getVideoFileByFormat(\diWebVideoFormats::$extensions[$formatId]);
			},
		]);
	}

	public function getTemplateVars()
	{
		$ar = parent::getTemplateVars();

		foreach (\diWebVideoFormats::$extensions as $formatId => $formatExt)
		{
			if ($this->has('video_' . $formatExt))
			{
				$ar['video_' . $formatExt] = $this->getVideoFileByFormat($formatExt);
			}
		}

		return $ar;
	}

	public function getHref()
	{
		return $this->__getPrefixForHref() . '/' . CMS::ct('videos') . '/' . $this->getSlug() . '/';
	}

	public function getVideoVendorPreview()
	{
		if ($this->getVendor() == \diVideoVendors::Own)
		{
			return null;
		}

		return \diVideoVendors::getPoster($this->getVendor(), $this->getVendorVideoUid());
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

		if ($this->getVendor() != \diVideoVendors::Own)
		{
			$ar = extend($ar, [
				'pic_tn' => $this->getVideoVendorPreview(),
				'pic_tn_safe' => $this->hasPic() ? $this->getPicsFolder() . $this->getPic() : $this->getVideoVendorPreview(),
				'vendor_link' => $this->getVendorLink(),
				'vendor_embed_link' => $this->getVendorEmbedLink(),
			]);
		}

		return $ar;
	}

	public function getVendorLink()
	{
		return \diVideoVendors::getLink($this->getVendor(), $this->getVendorVideoUid());
	}

	public function getVendorEmbedLink()
	{
		return \diVideoVendors::getEmbedLink($this->getVendor(), $this->getVendorVideoUid());
	}

	/**
	 * Returns query conditions array for order_num calculating
	 *
	 * @return array
	 */
	public function getQueryArForMove()
	{
		$ar = [
			"album_id = '{$this->getAlbumId()}'",
		];

		return $ar;
	}
}