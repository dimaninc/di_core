<?php
/**
 * Created by diModelsManager
 * Date: 24.06.2016
 * Time: 14:40
 */

namespace diCore\Entity\Slug;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTargetType($value, $operator = null)
 * @method $this filterByTargetId($value, $operator = null)
 * @method $this filterBySlug($value, $operator = null)
 * @method $this filterByFullSlug($value, $operator = null)
 * @method $this filterByLevelNum($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTargetType($direction = null)
 * @method $this orderByTargetId($direction = null)
 * @method $this orderBySlug($direction = null)
 * @method $this orderByFullSlug($direction = null)
 * @method $this orderByLevelNum($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTargetType()
 * @method $this selectTargetId()
 * @method $this selectSlug()
 * @method $this selectFullSlug()
 * @method $this selectLevelNum()
 */
class Collection extends \diCollection
{
    const type = \diTypes::slug;
    protected $table = 'slugs';
    protected $modelType = 'slug';
}