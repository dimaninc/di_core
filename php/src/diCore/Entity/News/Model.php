<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2015
 * Time: 18:18
 */

namespace diCore\Entity\News;

use diCore\Admin\Submit;

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
 * @method $this setMenuTitle($value)
 * @method $this setTitle($value)
 * @method $this setShortContent($value)
 * @method $this setContent($value)
 * @method $this setHtmlTitle($value)
 * @method $this setHtmlKeywords($value)
 * @method $this setHtmlDescription($value)
 * @method $this setPic($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setPicT($value)
 * @method $this setPicTnW($value)
 * @method $this setPicTnH($value)
 * @method $this setDate($value)
 * @method $this setVisible($value)
 * @method $this setOrderNum($value)
 * @method $this setKarma($value)
 * @method $this setCommentsCount($value)
 * @method $this setCommentsLastDate($value)
 * @method $this setCommentsEnabled($value)
 */
class Model extends \diBasePrevNextModel
{
    const type = \diTypes::news;
    const table = 'news';
    protected $table = 'news';

    protected static $picStoreSettings = [
        'pic' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
            [
                'type' => Submit::IMAGE_TYPE_PREVIEW,
                'resize' => \diImage::DI_THUMB_CROP,
            ],
        ],
    ];

    // prev/next stuff
    protected $customOrderByOptions = [
        'reverse' => true,
    ];

    public function getSourceForSlug()
    {
        return mb_substr($this->getDate(), 0, 10) . ' ' . ($this->getSlugSource() ?: $this->getMenuTitle() ?: $this->getTitle());
    }

    public function getHref()
    {
        return $this->__getPrefixForHref() . '/' . \diCurrentCMS::ct('news') . '/' . $this->getSlug() . '/';
    }
}
