<?php
/**
 * Created by diModelsManager
 * Date: 15.09.2016
 * Time: 11:42
 */

/**
 * Class diRedirectModel
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
 * @method diRedirectModel setOldUrl($value)
 * @method diRedirectModel setNewUrl($value)
 * @method diRedirectModel setStatus($value)
 * @method diRedirectModel setActive($value)
 * @method diRedirectModel setDate($value)
 * @method diRedirectModel setStrictForQuery($value)
 */
class diRedirectModel extends diModel
{
    const type = diTypes::redirect;
    protected $table = 'redirects';
}
