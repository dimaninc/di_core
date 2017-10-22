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
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByAlbumId($value, $operator = null)
 * @method Collection filterByVendor($value, $operator = null)
 * @method Collection filterByVendorVideoUid($value, $operator = null)
 * @method Collection filterBySlug($value, $operator = null)
 * @method Collection filterBySlugSource($value, $operator = null)
 * @method Collection filterBySeasonId($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByEmbed($value, $operator = null)
 * @method Collection filterByVideoMp4($value, $operator = null)
 * @method Collection filterByVideoM4v($value, $operator = null)
 * @method Collection filterByVideoOgv($value, $operator = null)
 * @method Collection filterByVideoWebm($value, $operator = null)
 * @method Collection filterByVideoW($value, $operator = null)
 * @method Collection filterByVideoH($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByPicW($value, $operator = null)
 * @method Collection filterByPicH($value, $operator = null)
 * @method Collection filterByPicT($value, $operator = null)
 * @method Collection filterByPicTnW($value, $operator = null)
 * @method Collection filterByPicTnH($value, $operator = null)
 * @method Collection filterByViewsCount($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByTop($value, $operator = null)
 * @method Collection filterByCommentsEnabled($value, $operator = null)
 * @method Collection filterByCommentsLastDate($value, $operator = null)
 * @method Collection filterByCommentsCount($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByAlbumId($direction = null)
 * @method Collection orderByVendor($direction = null)
 * @method Collection orderByVendorVideoUid($direction = null)
 * @method Collection orderBySlug($direction = null)
 * @method Collection orderBySlugSource($direction = null)
 * @method Collection orderBySeasonId($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByEmbed($direction = null)
 * @method Collection orderByVideoMp4($direction = null)
 * @method Collection orderByVideoM4v($direction = null)
 * @method Collection orderByVideoOgv($direction = null)
 * @method Collection orderByVideoWebm($direction = null)
 * @method Collection orderByVideoW($direction = null)
 * @method Collection orderByVideoH($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByPicW($direction = null)
 * @method Collection orderByPicH($direction = null)
 * @method Collection orderByPicT($direction = null)
 * @method Collection orderByPicTnW($direction = null)
 * @method Collection orderByPicTnH($direction = null)
 * @method Collection orderByViewsCount($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByTop($direction = null)
 * @method Collection orderByCommentsEnabled($direction = null)
 * @method Collection orderByCommentsLastDate($direction = null)
 * @method Collection orderByCommentsCount($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectAlbumId()
 * @method Collection selectVendor()
 * @method Collection selectVendorVideoUid()
 * @method Collection selectSlug()
 * @method Collection selectSlugSource()
 * @method Collection selectSeasonId()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectEmbed()
 * @method Collection selectVideoMp4()
 * @method Collection selectVideoM4v()
 * @method Collection selectVideoOgv()
 * @method Collection selectVideoWebm()
 * @method Collection selectVideoW()
 * @method Collection selectVideoH()
 * @method Collection selectPic()
 * @method Collection selectPicW()
 * @method Collection selectPicH()
 * @method Collection selectPicT()
 * @method Collection selectPicTnW()
 * @method Collection selectPicTnH()
 * @method Collection selectViewsCount()
 * @method Collection selectDate()
 * @method Collection selectOrderNum()
 * @method Collection selectVisible()
 * @method Collection selectTop()
 * @method Collection selectCommentsEnabled()
 * @method Collection selectCommentsLastDate()
 * @method Collection selectCommentsCount()
 */
class Collection extends \diCollection
{
	const type = Types::video;
	protected $table = 'videos';
	protected $modelType = 'video';
}