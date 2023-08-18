<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:17
 */

namespace diCore\Entity\Photo;

use diCore\Admin\Submit;
use diCore\Data\Types;
use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getAlbumId
 * @method string	getSlugSource
 * @method string	getTitle
 * @method string	getContent
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method string	getVisible
 * @method string	getTop
 * @method integer	getCommentsEnabled
 * @method string	getCommentsLastDate
 * @method integer	getCommentsCount
 * @method string	getDate
 * @method integer	getOrderNum
 *
 * @method bool hasAlbumId
 * @method bool hasSlugSource
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasVisible
 * @method bool hasTop
 * @method bool hasCommentsEnabled
 * @method bool hasCommentsLastDate
 * @method bool hasCommentsCount
 * @method bool hasDate
 * @method bool hasOrderNum
 *
 * @method $this setAlbumId($value)
 * @method $this setSlugSource($value)
 * @method $this setTitle($value)
 * @method $this setContent($value)
 * @method $this setPic($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setPicT($value)
 * @method $this setPicTnW($value)
 * @method $this setPicTnH($value)
 * @method $this setVisible($value)
 * @method $this setTop($value)
 * @method $this setCommentsEnabled($value)
 * @method $this setCommentsLastDate($value)
 * @method $this setCommentsCount($value)
 * @method $this setDate($value)
 * @method $this setOrderNum($value)
 */
class Model extends \diModel
{
    const type = Types::photo;
    const slug_field_name = self::SLUG_FIELD_NAME;
    const table = 'photos';
    protected $table = 'photos';

    /** @var \diCore\Entity\Album\Model */
    protected $album;

    public static $tokenAlignments = ['left', 'right', 'center', null];

    protected static $picStoreSettings = [
        'pic' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
            [
                'type' => Submit::IMAGE_TYPE_PREVIEW,
                'resize' => \diImage::DI_THUMB_FIT,
            ],
        ],
    ];

    /** @deprecated  */
    public static function getPicOptions()
    {
        return static::getPicStoreSettings('pic');
    }

    public function getToken($alignment = null)
    {
        $token = $this->getId()
            ? '[PHOTO-' . str_pad($this->getId(), 6, '0', STR_PAD_LEFT) . ']'
            : null;

        if ($token && $alignment) {
            $token .= '[' . strtoupper($alignment) . ']';
        }

        return $token;
    }

    public function getAllTokens()
    {
        $ar = [];

        foreach (static::$tokenAlignments as $alignment) {
            $ar[$alignment] = $this->getToken($alignment);
        }

        return $ar;
    }

    /**
     * Custom model template vars
     *
     * @return array
     */
    public function getCustomTemplateVars()
    {
        return extend(parent::getCustomTemplateVars(), [
            'token' => $this->getToken(),
        ]);
    }

    /**
     * Returns query conditions array for order_num calculating
     *
     * @return array
     */
    public function getQueryArForMove()
    {
        $ar = ["album_id = '{$this->getAlbumId()}'"];

        return $ar;
    }

    public function getPicSubFolders()
    {
        return '';
    }

    public function getPicsFolder()
    {
        return parent::getPicsFolder() . $this->getPicSubFolders();
    }

    public function getAlbum()
    {
        if (!$this->album) {
            $this->album = CollectionCache::getModel(
                Types::album,
                $this->getAlbumId(),
                true
            );
        }

        return $this->album;
    }
}
