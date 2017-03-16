<?php
/**
 * Created by \diModelsManager
 * Date: 01.03.2017
 * Time: 15:34
 */

namespace diCore\Entity\Localization;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByName($value, $operator = null)
 * @method Collection filterByValue($value, $operator = null)
 * @method Collection filterByEnValue($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByName($direction = null)
 * @method Collection orderByValue($direction = null)
 * @method Collection orderByEnValue($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectName()
 * @method Collection selectValue()
 * @method Collection selectEnValue()
 *
 * @method Collection filterByLocalizedValue($value, $operator = null)
 *
 * @method Collection orderByLocalizedValue($direction = null)
 *
 * @method Collection selectLocalizedValue()
 */
class Collection extends \diCollection
{
	const type = \diTypes::localization;
	protected $table = 'localization';
	protected $modelType = 'localization';
}