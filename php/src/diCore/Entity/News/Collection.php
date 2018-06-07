<?php
/**
 * Created by diModelsManager
 * Date: 01.11.2016
 * Time: 10:55
 */

namespace diCore\Entity\News;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByCleanTitle($value, $operator = null)
 * @method Collection filterByMenuTitle($value, $operator = null)
 * @method Collection filterBySeasonId($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByShortContent($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByHtmlTitle($value, $operator = null)
 * @method Collection filterByHtmlKeywords($value, $operator = null)
 * @method Collection filterByHtmlDescription($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByPicT($value, $operator = null)
 * @method Collection filterByPicTnW($value, $operator = null)
 * @method Collection filterByPicTnH($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByKarma($value, $operator = null)
 * @method Collection filterByCommentsCount($value, $operator = null)
 * @method Collection filterByCommentsLastDate($value, $operator = null)
 * @method Collection filterByCommentsEnabled($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByCleanTitle($direction = null)
 * @method Collection orderByMenuTitle($direction = null)
 * @method Collection orderBySeasonId($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByShortContent($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByHtmlTitle($direction = null)
 * @method Collection orderByHtmlKeywords($direction = null)
 * @method Collection orderByHtmlDescription($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByPicT($direction = null)
 * @method Collection orderByPicTnW($direction = null)
 * @method Collection orderByPicTnH($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByKarma($direction = null)
 * @method Collection orderByCommentsCount($direction = null)
 * @method Collection orderByCommentsLastDate($direction = null)
 * @method Collection orderByCommentsEnabled($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectCleanTitle()
 * @method Collection selectMenuTitle()
 * @method Collection selectSeasonId()
 * @method Collection selectTitle()
 * @method Collection selectShortContent()
 * @method Collection selectContent()
 * @method Collection selectHtmlTitle()
 * @method Collection selectHtmlKeywords()
 * @method Collection selectHtmlDescription()
 * @method Collection selectPic()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectPicT()
 * @method Collection selectPicTnW()
 * @method Collection selectPicTnH()
 * @method Collection selectDate()
 * @method Collection selectVisible()
 * @method Collection selectOrderNum()
 * @method Collection selectKarma()
 * @method Collection selectCommentsCount()
 * @method Collection selectCommentsLastDate()
 * @method Collection selectCommentsEnabled()
 */
class Collection extends \diCollection
{
    const type = \diTypes::news;
    protected $table = 'news';
    protected $modelType = 'news';
}