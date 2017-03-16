<?php
/**
 * Created by diModelsManager
 * Date: 09.10.2015
 * Time: 17:50
 */

/**
 * Class diVideoModel
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
 * @method diVideoModel setAlbumId($value)
 * @method diVideoModel setVendor($value)
 * @method diVideoModel setVendorVideoUid($value)
 * @method diVideoModel setSlugSource($value)
 * @method diVideoModel setTitle($value)
 * @method diVideoModel setContent($value)
 * @method diVideoModel setEmbed($value)
 * @method diVideoModel setVideoMp4($value)
 * @method diVideoModel setVideoM4v($value)
 * @method diVideoModel setVideoOgv($value)
 * @method diVideoModel setVideoWebm($value)
 * @method diVideoModel setVideoW($value)
 * @method diVideoModel setVideoH($value)
 * @method diVideoModel setPic($value)
 * @method diVideoModel setPicW($value)
 * @method diVideoModel setPicH($value)
 * @method diVideoModel setPicT($value)
 * @method diVideoModel setPicTnW($value)
 * @method diVideoModel setPicTnH($value)
 * @method diVideoModel setViewsCount($value)
 * @method diVideoModel setDate($value)
 * @method diVideoModel setOrderNum($value)
 * @method diVideoModel setVisible($value)
 * @method diVideoModel setTop($value)
 * @method diVideoModel setCommentsEnabled($value)
 * @method diVideoModel setCommentsLastDate($value)
 * @method diVideoModel setCommentsCount($value)
 */
class diVideoModel extends diModel
{
	const type = diTypes::video;
	protected $table = "videos";
	protected $customFileFields = ["video_mp4", "video_m4v", "video_ogv", "video_webm"];
	protected $slugFieldName = "slug";

	public function getToken()
	{
		return $this->hasId()
			? "[VIDEO-" . str_pad($this->getId(), 6, "0", STR_PAD_LEFT) . "]"
			: null;
	}

	public function getVideoFileByFormat($formatExt)
	{
		return $this->has("video_" . $formatExt)
			? get_files_folder($this->getTable()) . $this->get("video_" . $formatExt)
			: "";
	}

	public function getHtmlVideoSources()
	{
		return \diWebVideoPlayer::getFormatRowsHtml([
			'getFilenameCallback' => function($format) {
				return $this->getVideoFileByFormat($format);
			},
		]);
	}

	public function getTemplateVars()
	{
		$ar = parent::getTemplateVars();

		foreach (diWebVideoFormats::$extensions as $formatId => $formatExt)
		{
			if ($this->has("video_" . $formatExt))
			{
				$ar["video_" . $formatExt] = $this->getVideoFileByFormat($formatExt);
			}
		}

		return $ar;
	}

	public function getHref()
	{
		return $this->__getPrefixForHref() . "/" . diCurrentCMS::ct("videos") . "/" . $this->getSlug() . "/";
	}

	public function getVideoVendorPreview()
	{
		if ($this->getVendor() == diVideoVendors::Own)
		{
			return null;
		}

		return diVideoVendors::getPoster($this->getVendor(), $this->getVendorVideoUid());
	}

	/**
	 * Custom model template vars
	 *
	 * @return array
	 */
	public function getCustomTemplateVars()
	{
		$ar = extend(parent::getCustomTemplateVars(), [
			"token" => $this->getToken(),
		]);

		if ($this->getVendor() != diVideoVendors::Own)
		{
			$ar = extend($ar, [
				"pic_tn" => $this->getVideoVendorPreview(),
				"pic_tn_safe" => $this->hasPic() ? $this->getPicsFolder() . $this->getPic() : $this->getVideoVendorPreview(),
				"vendor_link" => diVideoVendors::getLink($this->getVendor(), $this->getVendorVideoUid()),
				"vendor_embed_link" => diVideoVendors::getEmbedLink($this->getVendor(), $this->getVendorVideoUid()),
			]);
		}

		return $ar;
	}

	/**
	 * Returns query conditions array for order_num calculating
	 *
	 * @return array
	 */
	public function getQueryArForMove()
	{
		$ar = [
			"album_id='{$this->getAlbumId()}'",
		];

		return $ar;
	}
}