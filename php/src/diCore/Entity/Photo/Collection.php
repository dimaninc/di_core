<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:17
 */

namespace diCore\Entity\Photo;

use diCore\Data\Types;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByAlbumId($value, $operator = null)
 * @method Collection filterBySlug($value, $operator = null)
 * @method Collection filterBySlugSource($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByPicT($value, $operator = null)
 * @method Collection filterByPicTnW($value, $operator = null)
 * @method Collection filterByPicTnH($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByTop($value, $operator = null)
 * @method Collection filterByCommentsEnabled($value, $operator = null)
 * @method Collection filterByCommentsLastDate($value, $operator = null)
 * @method Collection filterByCommentsCount($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByAlbumId($direction = null)
 * @method Collection orderBySlug($direction = null)
 * @method Collection orderBySlugSource($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByPicT($direction = null)
 * @method Collection orderByPicTnW($direction = null)
 * @method Collection orderByPicTnH($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByTop($direction = null)
 * @method Collection orderByCommentsEnabled($direction = null)
 * @method Collection orderByCommentsLastDate($direction = null)
 * @method Collection orderByCommentsCount($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectAlbumId()
 * @method Collection selectSlug()
 * @method Collection selectSlugSource()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectPic()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectPicT()
 * @method Collection selectPicTnW()
 * @method Collection selectPicTnH()
 * @method Collection selectVisible()
 * @method Collection selectTop()
 * @method Collection selectCommentsEnabled()
 * @method Collection selectCommentsLastDate()
 * @method Collection selectCommentsCount()
 * @method Collection selectDate()
 * @method Collection selectOrderNum()
 */
class Collection extends \diCollection
{
	const type = Types::photo;
	protected $table = 'photos';
	protected $modelType = 'photo';
}