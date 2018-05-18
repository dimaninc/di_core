<?php
/**
 * Created by diModelsManager
 * Date: 04.09.2016
 * Time: 22:40
 */

namespace diCore\Entity\Tag;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterBySlug($value, $operator = null)
 * @method Collection filterBySlugSource($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByPic($value, $operator = null)
 * @method Collection filterByWeight($value, $operator = null)
 * @method Collection filterByHtmlTitle($value, $operator = null)
 * @method Collection filterByHtmlKeywords($value, $operator = null)
 * @method Collection filterByHtmlDescription($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderBySlug($direction = null)
 * @method Collection orderBySlugSource($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByPic($direction = null)
 * @method Collection orderByWeight($direction = null)
 * @method Collection orderByHtmlTitle($direction = null)
 * @method Collection orderByHtmlKeywords($direction = null)
 * @method Collection orderByHtmlDescription($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByDate($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectSlug()
 * @method Collection selectSlugSource()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectPic()
 * @method Collection selectWeight()
 * @method Collection selectHtmlTitle()
 * @method Collection selectHtmlKeywords()
 * @method Collection selectHtmlDescription()
 * @method Collection selectVisible()
 * @method Collection selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::tag;
	protected $table = 'tags';
	protected $modelType = 'tag';
}