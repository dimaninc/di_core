<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:19
 */

namespace diCore\Entity\Video;

use diCore\Data\Types;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByAlbumId($value, $operator = null)
 * @method $this filterByVendor($value, $operator = null)
 * @method $this filterByVendorVideoUid($value, $operator = null)
 * @method $this filterBySlug($value, $operator = null)
 * @method $this filterBySlugSource($value, $operator = null)
 * @method $this filterBySeasonId($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByEmbed($value, $operator = null)
 * @method $this filterByVideoMp4($value, $operator = null)
 * @method $this filterByVideoM4v($value, $operator = null)
 * @method $this filterByVideoOgv($value, $operator = null)
 * @method $this filterByVideoWebm($value, $operator = null)
 * @method $this filterByVideoW($value, $operator = null)
 * @method $this filterByVideoH($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByPicTnW($value, $operator = null)
 * @method $this filterByPicTnH($value, $operator = null)
 * @method $this filterByViewsCount($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByTop($value, $operator = null)
 * @method $this filterByCommentsEnabled($value, $operator = null)
 * @method $this filterByCommentsLastDate($value, $operator = null)
 * @method $this filterByCommentsCount($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByAlbumId($direction = null)
 * @method $this orderByVendor($direction = null)
 * @method $this orderByVendorVideoUid($direction = null)
 * @method $this orderBySlug($direction = null)
 * @method $this orderBySlugSource($direction = null)
 * @method $this orderBySeasonId($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByEmbed($direction = null)
 * @method $this orderByVideoMp4($direction = null)
 * @method $this orderByVideoM4v($direction = null)
 * @method $this orderByVideoOgv($direction = null)
 * @method $this orderByVideoWebm($direction = null)
 * @method $this orderByVideoW($direction = null)
 * @method $this orderByVideoH($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByPicTnW($direction = null)
 * @method $this orderByPicTnH($direction = null)
 * @method $this orderByViewsCount($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByTop($direction = null)
 * @method $this orderByCommentsEnabled($direction = null)
 * @method $this orderByCommentsLastDate($direction = null)
 * @method $this orderByCommentsCount($direction = null)
 *
 * @method $this selectId()
 * @method $this selectAlbumId()
 * @method $this selectVendor()
 * @method $this selectVendorVideoUid()
 * @method $this selectSlug()
 * @method $this selectSlugSource()
 * @method $this selectSeasonId()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectEmbed()
 * @method $this selectVideoMp4()
 * @method $this selectVideoM4v()
 * @method $this selectVideoOgv()
 * @method $this selectVideoWebm()
 * @method $this selectVideoW()
 * @method $this selectVideoH()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicT()
 * @method $this selectPicTnW()
 * @method $this selectPicTnH()
 * @method $this selectViewsCount()
 * @method $this selectDate()
 * @method $this selectOrderNum()
 * @method $this selectVisible()
 * @method $this selectTop()
 * @method $this selectCommentsEnabled()
 * @method $this selectCommentsLastDate()
 * @method $this selectCommentsCount()
 */
class Collection extends \diCollection
{
    const type = Types::video;
    protected $table = 'videos';
    protected $modelType = 'video';
}
