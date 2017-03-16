<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:37
 */
/**
 * Class diAdBlockModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getDefaultSlideTitle
 * @method string	getDefaultSlideContent
 * @method integer	getTransition
 * @method integer	getTransitionStyle
 * @method integer	getDurationOfShow
 * @method integer	getDurationOfChange
 * @method integer	getSlidesOrder
 * @method integer	getIgnoreHoverHold
 * @method integer	getVisible
 * @method integer	getOrderNum
 * @method string	getDate
 *
 * @method bool hasTitle
 * @method bool hasDefaultSlideTitle
 * @method bool hasDefaultSlideContent
 * @method bool hasTransition
 * @method bool hasTransitionStyle
 * @method bool hasDurationOfShow
 * @method bool hasDurationOfChange
 * @method bool hasSlidesOrder
 * @method bool hasIgnoreHoverHold
 * @method bool hasVisible
 * @method bool hasOrderNum
 * @method bool hasDate
 *
 * @method diAdBlockModel setTitle($value)
 * @method diAdBlockModel setDefaultSlideTitle($value)
 * @method diAdBlockModel setDefaultSlideContent($value)
 * @method diAdBlockModel setTransition($value)
 * @method diAdBlockModel setTransitionStyle($value)
 * @method diAdBlockModel setDurationOfShow($value)
 * @method diAdBlockModel setDurationOfChange($value)
 * @method diAdBlockModel setSlidesOrder($value)
 * @method diAdBlockModel setIgnoreHoverHold($value)
 * @method diAdBlockModel setVisible($value)
 * @method diAdBlockModel setOrderNum($value)
 * @method diAdBlockModel setDate($value)
 */
class diAdBlockModel extends diModel
{
	const type = diTypes::ad_block;
	protected $table = "ad_blocks";
}