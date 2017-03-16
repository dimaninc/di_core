<?php
/**
 * Created by diModelsManager
 * Date: 09.10.2015
 * Time: 18:00
 */

/**
 * Class diContentModel
 * Methods list for IDE
 *
 * @method integer	getParent
 * @method string	getMenuTitle
 * @method string	getType
 * @method string	getTitle
 * @method string	getCaption
 * @method string	getHtmlTitle
 * @method string	getHtmlKeywords
 * @method string	getHtmlDescription
 * @method string	getContent
 * @method string	getShortContent
 * @method string	getLinksContent
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method string	getPic2
 * @method integer	getPic2W
 * @method integer	getPic2H
 * @method integer	getPic2T
 * @method string	getIco
 * @method integer	getIcoW
 * @method integer	getIcoH
 * @method integer	getIcoT
 * @method string	getColor
 * @method string	getBackgroundColor
 * @method string	getClass
 * @method string	getMenuClass
 * @method integer	getLevelNum
 * @method integer	getVisible
 * @method integer	getVisibleTop
 * @method integer	getVisibleBottom
 * @method integer	getVisibleLeft
 * @method integer	getVisibleRight
 * @method integer	getVisibleLoggedIn
 * @method integer	getToShowContent
 * @method integer	getOrderNum
 * @method integer	getTop
 * @method integer	getCommentsCount
 * @method string	getCommentsLastDate
 * @method integer	getCommentsEnabled
 * @method string	getShowLinks
 * @method integer	getAdBlockId
 *
 * @method bool hasParent
 * @method bool hasMenuTitle
 * @method bool hasType
 * @method bool hasTitle
 * @method bool hasCaption
 * @method bool hasHtmlTitle
 * @method bool hasHtmlKeywords
 * @method bool hasHtmlDescription
 * @method bool hasContent
 * @method bool hasShortContent
 * @method bool hasLinksContent
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasPic2
 * @method bool hasPic2W
 * @method bool hasPic2H
 * @method bool hasPic2T
 * @method bool hasIco
 * @method bool hasIcoW
 * @method bool hasIcoH
 * @method bool hasIcoT
 * @method bool hasColor
 * @method bool hasBackgroundColor
 * @method bool hasClass
 * @method bool hasMenuClass
 * @method bool hasLevelNum
 * @method bool hasVisible
 * @method bool hasVisibleTop
 * @method bool hasVisibleBottom
 * @method bool hasVisibleLeft
 * @method bool hasVisibleRight
 * @method bool hasVisibleLoggedIn
 * @method bool hasToShowContent
 * @method bool hasOrderNum
 * @method bool hasTop
 * @method bool hasCommentsCount
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsEnabled
 * @method bool hasShowLinks
 * @method bool hasAdBlockId
 *
 * @method diContentModel setParent($value)
 * @method diContentModel setMenuTitle($value)
 * @method diContentModel setType($value)
 * @method diContentModel setTitle($value)
 * @method diContentModel setCaption($value)
 * @method diContentModel setHtmlTitle($value)
 * @method diContentModel setHtmlKeywords($value)
 * @method diContentModel setHtmlDescription($value)
 * @method diContentModel setContent($value)
 * @method diContentModel setShortContent($value)
 * @method diContentModel setLinksContent($value)
 * @method diContentModel setPic($value)
 * @method diContentModel setPicW($value)
 * @method diContentModel setPicH($value)
 * @method diContentModel setPicT($value)
 * @method diContentModel setPic2($value)
 * @method diContentModel setPic2W($value)
 * @method diContentModel setPic2H($value)
 * @method diContentModel setPic2T($value)
 * @method diContentModel setIco($value)
 * @method diContentModel setIcoW($value)
 * @method diContentModel setIcoH($value)
 * @method diContentModel setIcoT($value)
 * @method diContentModel setColor($value)
 * @method diContentModel setBackgroundColor($value)
 * @method diContentModel setClass($value)
 * @method diContentModel setMenuClass($value)
 * @method diContentModel setLevelNum($value)
 * @method diContentModel setVisible($value)
 * @method diContentModel setVisibleTop($value)
 * @method diContentModel setVisibleBottom($value)
 * @method diContentModel setVisibleLeft($value)
 * @method diContentModel setVisibleRight($value)
 * @method diContentModel setVisibleLoggedIn($value)
 * @method diContentModel setToShowContent($value)
 * @method diContentModel setOrderNum($value)
 * @method diContentModel setTop($value)
 * @method diContentModel setCommentsCount($value)
 * @method diContentModel setCommentsLastDate($value)
 * @method diContentModel setCommentsEnabled($value)
 * @method diContentModel setShowLinks($value)
 * @method diContentModel setAdBlockId($value)
 */
class diContentModel extends diModel
{
	const type = diTypes::content;
	protected $table = "content";

	public function getHref()
	{
		switch ($this->getType())
		{
			case "home":
				return $this->__getPrefixForHref() . "/";

			case "href":
				return $this->getMenuTitle();

			case "logout":
				return diLib::getWorkerPath("auth", "logout") . "?back=" . urlencode(diRequest::server("REQUEST_URI"));

			default:
				return $this->__getPrefixForHref() . "/" . $this->getSlug() . "/";
		}
	}

	public function getSourceForSlug()
	{
		return $this->getMenuTitle() ?: $this->getTitle();
	}
}