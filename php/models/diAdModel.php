<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:37
 */

/**
 * Class diAdModel
 * Methods list for IDE
 *
 * @method integer	getBlockId
 * @method integer	getCategoryId
 * @method string	getTitle
 * @method string	getContent
 * @method string	getHref
 * @method string	getOnclick
 * @method string	getButtonColor
 * @method integer	getTransition
 * @method integer	getTransitionStyle
 * @method integer	getDurationOfShow
 * @method integer	getDurationOfChange
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getVisible
 * @method integer	getOrderNum
 * @method string	getDate
 *
 * @method bool hasBlockId
 * @method bool hasCategoryId
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasHref
 * @method bool hasOnclick
 * @method bool hasButtonColor
 * @method bool hasTransition
 * @method bool hasTransitionStyle
 * @method bool hasDurationOfShow
 * @method bool hasDurationOfChange
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasVisible
 * @method bool hasOrderNum
 * @method bool hasDate
 *
 * @method diAdModel setBlockId($value)
 * @method diAdModel setCategoryId($value)
 * @method diAdModel setTitle($value)
 * @method diAdModel setContent($value)
 * @method diAdModel setHref($value)
 * @method diAdModel setOnclick($value)
 * @method diAdModel setButtonColor($value)
 * @method diAdModel setTransition($value)
 * @method diAdModel setTransitionStyle($value)
 * @method diAdModel setDurationOfShow($value)
 * @method diAdModel setDurationOfChange($value)
 * @method diAdModel setPic($value)
 * @method diAdModel setPicW($value)
 * @method diAdModel setPicH($value)
 * @method diAdModel setVisible($value)
 * @method diAdModel setOrderNum($value)
 * @method diAdModel setDate($value)
 */
class diAdModel extends diModel
{
	const type = diTypes::ad;
	protected $table = "ads";

	/**
	 * Returns query conditions array for order_num calculating
	 *
	 * @return array
	 */
	public function getQueryArForMove()
	{
		return [
			"block_id='{$this->getBlockId()}'",
		];
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			"title_safe" => diStringHelper::out($this->getTitle()),
			"content_safe" => diStringHelper::out($this->getContent()),
		]);
	}
}