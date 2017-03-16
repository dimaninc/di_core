<?php
/**
 * Created by diModelsManager
 * Date: 01.11.2016
 * Time: 10:55
 */

/**
 * Class diNewsCollection
 * Methods list for IDE
 *
 * @method diNewsCollection filterById($value, $operator = null)
 * @method diNewsCollection filterByCleanTitle($value, $operator = null)
 * @method diNewsCollection filterByMenuTitle($value, $operator = null)
 * @method diNewsCollection filterBySeasonId($value, $operator = null)
 * @method diNewsCollection filterByTitle($value, $operator = null)
 * @method diNewsCollection filterByShortContent($value, $operator = null)
 * @method diNewsCollection filterByContent($value, $operator = null)
 * @method diNewsCollection filterByHtmlTitle($value, $operator = null)
 * @method diNewsCollection filterByHtmlKeywords($value, $operator = null)
 * @method diNewsCollection filterByHtmlDescription($value, $operator = null)
 * @method diNewsCollection filterByPic($value, $operator = null)
 * @method diNewsCollection filterByPicW($value, $operator = null)
 * @method diNewsCollection filterByPicH($value, $operator = null)
 * @method diNewsCollection filterByPicT($value, $operator = null)
 * @method diNewsCollection filterByPicTnW($value, $operator = null)
 * @method diNewsCollection filterByPicTnH($value, $operator = null)
 * @method diNewsCollection filterByDate($value, $operator = null)
 * @method diNewsCollection filterByVisible($value, $operator = null)
 * @method diNewsCollection filterByOrderNum($value, $operator = null)
 * @method diNewsCollection filterByKarma($value, $operator = null)
 * @method diNewsCollection filterByCommentsCount($value, $operator = null)
 * @method diNewsCollection filterByCommentsLastDate($value, $operator = null)
 * @method diNewsCollection filterByCommentsEnabled($value, $operator = null)
 *
 * @method diNewsCollection orderById($direction = null)
 * @method diNewsCollection orderByCleanTitle($direction = null)
 * @method diNewsCollection orderByMenuTitle($direction = null)
 * @method diNewsCollection orderBySeasonId($direction = null)
 * @method diNewsCollection orderByTitle($direction = null)
 * @method diNewsCollection orderByShortContent($direction = null)
 * @method diNewsCollection orderByContent($direction = null)
 * @method diNewsCollection orderByHtmlTitle($direction = null)
 * @method diNewsCollection orderByHtmlKeywords($direction = null)
 * @method diNewsCollection orderByHtmlDescription($direction = null)
 * @method diNewsCollection orderByPic($direction = null)
 * @method diNewsCollection orderByPicW($direction = null)
 * @method diNewsCollection orderByPicH($direction = null)
 * @method diNewsCollection orderByPicT($direction = null)
 * @method diNewsCollection orderByPicTnW($direction = null)
 * @method diNewsCollection orderByPicTnH($direction = null)
 * @method diNewsCollection orderByDate($direction = null)
 * @method diNewsCollection orderByVisible($direction = null)
 * @method diNewsCollection orderByOrderNum($direction = null)
 * @method diNewsCollection orderByKarma($direction = null)
 * @method diNewsCollection orderByCommentsCount($direction = null)
 * @method diNewsCollection orderByCommentsLastDate($direction = null)
 * @method diNewsCollection orderByCommentsEnabled($direction = null)
 *
 * @method diNewsCollection selectId()
 * @method diNewsCollection selectCleanTitle()
 * @method diNewsCollection selectMenuTitle()
 * @method diNewsCollection selectSeasonId()
 * @method diNewsCollection selectTitle()
 * @method diNewsCollection selectShortContent()
 * @method diNewsCollection selectContent()
 * @method diNewsCollection selectHtmlTitle()
 * @method diNewsCollection selectHtmlKeywords()
 * @method diNewsCollection selectHtmlDescription()
 * @method diNewsCollection selectPic()
 * @method diNewsCollection selectPicW()
 * @method diNewsCollection selectPicH()
 * @method diNewsCollection selectPicT()
 * @method diNewsCollection selectPicTnW()
 * @method diNewsCollection selectPicTnH()
 * @method diNewsCollection selectDate()
 * @method diNewsCollection selectVisible()
 * @method diNewsCollection selectOrderNum()
 * @method diNewsCollection selectKarma()
 * @method diNewsCollection selectCommentsCount()
 * @method diNewsCollection selectCommentsLastDate()
 * @method diNewsCollection selectCommentsEnabled()
 */
class diNewsCollection extends diCollection
{
	const type = diTypes::news;
	protected $table = "news";
	protected $modelType = "news";
}