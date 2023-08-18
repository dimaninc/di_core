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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByCleanTitle($value, $operator = null)
 * @method $this filterByMenuTitle($value, $operator = null)
 * @method $this filterBySeasonId($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByShortContent($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByHtmlTitle($value, $operator = null)
 * @method $this filterByHtmlKeywords($value, $operator = null)
 * @method $this filterByHtmlDescription($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByPicTnW($value, $operator = null)
 * @method $this filterByPicTnH($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByKarma($value, $operator = null)
 * @method $this filterByCommentsCount($value, $operator = null)
 * @method $this filterByCommentsLastDate($value, $operator = null)
 * @method $this filterByCommentsEnabled($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByCleanTitle($direction = null)
 * @method $this orderByMenuTitle($direction = null)
 * @method $this orderBySeasonId($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByShortContent($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByHtmlTitle($direction = null)
 * @method $this orderByHtmlKeywords($direction = null)
 * @method $this orderByHtmlDescription($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByPicTnW($direction = null)
 * @method $this orderByPicTnH($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByKarma($direction = null)
 * @method $this orderByCommentsCount($direction = null)
 * @method $this orderByCommentsLastDate($direction = null)
 * @method $this orderByCommentsEnabled($direction = null)
 *
 * @method $this selectId()
 * @method $this selectCleanTitle()
 * @method $this selectMenuTitle()
 * @method $this selectSeasonId()
 * @method $this selectTitle()
 * @method $this selectShortContent()
 * @method $this selectContent()
 * @method $this selectHtmlTitle()
 * @method $this selectHtmlKeywords()
 * @method $this selectHtmlDescription()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicT()
 * @method $this selectPicTnW()
 * @method $this selectPicTnH()
 * @method $this selectDate()
 * @method $this selectVisible()
 * @method $this selectOrderNum()
 * @method $this selectKarma()
 * @method $this selectCommentsCount()
 * @method $this selectCommentsLastDate()
 * @method $this selectCommentsEnabled()
 */
class Collection extends \diCollection
{
    const type = \diTypes::news;
    protected $table = 'news';
    protected $modelType = 'news';
}
