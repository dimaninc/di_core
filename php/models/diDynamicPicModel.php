<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 25.06.2015
 * Time: 12:13
 */
/**
 * Class diDynamicPicModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getContent
 * @method string	getOrigFn
 * @method string	getPic
 * @method integer	getPicT
 * @method integer	getPicW
 * @method integer	getPicH
 * @method string	getPicTn
 * @method integer	getPicTnT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method integer	getPicTn2T
 * @method integer	getPicTn2W
 * @method integer	getPicTn2H
 * @method integer	getByDefault
 * @method integer	getVisible
 * @method integer	getOrderNum
 * @method string	getDate
 *
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasOrigFn
 * @method bool hasPic
 * @method bool hasPicT
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicTn
 * @method bool hasPicTnT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasPicTn2T
 * @method bool hasPicTn2W
 * @method bool hasPicTn2H
 * @method bool hasByDefault
 * @method bool hasVisible
 * @method bool hasOrderNum
 * @method bool hasDate
 *
 * @method diDynamicPicModel setTitle($value)
 * @method diDynamicPicModel setContent($value)
 * @method diDynamicPicModel setOrigFn($value)
 * @method diDynamicPicModel setPic($value)
 * @method diDynamicPicModel setPicT($value)
 * @method diDynamicPicModel setPicW($value)
 * @method diDynamicPicModel setPicH($value)
 * @method diDynamicPicModel setPicTn($value)
 * @method diDynamicPicModel setPicTnT($value)
 * @method diDynamicPicModel setPicTnW($value)
 * @method diDynamicPicModel setPicTnH($value)
 * @method diDynamicPicModel setPicTn2T($value)
 * @method diDynamicPicModel setPicTn2W($value)
 * @method diDynamicPicModel setPicTn2H($value)
 * @method diDynamicPicModel setByDefault($value)
 * @method diDynamicPicModel setVisible($value)
 * @method diDynamicPicModel setOrderNum($value)
 * @method diDynamicPicModel setDate($value)
 */
class diDynamicPicModel extends diModel
{
	const type = diTypes::dynamic_pic;
	protected $table = "dipics";

	/**
	 * @return string
	 */
	public function getTargetTable()
	{
		return $this->get("_table");
	}

	/**
	 * @return string
	 */
	public function getTargetField()
	{
		return $this->get("_field");
	}

	/**
	 * @return integer
	 */
	public function getTargetId()
	{
		return $this->get("_id");
	}

	/**
	 * @return bool
	 */
	public function hasTargetTable()
	{
		return $this->has("_table");
	}

	/**
	 * @return bool
	 */
	public function hasTargetField()
	{
		return $this->has("_field");
	}

	/**
	 * @return bool
	 */
	public function hasTargetId()
	{
		return $this->has("_id");
	}

	/**
	 * @param string $value
	 * @return $this
	 */
	public function setTargetTable($value)
	{
		return $this->set("_table", $value);
	}

	/**
	 * @param string $value
	 * @return $this
	 */
	public function setTargetField($value)
	{
		return $this->set("_field", $value);
	}

	/**
	 * @param integer $value
	 * @return $this
	 */
	public function setTargetId($value)
	{
		return $this->set("_id", $value);
	}

	public function getPicsFolder()
	{
		return get_pics_folder($this->getRelated("table") ?: $this->getTargetTable() ?: $this->getTable());
	}

	public static function getDynamicPicVars(diModel $model, $relatedTable = null)
	{
		$ar = array();

		$pic = $model->getRelated("pic") ?: $model->get("pic");

		if ($pic)
		{
			$folder = get_pics_folder($relatedTable ?: $model->getTable());

			$ar["PIC"] = $folder . $pic;

			$ar["PIC_TN"] = $folder . get_tn_folder() . $pic;
			$ar["PIC_TN2"] = $folder . get_tn_folder(2) . $pic;
			$ar["PIC_TN3"] = $folder . get_tn_folder(3) . $pic;

			$ar["PIC_BIG"] = $folder . get_big_folder() . $pic;
			$ar["PIC_ORIG"] = $folder . get_orig_folder() . $pic;
		}

		return $ar;
	}

	public function getTemplateVars()
	{
		/*
		if (!$this->getRelated("table"))
		{
			throw new Exception("Related table not set");
		}
		*/

		$ar = array_merge(
			parent::getTemplateVars(),
			static::getDynamicPicVars($this, $this->getRelated("table") ?: $this->getTargetTable())
		);

		return $ar;
	}
}