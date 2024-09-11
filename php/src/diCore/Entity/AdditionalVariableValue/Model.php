<?php
/**
 * Created by ModelsManager
 * Date: 11.09.2024
 * Time: 15:47
 */

namespace diCore\Entity\AdditionalVariableValue;

use diCore\Database\FieldType;
use diCore\Traits\Model\AutoTimestamps;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getTargetId
 * @method integer	getAdditionalVariableId
 * @method string	getValue
 *
 * @method bool hasTargetId
 * @method bool hasAdditionalVariableId
 * @method bool hasValue
 *
 * @method $this setTargetId($value)
 * @method $this setAdditionalVariableId($value)
 * @method $this setValue($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::additional_variable_value;
    const connection_name = 'default';
    const table = 'additional_variable_value';
    protected $table = 'additional_variable_value';

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'target_id' => FieldType::int,
        'additional_variable_id' => FieldType::int,
        'value' => FieldType::string,
        'created_at' => FieldType::timestamp,
        'updated_at' => FieldType::timestamp,
    ];

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
}
