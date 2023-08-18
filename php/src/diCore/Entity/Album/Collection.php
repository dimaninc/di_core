<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:56
 */

namespace diCore\Entity\Album;

use diCore\Data\Types;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterBySlug($value, $operator = null)
 * @method $this filterBySlugSource($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByCoverPhotoId($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByTop($value, $operator = null)
 * @method $this filterByCommentsEnabled($value, $operator = null)
 * @method $this filterByCommentsLastDate($value, $operator = null)
 * @method $this filterByCommentsCount($value, $operator = null)
 * @method $this filterByPhotosCount($value, $operator = null)
 * @method $this filterByVideosCount($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderBySlug($direction = null)
 * @method $this orderBySlugSource($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByCoverPhotoId($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByTop($direction = null)
 * @method $this orderByCommentsEnabled($direction = null)
 * @method $this orderByCommentsLastDate($direction = null)
 * @method $this orderByCommentsCount($direction = null)
 * @method $this orderByPhotosCount($direction = null)
 * @method $this orderByVideosCount($direction = null)
 *
 * @method $this selectId()
 * @method $this selectSlug()
 * @method $this selectSlugSource()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectCoverPhotoId()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicT()
 * @method $this selectDate()
 * @method $this selectOrderNum()
 * @method $this selectVisible()
 * @method $this selectTop()
 * @method $this selectCommentsEnabled()
 * @method $this selectCommentsLastDate()
 * @method $this selectCommentsCount()
 * @method $this selectPhotosCount()
 * @method $this selectVideosCount()
 */
class Collection extends \diCollection
{
    const type = Types::album;
    protected $table = 'albums';
    protected $modelType = 'album';
}
