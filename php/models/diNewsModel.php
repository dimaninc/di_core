<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2015
 * Time: 18:18
 */

/**
 * Class diNewsModel
 * Methods list for IDE
 *
 * @method string	getMenuTitle
 * @method string	getTitle
 * @method string	getShortContent
 * @method string	getContent
 * @method string	getHtmlTitle
 * @method string	getHtmlKeywords
 * @method string	getHtmlDescription
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method string	getDate
 * @method integer	getVisible
 * @method integer	getOrderNum
 * @method integer	getKarma
 * @method integer	getCommentsCount
 * @method string	getCommentsLastDate
 * @method integer	getCommentsEnabled
 *
 * @method bool hasMenuTitle
 * @method bool hasTitle
 * @method bool hasShortContent
 * @method bool hasContent
 * @method bool hasHtmlTitle
 * @method bool hasHtmlKeywords
 * @method bool hasHtmlDescription
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasDate
 * @method bool hasVisible
 * @method bool hasOrderNum
 * @method bool hasKarma
 * @method bool hasCommentsCount
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsEnabled
 *
 * @method diNewsModel setMenuTitle($value)
 * @method diNewsModel setTitle($value)
 * @method diNewsModel setShortContent($value)
 * @method diNewsModel setContent($value)
 * @method diNewsModel setHtmlTitle($value)
 * @method diNewsModel setHtmlKeywords($value)
 * @method diNewsModel setHtmlDescription($value)
 * @method diNewsModel setPic($value)
 * @method diNewsModel setPicW($value)
 * @method diNewsModel setPicH($value)
 * @method diNewsModel setPicT($value)
 * @method diNewsModel setPicTnW($value)
 * @method diNewsModel setPicTnH($value)
 * @method diNewsModel setDate($value)
 * @method diNewsModel setVisible($value)
 * @method diNewsModel setOrderNum($value)
 * @method diNewsModel setKarma($value)
 * @method diNewsModel setCommentsCount($value)
 * @method diNewsModel setCommentsLastDate($value)
 * @method diNewsModel setCommentsEnabled($value)
 */
class diNewsModel extends diBasePrevNextModel
{
	protected $table = "news";

	// prev/next stuff
	protected $customOrderByOptions = [
		"reverse" => true,
	];

	public function getSourceForSlug()
	{
		return mb_substr($this->getDate(), 0, 10) . " " . ($this->getMenuTitle() ?: $this->getTitle());
	}

	public function getHref()
	{
		return $this->__getPrefixForHref() . "/" . diCurrentCMS::ct("news") . "/" . $this->getSlug() . "/";
	}
}