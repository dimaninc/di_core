<?php
/**
 * Created by ModelsManager
 * Date: 11.09.2024
 * Time: 15:47
 */

namespace diCore\Entity\AdditionalVariableValue;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTargetId($value, $operator = null)
 * @method $this filterByAdditionalVariableId($value, $operator = null)
 * @method $this filterByValue($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTargetId($direction = null)
 * @method $this orderByAdditionalVariableId($direction = null)
 * @method $this orderByValue($direction = null)
 *
 * @method $this selectId
 * @method $this selectTargetId
 * @method $this selectAdditionalVariableId
 * @method $this selectValue
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::additional_variable_value;
    const connection_name = 'default';
    protected $table = 'additional_variable_value';
    protected $modelType = 'additional_variable_value';
}
