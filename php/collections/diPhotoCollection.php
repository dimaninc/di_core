<?php
/**
 * Created by diModelsManager
 * Date: 25.05.2016
 * Time: 17:20
 */

/**
 * Class diPhotoCollection
 * Methods list for IDE
 *
 * @method diPhotoCollection filterById($value, $operator = null)
 * @method diPhotoCollection filterByAlbumId($value, $operator = null)
 * @method diPhotoCollection filterBySlug($value, $operator = null)
 * @method diPhotoCollection filterBySlugSource($value, $operator = null)
 * @method diPhotoCollection filterByTitle($value, $operator = null)
 * @method diPhotoCollection filterByContent($value, $operator = null)
 * @method diPhotoCollection filterByPic($value, $operator = null)
 * @method diPhotoCollection filterByPicW($value, $operator = null)
 * @method diPhotoCollection filterByPicH($value, $operator = null)
 * @method diPhotoCollection filterByPicT($value, $operator = null)
 * @method diPhotoCollection filterByPicTnW($value, $operator = null)
 * @method diPhotoCollection filterByPicTnH($value, $operator = null)
 * @method diPhotoCollection filterByVisible($value, $operator = null)
 * @method diPhotoCollection filterByCommentsEnabled($value, $operator = null)
 * @method diPhotoCollection filterByCommentsLastDate($value, $operator = null)
 * @method diPhotoCollection filterByCommentsCount($value, $operator = null)
 * @method diPhotoCollection filterByDate($value, $operator = null)
 * @method diPhotoCollection filterByOrderNum($value, $operator = null)
 *
 * @method diPhotoCollection orderById($direction = null)
 * @method diPhotoCollection orderByAlbumId($direction = null)
 * @method diPhotoCollection orderBySlug($direction = null)
 * @method diPhotoCollection orderBySlugSource($direction = null)
 * @method diPhotoCollection orderByTitle($direction = null)
 * @method diPhotoCollection orderByContent($direction = null)
 * @method diPhotoCollection orderByPic($direction = null)
 * @method diPhotoCollection orderByPicW($direction = null)
 * @method diPhotoCollection orderByPicH($direction = null)
 * @method diPhotoCollection orderByPicT($direction = null)
 * @method diPhotoCollection orderByPicTnW($direction = null)
 * @method diPhotoCollection orderByPicTnH($direction = null)
 * @method diPhotoCollection orderByVisible($direction = null)
 * @method diPhotoCollection orderByCommentsEnabled($direction = null)
 * @method diPhotoCollection orderByCommentsLastDate($direction = null)
 * @method diPhotoCollection orderByCommentsCount($direction = null)
 * @method diPhotoCollection orderByDate($direction = null)
 * @method diPhotoCollection orderByOrderNum($direction = null)
 *
 * @method diPhotoCollection selectId()
 * @method diPhotoCollection selectAlbumId()
 * @method diPhotoCollection selectSlug()
 * @method diPhotoCollection selectSlugSource()
 * @method diPhotoCollection selectTitle()
 * @method diPhotoCollection selectContent()
 * @method diPhotoCollection selectPic()
 * @method diPhotoCollection selectPicW()
 * @method diPhotoCollection selectPicH()
 * @method diPhotoCollection selectPicT()
 * @method diPhotoCollection selectPicTnW()
 * @method diPhotoCollection selectPicTnH()
 * @method diPhotoCollection selectVisible()
 * @method diPhotoCollection selectCommentsEnabled()
 * @method diPhotoCollection selectCommentsLastDate()
 * @method diPhotoCollection selectCommentsCount()
 * @method diPhotoCollection selectDate()
 * @method diPhotoCollection selectOrderNum()
 */
class diPhotoCollection extends diCollection
{
	protected $table = "photos";
	protected $modelType = "photo";
}