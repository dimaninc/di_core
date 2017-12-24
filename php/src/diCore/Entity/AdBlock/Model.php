<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\AdBlock;

/**
 * Class Model
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
 * @method Model setTitle($value)
 * @method Model setDefaultSlideTitle($value)
 * @method Model setDefaultSlideContent($value)
 * @method Model setTransition($value)
 * @method Model setTransitionStyle($value)
 * @method Model setDurationOfShow($value)
 * @method Model setDurationOfChange($value)
 * @method Model setSlidesOrder($value)
 * @method Model setIgnoreHoverHold($value)
 * @method Model setVisible($value)
 * @method Model setOrderNum($value)
 * @method Model setDate($value)
 */
class Model extends \diModel
{
	const type = \diTypes::ad_block;
	protected $table = 'ad_blocks';

	const INCUT_TEMPLATE = '[AD-BLOCK-%d]';
	const INCUT_TEMPLATE_FOR_ADMIN = '[AD-BLOCK-%id%]';
	
	public function getToken()
	{
		return sprintf(static::INCUT_TEMPLATE, $this->getId());
	}
	
	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'token' => $this->getToken(),
		]);
	}
}