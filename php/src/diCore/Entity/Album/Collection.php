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
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterBySlug($value, $operator = null)
 * @method Collection filterBySlugSource($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByCoverPhotoId($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByPicT($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByTop($value, $operator = null)
 * @method Collection filterByCommentsEnabled($value, $operator = null)
 * @method Collection filterByCommentsLastDate($value, $operator = null)
 * @method Collection filterByCommentsCount($value, $operator = null)
 * @method Collection filterByPhotosCount($value, $operator = null)
 * @method Collection filterByVideosCount($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderBySlug($direction = null)
 * @method Collection orderBySlugSource($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByCoverPhotoId($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByPicT($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByTop($direction = null)
 * @method Collection orderByCommentsEnabled($direction = null)
 * @method Collection orderByCommentsLastDate($direction = null)
 * @method Collection orderByCommentsCount($direction = null)
 * @method Collection orderByPhotosCount($direction = null)
 * @method Collection orderByVideosCount($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectSlug()
 * @method Collection selectSlugSource()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectCoverPhotoId()
 * @method Collection selectPic()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectPicT()
 * @method Collection selectDate()
 * @method Collection selectOrderNum()
 * @method Collection selectVisible()
 * @method Collection selectTop()
 * @method Collection selectCommentsEnabled()
 * @method Collection selectCommentsLastDate()
 * @method Collection selectCommentsCount()
 * @method Collection selectPhotosCount()
 * @method Collection selectVideosCount()
 */
class Collection extends \diCollection
{
	const type = Types::album;
	protected $table = 'albums';
	protected $modelType = 'album';
}