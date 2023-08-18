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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByAlbumId($value, $operator = null)
 * @method $this filterBySlug($value, $operator = null)
 * @method $this filterBySlugSource($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByPic($value, $operator = null)
 * @method $this filterByPicW($value, $operator = null)
 * @method $this filterByPicH($value, $operator = null)
 * @method $this filterByPicT($value, $operator = null)
 * @method $this filterByPicTnW($value, $operator = null)
 * @method $this filterByPicTnH($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByTop($value, $operator = null)
 * @method $this filterByCommentsEnabled($value, $operator = null)
 * @method $this filterByCommentsLastDate($value, $operator = null)
 * @method $this filterByCommentsCount($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByAlbumId($direction = null)
 * @method $this orderBySlug($direction = null)
 * @method $this orderBySlugSource($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByPic($direction = null)
 * @method $this orderByPicW($direction = null)
 * @method $this orderByPicH($direction = null)
 * @method $this orderByPicT($direction = null)
 * @method $this orderByPicTnW($direction = null)
 * @method $this orderByPicTnH($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByTop($direction = null)
 * @method $this orderByCommentsEnabled($direction = null)
 * @method $this orderByCommentsLastDate($direction = null)
 * @method $this orderByCommentsCount($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByOrderNum($direction = null)
 *
 * @method $this selectId()
 * @method $this selectAlbumId()
 * @method $this selectSlug()
 * @method $this selectSlugSource()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectPic()
 * @method $this selectPicW()
 * @method $this selectPicH()
 * @method $this selectPicT()
 * @method $this selectPicTnW()
 * @method $this selectPicTnH()
 * @method $this selectVisible()
 * @method $this selectTop()
 * @method $this selectCommentsEnabled()
 * @method $this selectCommentsLastDate()
 * @method $this selectCommentsCount()
 * @method $this selectDate()
 * @method $this selectOrderNum()
 */
class Collection extends \diCollection
{
    const type = Types::photo;
    protected $table = 'photos';
    protected $modelType = 'photo';
}
