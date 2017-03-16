<?php
/**
 * Created by diModelsManager
 * Date: 09.10.2015
 * Time: 15:44
 */

/**
 * Class diPhotoModel
 * Methods list for IDE
 *
 * @method string	getAlbumId
 * @method string	getSlugSource
 * @method string	getTitle
 * @method string	getContent
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method string	getVisible
 * @method integer	getCommentsEnabled
 * @method string	getCommentsLastDate
 * @method integer	getCommentsCount
 * @method string	getDate
 * @method integer	getOrderNum
 *
 * @method bool hasAlbumId
 * @method bool hasSlugSource
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasVisible
 * @method bool hasCommentsEnabled
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsCount
 * @method bool hasDate
 * @method bool hasOrderNum
 *
 * @method diPhotoModel setAlbumId($value)
 * @method diPhotoModel setSlugSource($value)
 * @method diPhotoModel setTitle($value)
 * @method diPhotoModel setContent($value)
 * @method diPhotoModel setPic($value)
 * @method diPhotoModel setPicW($value)
 * @method diPhotoModel setPicH($value)
 * @method diPhotoModel setPicT($value)
 * @method diPhotoModel setPicTnW($value)
 * @method diPhotoModel setPicTnH($value)
 * @method diPhotoModel setVisible($value)
 * @method diPhotoModel setCommentsEnabled($value)
 * @method diPhotoModel setCommentsLastDate($value)
 * @method diPhotoModel setCommentsCount($value)
 * @method diPhotoModel setDate($value)
 * @method diPhotoModel setOrderNum($value)
 */
class diPhotoModel extends diModel
{
	const type = diTypes::photo;
	protected $table = "photos";

	public static $tokenAlignments = [
		'left',
		'right',
		'center',
		null,
	];

	public function getToken($alignment = null)
	{
		$token = $this->getId()
			? "[PHOTO-" . str_pad($this->getId(), 6, "0", STR_PAD_LEFT) . "]"
			: null;

		if ($token && $alignment)
		{
			$token .= '[' . strtoupper($alignment) . ']';
		}

		return $token;
	}

	public function getAllTokens()
	{
		$ar = [];

		foreach (static::$tokenAlignments as $alignment)
		{
			$ar[$alignment] = $this->getToken($alignment);
		}

		return $ar;
	}

	/**
	 * Custom model template vars
	 *
	 * @return array
	 */
	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			"token" => $this->getToken(),
		]);
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