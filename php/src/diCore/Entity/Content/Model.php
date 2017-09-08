<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 21:47
 */

namespace diCore\Entity\Content;

use diCore\Data\Types;

/**
 * Class Model
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
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
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
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 * @method bool hasCommentsCount
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsEnabled
 * @method bool hasShowLinks
 * @method bool hasAdBlockId
 *
 * @method Model setParent($value)
 * @method Model setMenuTitle($value)
 * @method Model setType($value)
 * @method Model setTitle($value)
 * @method Model setCaption($value)
 * @method Model setHtmlTitle($value)
 * @method Model setHtmlKeywords($value)
 * @method Model setHtmlDescription($value)
 * @method Model setContent($value)
 * @method Model setShortContent($value)
 * @method Model setLinksContent($value)
 * @method Model setPic($value)
 * @method Model setPicW($value)
 * @method Model setPicH($value)
 * @method Model setPicT($value)
 * @method Model setPic2($value)
 * @method Model setPic2W($value)
 * @method Model setPic2H($value)
 * @method Model setPic2T($value)
 * @method Model setIco($value)
 * @method Model setIcoW($value)
 * @method Model setIcoH($value)
 * @method Model setIcoT($value)
 * @method Model setColor($value)
 * @method Model setBackgroundColor($value)
 * @method Model setClass($value)
 * @method Model setMenuClass($value)
 * @method Model setLevelNum($value)
 * @method Model setVisible($value)
 * @method Model setVisibleTop($value)
 * @method Model setVisibleBottom($value)
 * @method Model setVisibleLeft($value)
 * @method Model setVisibleRight($value)
 * @method Model setVisibleLoggedIn($value)
 * @method Model setToShowContent($value)
 * @method Model setOrderNum($value)
 * @method Model setTop($value)
 * @method Model setCommentsCount($value)
 * @method Model setCommentsLastDate($value)
 * @method Model setCommentsEnabled($value)
 * @method Model setCreatedAt($value)
 * @method Model setUpdatedAt($value)
 * @method Model setShowLinks($value)
 * @method Model setAdBlockId($value)
 */
class Model extends \diModel
{
	const type = Types::content;
	protected $table = 'content';

	public function getHref()
	{
		switch ($this->getType())
		{
			case 'home':
				return $this->__getPrefixForHref() . '/';

			case 'href':
				return $this->getMenuTitle();

			case 'logout':
				return \diLib::getWorkerPath('auth', 'logout') . '?back=' . urlencode(\diRequest::requestUri());

			default:
				return $this->getDefaultHref();
		}
	}

	protected function getDefaultHref()
	{
		return $this->__getPrefixForHref() . '/' . $this->getSlug() . '/';
	}

	public function getSourceForSlug()
	{
		return $this->getMenuTitle() ?: $this->getTitle();
	}
}