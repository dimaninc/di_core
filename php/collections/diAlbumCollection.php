<?php
/**
 * Created by diModelsManager
 * Date: 25.05.2016
 * Time: 17:20
 */

/**
 * Class diAlbumCollection
 * Methods list for IDE
 *
 * @method diAlbumCollection filterById($value, $operator = null)
 * @method diAlbumCollection filterBySlug($value, $operator = null)
 * @method diAlbumCollection filterBySlugSource($value, $operator = null)
 * @method diAlbumCollection filterByTitle($value, $operator = null)
 * @method diAlbumCollection filterByContent($value, $operator = null)
 * @method diAlbumCollection filterByCoverPhotoId($value, $operator = null)
 * @method diAlbumCollection filterByPic($value, $operator = null)
 * @method diAlbumCollection filterByPicW($value, $operator = null)
 * @method diAlbumCollection filterByPicH($value, $operator = null)
 * @method diAlbumCollection filterByPicT($value, $operator = null)
 * @method diAlbumCollection filterByDate($value, $operator = null)
 * @method diAlbumCollection filterByOrderNum($value, $operator = null)
 * @method diAlbumCollection filterByVisible($value, $operator = null)
 * @method diAlbumCollection filterByTop($value, $operator = null)
 * @method diAlbumCollection filterByCommentsEnabled($value, $operator = null)
 * @method diAlbumCollection filterByCommentsLastDate($value, $operator = null)
 * @method diAlbumCollection filterByCommentsCount($value, $operator = null)
 * @method diAlbumCollection filterByPhotosCount($value, $operator = null)
 * @method diAlbumCollection filterByVideosCount($value, $operator = null)
 *
 * @method diAlbumCollection orderById($direction = null)
 * @method diAlbumCollection orderBySlug($direction = null)
 * @method diAlbumCollection orderBySlugSource($direction = null)
 * @method diAlbumCollection orderByTitle($direction = null)
 * @method diAlbumCollection orderByContent($direction = null)
 * @method diAlbumCollection orderByCoverPhotoId($direction = null)
 * @method diAlbumCollection orderByPic($direction = null)
 * @method diAlbumCollection orderByPicW($direction = null)
 * @method diAlbumCollection orderByPicH($direction = null)
 * @method diAlbumCollection orderByPicT($direction = null)
 * @method diAlbumCollection orderByDate($direction = null)
 * @method diAlbumCollection orderByOrderNum($direction = null)
 * @method diAlbumCollection orderByVisible($direction = null)
 * @method diAlbumCollection orderByTop($direction = null)
 * @method diAlbumCollection orderByCommentsEnabled($direction = null)
 * @method diAlbumCollection orderByCommentsLastDate($direction = null)
 * @method diAlbumCollection orderByCommentsCount($direction = null)
 * @method diAlbumCollection orderByPhotosCount($direction = null)
 * @method diAlbumCollection orderByVideosCount($direction = null)
 *
 * @method diAlbumCollection selectId()
 * @method diAlbumCollection selectSlug()
 * @method diAlbumCollection selectSlugSource()
 * @method diAlbumCollection selectTitle()
 * @method diAlbumCollection selectContent()
 * @method diAlbumCollection selectCoverPhotoId()
 * @method diAlbumCollection selectPic()
 * @method diAlbumCollection selectPicW()
 * @method diAlbumCollection selectPicH()
 * @method diAlbumCollection selectPicT()
 * @method diAlbumCollection selectDate()
 * @method diAlbumCollection selectOrderNum()
 * @method diAlbumCollection selectVisible()
 * @method diAlbumCollection selectTop()
 * @method diAlbumCollection selectCommentsEnabled()
 * @method diAlbumCollection selectCommentsLastDate()
 * @method diAlbumCollection selectCommentsCount()
 * @method diAlbumCollection selectPhotosCount()
 * @method diAlbumCollection selectVideosCount()
 */
class diAlbumCollection extends diCollection
{
	const type = diTypes::album;
	protected $table = "albums";
	protected $modelType = "album";
}