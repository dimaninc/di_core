<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 21:48
 */

namespace diCore\Entity\Content;

use diCore\Traits\Collection\AutoTimestamps;
use diCore\Traits\Collection\Hierarchy;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByCleanTitle($value, $operator = null)
 * @method $this filterByMenuTitle($value, $operator = null)
 * @method $this filterByType($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByCaption($value, $operator = null)
 * @method $this filterByHtmlTitle($value, $operator = null)
 * @method $this filterByHtmlKeywords($value, $operator = null)
 * @method $this filterByHtmlDescription($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByShortContent($value, $operator = null)
 * @method $this filterByLinksContent($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByPic2($value, $operator = null)
 * @method $this filterByPic2W($value, $operator = null)
 * @method $this filterByPic2H($value, $operator = null)
 * @method $this filterByPic2T($value, $operator = null)
 * @method $this filterByIco($value, $operator = null)
 * @method $this filterByIcoW($value, $operator = null)
 * @method $this filterByIcoH($value, $operator = null)
 * @method $this filterByIcoT($value, $operator = null)
 * @method $this filterByColor($value, $operator = null)
 * @method $this filterByBackgroundColor($value, $operator = null)
 * @method $this filterByClass($value, $operator = null)
 * @method $this filterByMenuClass($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByVisibleTop($value, $operator = null)
 * @method $this filterByVisibleBottom($value, $operator = null)
 * @method $this filterByVisibleLeft($value, $operator = null)
 * @method $this filterByVisibleRight($value, $operator = null)
 * @method $this filterByVisibleLoggedIn($value, $operator = null)
 * @method $this filterByToShowContent($value, $operator = null)
 * @method $this filterByTop($value, $operator = null)
 * @method $this filterByCommentsCount($value, $operator = null)
 * @method $this filterByCommentsLastDate($value, $operator = null)
 * @method $this filterByCommentsEnabled($value, $operator = null)
 * @method $this filterByAdBlockId($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByCleanTitle($direction = null)
 * @method $this orderByMenuTitle($direction = null)
 * @method $this orderByType($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByCaption($direction = null)
 * @method $this orderByHtmlTitle($direction = null)
 * @method $this orderByHtmlKeywords($direction = null)
 * @method $this orderByHtmlDescription($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByShortContent($direction = null)
 * @method $this orderByLinksContent($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByPic2($direction = null)
 * @method $this orderByPic2W($direction = null)
 * @method $this orderByPic2H($direction = null)
 * @method $this orderByPic2T($direction = null)
 * @method $this orderByIco($direction = null)
 * @method $this orderByIcoW($direction = null)
 * @method $this orderByIcoH($direction = null)
 * @method $this orderByIcoT($direction = null)
 * @method $this orderByColor($direction = null)
 * @method $this orderByBackgroundColor($direction = null)
 * @method $this orderByClass($direction = null)
 * @method $this orderByMenuClass($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByVisibleTop($direction = null)
 * @method $this orderByVisibleBottom($direction = null)
 * @method $this orderByVisibleLeft($direction = null)
 * @method $this orderByVisibleRight($direction = null)
 * @method $this orderByVisibleLoggedIn($direction = null)
 * @method $this orderByToShowContent($direction = null)
 * @method $this orderByTop($direction = null)
 * @method $this orderByCommentsCount($direction = null)
 * @method $this orderByCommentsLastDate($direction = null)
 * @method $this orderByCommentsEnabled($direction = null)
 * @method $this orderByAdBlockId($direction = null)
 *
 * @method $this selectId()
 * @method $this selectCleanTitle()
 * @method $this selectMenuTitle()
 * @method $this selectType()
 * @method $this selectTitle()
 * @method $this selectCaption()
 * @method $this selectHtmlTitle()
 * @method $this selectHtmlKeywords()
 * @method $this selectHtmlDescription()
 * @method $this selectContent()
 * @method $this selectShortContent()
 * @method $this selectLinksContent()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicT()
 * @method $this selectPic2()
 * @method $this selectPic2W()
 * @method $this selectPic2H()
 * @method $this selectPic2T()
 * @method $this selectIco()
 * @method $this selectIcoW()
 * @method $this selectIcoH()
 * @method $this selectIcoT()
 * @method $this selectColor()
 * @method $this selectBackgroundColor()
 * @method $this selectClass()
 * @method $this selectMenuClass()
 * @method $this selectVisible()
 * @method $this selectVisibleTop()
 * @method $this selectVisibleBottom()
 * @method $this selectVisibleLeft()
 * @method $this selectVisibleRight()
 * @method $this selectVisibleLoggedIn()
 * @method $this selectToShowContent()
 * @method $this selectTop()
 * @method $this selectCommentsCount()
 * @method $this selectCommentsLastDate()
 * @method $this selectCommentsEnabled()
 * @method $this selectAdBlockId()
 */
class Collection extends \diCollection
{
    use AutoTimestamps;
    use Hierarchy;

	const type = \diTypes::content;
	protected $table = 'content';
	protected $modelType = 'content';
}
