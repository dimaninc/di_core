<?php
/**
 * Created by \diModelsManager
 * Date: 01.03.2017
 * Time: 15:34
 */

namespace diCore\Entity\Localization;

use diCore\Base\CMS;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByName($value, $operator = null)
 * @method $this filterByValue($value, $operator = null)
 * @method $this filterByEnValue($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByName($direction = null)
 * @method $this orderByValue($direction = null)
 * @method $this orderByEnValue($direction = null)
 *
 * @method $this selectId()
 * @method $this selectName()
 * @method $this selectValue()
 * @method $this selectEnValue()
 *
 * @method $this filterByLocalizedValue($value, $operator = null)
 *
 * @method $this orderByLocalizedValue($direction = null)
 *
 * @method $this selectLocalizedValue()
 */
class Collection extends \diCollection
{
	const type = \diTypes::localization;
	protected $table = 'localization';
	protected $modelType = 'localization';

	public static function getPossibleLanguages()
    {
        /** @var CMS $cmsClass */
        $cmsClass = CMS::getClass();

        return $cmsClass::$possibleLanguages;
    }

    public static function getDefaultLanguage()
    {
        /** @var CMS $cmsClass */
        $cmsClass = CMS::getClass();

        return $cmsClass::$defaultLanguage;
    }

    public function asArrayByLanguage()
    {
        $ar = array_fill_keys(static::getPossibleLanguages(), []);

        $this->map(function (Model $m) use (&$ar) {
            foreach (static::getPossibleLanguages() as $lang) {
                $ar[$lang][$m->getName()] = $m->get(\diModel::getLocalizedFieldName('value', $lang));
            }
        });

        return $ar;
    }
}
