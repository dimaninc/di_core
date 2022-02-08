<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\AdBlock;

use diCore\Entity\Ad\Helper;

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
 * @method $this setTitle($value)
 * @method $this setDefaultSlideTitle($value)
 * @method $this setDefaultSlideContent($value)
 * @method $this setTransition($value)
 * @method $this setTransitionStyle($value)
 * @method $this setDurationOfShow($value)
 * @method $this setDurationOfChange($value)
 * @method $this setSlidesOrder($value)
 * @method $this setIgnoreHoverHold($value)
 * @method $this setVisible($value)
 * @method $this setOrderNum($value)
 * @method $this setDate($value)
 */
class Model extends \diModel
{
	const type = \diTypes::ad_block;
    const table = 'ad_blocks';
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

	public function getHtml()
    {
        return $this->exists()
            ? Helper::printBlock($this->getId())
            : null;
    }

    /**
     * Called in Helper class when fetching ads for block
     * Rewrite this if some emulation for non existing block needed or something
     * @param callable $nativeCallback
     * @return mixed
     */
    public function fetchAdsForHelper($nativeCallback)
    {
        return $nativeCallback();
    }
}
