<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\Ad;

use diCore\Helper\StringHelper;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getBlockId
 * @method integer	getCategoryId
 * @method string	getTitle
 * @method string	getContent
 * @method string	getHref
 * @method integer	getHrefTarget
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
 * @method string	getShowDate1
 * @method string	getShowDate2
 * @method string	getShowTime1
 * @method string	getShowTime2
 * @method string	getShowOnWeekdays
 * @method integer	getShowOnHolidays
 *
 * @method bool hasBlockId
 * @method bool hasCategoryId
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasHref
 * @method bool hasHrefTarget
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
 * @method bool hasShowDate1
 * @method bool hasShowDate2
 * @method bool hasShowTime1
 * @method bool hasShowTime2
 * @method bool hasShowOnWeekdays
 * @method bool hasShowOnHolidays
 *
 * @method $this setBlockId($value)
 * @method $this setCategoryId($value)
 * @method $this setTitle($value)
 * @method $this setContent($value)
 * @method $this setHref($value)
 * @method $this setHrefTarget($value)
 * @method $this setOnclick($value)
 * @method $this setButtonColor($value)
 * @method $this setTransition($value)
 * @method $this setTransitionStyle($value)
 * @method $this setDurationOfShow($value)
 * @method $this setDurationOfChange($value)
 * @method $this setPic($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setVisible($value)
 * @method $this setOrderNum($value)
 * @method $this setDate($value)
 * @method $this setShowDate1($value)
 * @method $this setShowDate2($value)
 * @method $this setShowTime1($value)
 * @method $this setShowTime2($value)
 * @method $this setShowOnWeekdays($value)
 * @method $this setShowOnHolidays($value)
 */
class Model extends \diModel
{
	const type = \diTypes::ad;
	protected $table = 'ads';

	/**
	 * Returns query conditions array for order_num calculating
	 *
	 * @return array
	 */
	public function getQueryArForMove()
	{
		return [
			"block_id = '{$this->getBlockId()}'",
		];
	}
	
	public function getHrefTargetName()
	{
		return HrefTarget::name($this->getHrefTarget());
	}

	public function getHrefTargetAttribute()
	{
		return HrefTarget::htmlAttribute($this->getHrefTarget());
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'title_safe' => StringHelper::out($this->getTitle()),
			'content_safe' => StringHelper::out($this->getContent()),
			'href_target_name' => $this->getHrefTargetName(),
			'href_target_attribute' => $this->getHrefTargetAttribute(),
		]);
	}
}