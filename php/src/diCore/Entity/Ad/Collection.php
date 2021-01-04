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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByBlockId($value, $operator = null)
 * @method $this filterByCategoryId($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByHref($value, $operator = null)
 * @method $this filterByHrefTarget($value, $operator = null)
 * @method $this filterByOnclick($value, $operator = null)
 * @method $this filterByButtonColor($value, $operator = null)
 * @method $this filterByTransition($value, $operator = null)
 * @method $this filterByTransitionStyle($value, $operator = null)
 * @method $this filterByDurationOfShow($value, $operator = null)
 * @method $this filterByDurationOfChange($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByShowDate1($value, $operator = null)
 * @method $this filterByShowDate2($value, $operator = null)
 * @method $this filterByShowTime1($value, $operator = null)
 * @method $this filterByShowTime2($value, $operator = null)
 * @method $this filterByShowOnWeekdays($value, $operator = null)
 * @method $this filterByShowOnHolidays($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByBlockId($direction = null)
 * @method $this orderByCategoryId($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByHref($direction = null)
 * @method $this orderByHrefTarget($direction = null)
 * @method $this orderByOnclick($direction = null)
 * @method $this orderByButtonColor($direction = null)
 * @method $this orderByTransition($direction = null)
 * @method $this orderByTransitionStyle($direction = null)
 * @method $this orderByDurationOfShow($direction = null)
 * @method $this orderByDurationOfChange($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByShowDate1($direction = null)
 * @method $this orderByShowDate2($direction = null)
 * @method $this orderByShowTime1($direction = null)
 * @method $this orderByShowTime2($direction = null)
 * @method $this orderByShowOnWeekdays($direction = null)
 * @method $this orderByShowOnHolidays($direction = null)
 *
 * @method $this selectId()
 * @method $this selectBlockId()
 * @method $this selectCategoryId()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectHref()
 * @method $this selectHrefTarget()
 * @method $this selectOnclick()
 * @method $this selectButtonColor()
 * @method $this selectTransition()
 * @method $this selectTransitionStyle()
 * @method $this selectDurationOfShow()
 * @method $this selectDurationOfChange()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectVisible()
 * @method $this selectOrderNum()
 * @method $this selectDate()
 * @method $this selectShowDate1()
 * @method $this selectShowDate2()
 * @method $this selectShowTime1()
 * @method $this selectShowTime2()
 * @method $this selectShowOnWeekdays()
 * @method $this selectShowOnHolidays()
 */
class Collection extends \diCollection
{
	const type = \diTypes::ad;
	protected $table = 'ads';
	protected $modelType = 'ad';
}