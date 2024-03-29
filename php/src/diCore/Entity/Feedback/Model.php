<?php
/**
 * Created by diModelsManager
 * Date: 01.10.2015
 * Time: 00:20
 */

namespace diCore\Entity\Feedback;

use diCore\Database\FieldType;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserId
 * @method string	getName
 * @method string	getEmail
 * @method string	getPhone
 * @method string	getContent
 * @method integer	getIp
 * @method string	getDate
 *
 * @method bool hasUserId
 * @method bool hasName
 * @method bool hasEmail
 * @method bool hasPhone
 * @method bool hasContent
 * @method bool hasIp
 * @method bool hasDate
 *
 * @method $this setUserId($value)
 * @method $this setName($value)
 * @method $this setEmail($value)
 * @method $this setPhone($value)
 * @method $this setContent($value)
 * @method $this setIp($value)
 * @method $this setDate($value)
 */
class Model extends \diModel
{
    const type = \diTypes::feedback;
    const table = 'feedback';
    protected $table = 'feedback';

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'user_id' => FieldType::int,
        'name' => FieldType::string,
        'email' => FieldType::string,
        'phone' => FieldType::string,
        'content' => FieldType::string,
        'ip' => FieldType::ip_int,
        'date' => FieldType::timestamp,
    ];

    public function validate()
    {
        if (!$this->hasContent()) {
            $this->addValidationError('Content required', 'content');
        }

        return parent::validate();
    }
}
