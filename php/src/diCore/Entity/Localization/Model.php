<?php
/**
 * Created by \diModelsManager
 * Date: 01.03.2017
 * Time: 15:34
 */

namespace diCore\Entity\Localization;

use diCore\Database\FieldType;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getName
 * @method string	getValue
 * @method string	getEnValue
 *
 * @method bool hasName
 * @method bool hasValue
 * @method bool hasEnValue
 *
 * @method $this setName($value)
 * @method $this setValue($value)
 * @method $this setEnValue($value)
 *
 * @method string	localizedValue
 */
class Model extends \diModel
{
    const type = \diTypes::localization;
    protected $table = 'localization';
    protected $localizedFields = ['value'];

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'name' => FieldType::string,
        'value' => FieldType::string,
        'en_value' => FieldType::string,
    ];

    protected static $publicFields = ['name', 'value', 'en_value'];

    public function getValueForLanguage($language)
    {
        $field = static::getLocalizedFieldName('value', $language);

        return $this->get($field);
    }
}
