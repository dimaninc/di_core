<?php
/**
 * Created by diModelsManager
 * Date: 15.09.2016
 * Time: 11:42
 */

namespace diCore\Entity\Redirect;

use diCore\Data\Types;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getOldUrl
 * @method string	getNewUrl
 * @method integer	getStatus
 * @method integer	getActive
 * @method string	getDate
 * @method integer  getStrictForQuery
 *
 * @method bool hasOldUrl
 * @method bool hasNewUrl
 * @method bool hasStatus
 * @method bool hasActive
 * @method bool hasDate
 * @method bool hasStrictForQuery
 *
 * @method $this setOldUrl($value)
 * @method $this setNewUrl($value)
 * @method $this setStatus($value)
 * @method $this setActive($value)
 * @method $this setDate($value)
 * @method $this setStrictForQuery($value)
 */
class Model extends \diModel
{
    const type = Types::redirect;
    const table = 'redirects';
    protected $table = 'redirects';
}
