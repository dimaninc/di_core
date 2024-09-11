<?php
/**
 * Created by diModelsManager
 * Date: 15.09.2016
 * Time: 11:42
 */

namespace diCore\Entity\Redirect;

use diCore\Data\Types;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByOldUrl($value, $operator = null)
 * @method $this filterByNewUrl($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 * @method $this filterByActive($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByStrictForQuery($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByOldUrl($direction = null)
 * @method $this orderByNewUrl($direction = null)
 * @method $this orderByStatus($direction = null)
 * @method $this orderByActive($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByStrictForQuery($direction = null)
 *
 * @method $this selectId()
 * @method $this selectOldUrl()
 * @method $this selectNewUrl()
 * @method $this selectStatus()
 * @method $this selectActive()
 * @method $this selectDate()
 * @method $this selectStrictForQuery()
 */
class Collection extends \diCollection
{
    const type = Types::redirect;
    protected $table = 'redirects';
    protected $modelType = 'redirect';
}
