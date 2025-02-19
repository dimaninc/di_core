<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.03.2017
 * Time: 21:47
 */

namespace diCore\Entity\Content;

use diCore\Admin\Submit;
use diCore\Data\Types;
use diCore\Database\FieldType;
use diCore\Entity\AdBlock\Model as AdBlock;
use diCore\Traits\Model\AutoTimestamps;
use diCore\Traits\Model\Hierarchy;
use diCore\Traits\Model\JsonProperties;

/**
 * Class Model
 * Methods list for IDE
 *
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
 * @method integer	getVisible
 * @method integer	getVisibleTop
 * @method integer	getVisibleBottom
 * @method integer	getVisibleLeft
 * @method integer	getVisibleRight
 * @method integer	getVisibleLoggedIn
 * @method integer	getToShowContent
 * @method integer	getTop
 * @method integer	getCommentsCount
 * @method string	getCommentsLastDate
 * @method integer	getCommentsEnabled
 * @method string	getShowLinks
 * @method integer	getAdBlockId
 *
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
 * @method bool hasVisible
 * @method bool hasVisibleTop
 * @method bool hasVisibleBottom
 * @method bool hasVisibleLeft
 * @method bool hasVisibleRight
 * @method bool hasVisibleLoggedIn
 * @method bool hasToShowContent
 * @method bool hasTop
 * @method bool hasCommentsCount
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsEnabled
 * @method bool hasShowLinks
 * @method bool hasAdBlockId
 *
 * @method $this setMenuTitle($value)
 * @method $this setType($value)
 * @method $this setTitle($value)
 * @method $this setCaption($value)
 * @method $this setHtmlTitle($value)
 * @method $this setHtmlKeywords($value)
 * @method $this setHtmlDescription($value)
 * @method $this setContent($value)
 * @method $this setShortContent($value)
 * @method $this setLinksContent($value)
 * @method $this setPic($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setPicT($value)
 * @method $this setPic2($value)
 * @method $this setPic2W($value)
 * @method $this setPic2H($value)
 * @method $this setPic2T($value)
 * @method $this setIco($value)
 * @method $this setIcoW($value)
 * @method $this setIcoH($value)
 * @method $this setIcoT($value)
 * @method $this setColor($value)
 * @method $this setBackgroundColor($value)
 * @method $this setClass($value)
 * @method $this setMenuClass($value)
 * @method $this setVisible($value)
 * @method $this setVisibleTop($value)
 * @method $this setVisibleBottom($value)
 * @method $this setVisibleLeft($value)
 * @method $this setVisibleRight($value)
 * @method $this setVisibleLoggedIn($value)
 * @method $this setToShowContent($value)
 * @method $this setTop($value)
 * @method $this setCommentsCount($value)
 * @method $this setCommentsLastDate($value)
 * @method $this setCommentsEnabled($value)
 * @method $this setShowLinks($value)
 * @method $this setAdBlockId($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;
    use Hierarchy;
    use JsonProperties;

    const type = Types::content;
    const table = 'content';
    const SKIP_TIMESTAMP_FIELDS = false;
    protected $table = 'content';

    protected static $publicFields = [
        'clean_title',
        'title',
        'caption',
        'content',
        'visible',
        'html_title',
        'html_description',
        'html_keywords',
    ];

    protected static $fieldTypes = [
        'properties' => FieldType::json,
    ];

    protected static $picStoreSettings = [
        'pic' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
        ],
        'pic2' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
        ],
        'ico' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
        ],
    ];

    /** @var AdBlock */
    protected $adBlock;

    public function getHref()
    {
        switch ($this->getType()) {
            case 'home':
                return $this->__getPrefixForHref() . '/';

            case 'href':
                return $this->getMenuTitle();

            case 'logout':
                return \diLib::getWorkerPath('auth', 'logout') .
                    '?back=' .
                    urlencode(\diRequest::requestUri());

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

    public function getAdBlock()
    {
        if (!$this->adBlock) {
            $this->adBlock = AdBlock::createById($this->getAdBlockId());
        }

        return $this->adBlock;
    }

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
}
