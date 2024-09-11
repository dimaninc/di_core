<?php
/**
 * Created by ModelsManager
 * Date: 11.09.2024
 * Time: 15:46
 */

namespace diCore\Entity\AdditionalVariable;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTargetType($value, $operator = null)
 * @method $this filterByName($value, $operator = null)
 * @method $this filterByProperties($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByActive($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTargetType($direction = null)
 * @method $this orderByName($direction = null)
 * @method $this orderByProperties($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByActive($direction = null)
 *
 * @method $this selectId
 * @method $this selectTargetType
 * @method $this selectName
 * @method $this selectProperties
 * @method $this selectOrderNum
 * @method $this selectActive
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::additional_variable;
    const connection_name = 'default';
    protected $table = 'additional_variable';
    protected $modelType = 'additional_variable';
}
