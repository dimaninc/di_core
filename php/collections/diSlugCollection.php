<?php
/**
 * Created by diModelsManager
 * Date: 24.06.2016
 * Time: 14:40
 */

/**
 * Class diSlugCollection
 * Methods list for IDE
 *
 * @method diSlugCollection filterById($value, $operator = null)
 * @method diSlugCollection filterByTargetType($value, $operator = null)
 * @method diSlugCollection filterByTargetId($value, $operator = null)
 * @method diSlugCollection filterBySlug($value, $operator = null)
 * @method diSlugCollection filterByFullSlug($value, $operator = null)
 * @method diSlugCollection filterByLevelNum($value, $operator = null)
 *
 * @method diSlugCollection orderById($direction = null)
 * @method diSlugCollection orderByTargetType($direction = null)
 * @method diSlugCollection orderByTargetId($direction = null)
 * @method diSlugCollection orderBySlug($direction = null)
 * @method diSlugCollection orderByFullSlug($direction = null)
 * @method diSlugCollection orderByLevelNum($direction = null)
 *
 * @method diSlugCollection selectId()
 * @method diSlugCollection selectTargetType()
 * @method diSlugCollection selectTargetId()
 * @method diSlugCollection selectSlug()
 * @method diSlugCollection selectFullSlug()
 * @method diSlugCollection selectLevelNum()
 */
class diSlugCollection extends diCollection
{
	const type = diTypes::slug;
	protected $table = "slugs";
	protected $modelType = "slug";
}