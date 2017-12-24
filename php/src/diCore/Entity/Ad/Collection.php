<?php
/**
 * Created by \diModelsManager
 * Date: 24.12.2017
 * Time: 11:39
 */

namespace diCore\Entity\Ad;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByBlockId($value, $operator = null)
 * @method Collection filterByCategoryId($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByHref($value, $operator = null)
 * @method Collection filterByHrefTarget($value, $operator = null)
 * @method Collection filterByOnclick($value, $operator = null)
 * @method Collection filterByButtonColor($value, $operator = null)
 * @method Collection filterByTransition($value, $operator = null)
 * @method Collection filterByTransitionStyle($value, $operator = null)
 * @method Collection filterByDurationOfShow($value, $operator = null)
 * @method Collection filterByDurationOfChange($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByShowDate1($value, $operator = null)
 * @method Collection filterByShowDate2($value, $operator = null)
 * @method Collection filterByShowTime1($value, $operator = null)
 * @method Collection filterByShowTime2($value, $operator = null)
 * @method Collection filterByShowOnWeekdays($value, $operator = null)
 * @method Collection filterByShowOnHolidays($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByBlockId($direction = null)
 * @method Collection orderByCategoryId($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByHref($direction = null)
 * @method Collection orderByHrefTarget($direction = null)
 * @method Collection orderByOnclick($direction = null)
 * @method Collection orderByButtonColor($direction = null)
 * @method Collection orderByTransition($direction = null)
 * @method Collection orderByTransitionStyle($direction = null)
 * @method Collection orderByDurationOfShow($direction = null)
 * @method Collection orderByDurationOfChange($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByShowDate1($direction = null)
 * @method Collection orderByShowDate2($direction = null)
 * @method Collection orderByShowTime1($direction = null)
 * @method Collection orderByShowTime2($direction = null)
 * @method Collection orderByShowOnWeekdays($direction = null)
 * @method Collection orderByShowOnHolidays($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectBlockId()
 * @method Collection selectCategoryId()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectHref()
 * @method Collection selectHrefTarget()
 * @method Collection selectOnclick()
 * @method Collection selectButtonColor()
 * @method Collection selectTransition()
 * @method Collection selectTransitionStyle()
 * @method Collection selectDurationOfShow()
 * @method Collection selectDurationOfChange()
 * @method Collection selectPic()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectVisible()
 * @method Collection selectOrderNum()
 * @method Collection selectDate()
 * @method Collection selectShowDate1()
 * @method Collection selectShowDate2()
 * @method Collection selectShowTime1()
 * @method Collection selectShowTime2()
 * @method Collection selectShowOnWeekdays()
 * @method Collection selectShowOnHolidays()
 */
class Collection extends \diCollection
{
	const type = \diTypes::ad;
	protected $table = 'ads';
	protected $modelType = 'ad';
}