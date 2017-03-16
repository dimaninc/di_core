<?php
/**
 * Created by diModelsManager
 * Date: 04.09.2016
 * Time: 22:40
 */

/**
 * Class diTagCollection
 * Methods list for IDE
 *
 * @method diTagCollection filterById($value, $operator = null)
 * @method diTagCollection filterBySlug($value, $operator = null)
 * @method diTagCollection filterBySlugSource($value, $operator = null)
 * @method diTagCollection filterByTitle($value, $operator = null)
 * @method diTagCollection filterByContent($value, $operator = null)
 * @method diTagCollection filterByPic($value, $operator = null)
 * @method diTagCollection filterByWeight($value, $operator = null)
 * @method diTagCollection filterByHtmlTitle($value, $operator = null)
 * @method diTagCollection filterByHtmlKeywords($value, $operator = null)
 * @method diTagCollection filterByHtmlDescription($value, $operator = null)
 * @method diTagCollection filterByVisible($value, $operator = null)
 * @method diTagCollection filterByDate($value, $operator = null)
 *
 * @method diTagCollection orderById($direction = null)
 * @method diTagCollection orderBySlug($direction = null)
 * @method diTagCollection orderBySlugSource($direction = null)
 * @method diTagCollection orderByTitle($direction = null)
 * @method diTagCollection orderByContent($direction = null)
 * @method diTagCollection orderByPic($direction = null)
 * @method diTagCollection orderByWeight($direction = null)
 * @method diTagCollection orderByHtmlTitle($direction = null)
 * @method diTagCollection orderByHtmlKeywords($direction = null)
 * @method diTagCollection orderByHtmlDescription($direction = null)
 * @method diTagCollection orderByVisible($direction = null)
 * @method diTagCollection orderByDate($direction = null)
 *
 * @method diTagCollection selectId()
 * @method diTagCollection selectSlug()
 * @method diTagCollection selectSlugSource()
 * @method diTagCollection selectTitle()
 * @method diTagCollection selectContent()
 * @method diTagCollection selectPic()
 * @method diTagCollection selectWeight()
 * @method diTagCollection selectHtmlTitle()
 * @method diTagCollection selectHtmlKeywords()
 * @method diTagCollection selectHtmlDescription()
 * @method diTagCollection selectVisible()
 * @method diTagCollection selectDate()
 */
class diTagCollection extends diCollection
{
	const type = diTypes::tag;
	protected $table = "tags";
	protected $modelType = "tag";
}