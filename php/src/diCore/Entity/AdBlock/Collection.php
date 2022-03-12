<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\AdBlock;

use diCore\Traits\Collection\TargetInside;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByDefaultSlideTitle($value, $operator = null)
 * @method $this filterByDefaultSlideContent($value, $operator = null)
 * @method $this filterByTransition($value, $operator = null)
 * @method $this filterByTransitionStyle($value, $operator = null)
 * @method $this filterByDurationOfShow($value, $operator = null)
 * @method $this filterByDurationOfChange($value, $operator = null)
 * @method $this filterBySlidesOrder($value, $operator = null)
 * @method $this filterByIgnoreHoverHold($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByDefaultSlideTitle($direction = null)
 * @method $this orderByDefaultSlideContent($direction = null)
 * @method $this orderByTransition($direction = null)
 * @method $this orderByTransitionStyle($direction = null)
 * @method $this orderByDurationOfShow($direction = null)
 * @method $this orderByDurationOfChange($direction = null)
 * @method $this orderBySlidesOrder($direction = null)
 * @method $this orderByIgnoreHoverHold($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByDate($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTitle()
 * @method $this selectDefaultSlideTitle()
 * @method $this selectDefaultSlideContent()
 * @method $this selectTransition()
 * @method $this selectTransitionStyle()
 * @method $this selectDurationOfShow()
 * @method $this selectDurationOfChange()
 * @method $this selectSlidesOrder()
 * @method $this selectIgnoreHoverHold()
 * @method $this selectVisible()
 * @method $this selectOrderNum()
 * @method $this selectDate()
 */
class Collection extends \diCollection
{
    use TargetInside;

	const type = \diTypes::ad_block;
	protected $table = 'ad_blocks';
	protected $modelType = 'ad_block';
}
