<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\AdBlock;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByDefaultSlideTitle($value, $operator = null)
 * @method Collection filterByDefaultSlideContent($value, $operator = null)
 * @method Collection filterByTransition($value, $operator = null)
 * @method Collection filterByTransitionStyle($value, $operator = null)
 * @method Collection filterByDurationOfShow($value, $operator = null)
 * @method Collection filterByDurationOfChange($value, $operator = null)
 * @method Collection filterBySlidesOrder($value, $operator = null)
 * @method Collection filterByIgnoreHoverHold($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByDefaultSlideTitle($direction = null)
 * @method Collection orderByDefaultSlideContent($direction = null)
 * @method Collection orderByTransition($direction = null)
 * @method Collection orderByTransitionStyle($direction = null)
 * @method Collection orderByDurationOfShow($direction = null)
 * @method Collection orderByDurationOfChange($direction = null)
 * @method Collection orderBySlidesOrder($direction = null)
 * @method Collection orderByIgnoreHoverHold($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByDate($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTitle()
 * @method Collection selectDefaultSlideTitle()
 * @method Collection selectDefaultSlideContent()
 * @method Collection selectTransition()
 * @method Collection selectTransitionStyle()
 * @method Collection selectDurationOfShow()
 * @method Collection selectDurationOfChange()
 * @method Collection selectSlidesOrder()
 * @method Collection selectIgnoreHoverHold()
 * @method Collection selectVisible()
 * @method Collection selectOrderNum()
 * @method Collection selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::ad_block;
	protected $table = 'ad_blocks';
	protected $modelType = 'ad_block';
}