<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2015
 * Time: 18:18
 */

namespace diCore\Entity\News;

/**
 * Class Model
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
 * @method Model setMenuTitle($value)
 * @method Model setTitle($value)
 * @method Model setShortContent($value)
 * @method Model setContent($value)
 * @method Model setHtmlTitle($value)
 * @method Model setHtmlKeywords($value)
 * @method Model setHtmlDescription($value)
 * @method Model setPic($value)
 * @method Model setPicW($value)
 * @method Model setPicH($value)
 * @method Model setPicT($value)
 * @method Model setPicTnW($value)
 * @method Model setPicTnH($value)
 * @method Model setDate($value)
 * @method Model setVisible($value)
 * @method Model setOrderNum($value)
 * @method Model setKarma($value)
 * @method Model setCommentsCount($value)
 * @method Model setCommentsLastDate($value)
 * @method Model setCommentsEnabled($value)
 */
class Model extends \diBasePrevNextModel
{
    const type = \diTypes::news;
    protected $table = 'news';

    // prev/next stuff
    protected $customOrderByOptions = [
        'reverse' => true,
    ];

    public function getSourceForSlug()
    {
        return mb_substr($this->getDate(), 0, 10) . ' ' . ($this->getMenuTitle() ?: $this->getTitle());
    }

    public function getHref()
    {
        return $this->__getPrefixForHref() . '/' . \diCurrentCMS::ct('news') . '/' . $this->getSlug() . '/';
    }
}