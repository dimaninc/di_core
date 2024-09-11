<?php
/**
 * Created by ModelsManager
 * Date: 11.09.2024
 * Time: 15:46
 */

namespace diCore\Entity\AdditionalVariable;

use diCore\Database\FieldType;
use diCore\Traits\Model\AutoTimestamps;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getTargetType
 * @method string	getName
 * @method array	getProperties
 * @method integer	getOrderNum
 * @method integer	getActive
 *
 * @method bool hasTargetType
 * @method bool hasName
 * @method bool hasProperties
 * @method bool hasOrderNum
 * @method bool hasActive
 *
 * @method $this setTargetType($value)
 * @method $this setName($value)
 * @method $this setProperties($value)
 * @method $this setOrderNum($value)
 * @method $this setActive($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::additional_variable;
    const connection_name = 'default';
    const table = 'additional_variable';
    protected $table = 'additional_variable';

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'target_type' => FieldType::int,
        'name' => FieldType::string,
        'properties' => FieldType::json,
        'order_num' => FieldType::int,
        'active' => FieldType::int,
        'created_at' => FieldType::timestamp,
        'updated_at' => FieldType::timestamp,
    ];

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }

    public function getDefaultValue()
    {
        return $this->getJsonData('properties', 'defaultValue');
    }
}
