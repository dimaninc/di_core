<?php
/**
 * Created by \diModelsManager
 * Date: 01.03.2017
 * Time: 15:34
 */

namespace diCore\Entity\Localization;

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
 * @method Model setName($value)
 * @method Model setValue($value)
 * @method Model setEnValue($value)
 *
 * @method string	localizedValue
 */
class Model extends \diModel
{
	const type = \diTypes::localization;
	protected $table = 'localization';
	protected $localizedFields = ['value'];

	public function getValueForLanguage($language)
	{
		$field = static::getLocalizedFieldName('value', $language);

		return $this->get($field);
	}
}