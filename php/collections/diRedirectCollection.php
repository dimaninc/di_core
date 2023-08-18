<?php
/**
 * Created by diModelsManager
 * Date: 15.09.2016
 * Time: 11:42
 */

/**
 * Class diRedirectCollection
 * Methods list for IDE
 *
 * @method diRedirectCollection filterById($value, $operator = null)
 * @method diRedirectCollection filterByOldUrl($value, $operator = null)
 * @method diRedirectCollection filterByNewUrl($value, $operator = null)
 * @method diRedirectCollection filterByStatus($value, $operator = null)
 * @method diRedirectCollection filterByActive($value, $operator = null)
 * @method diRedirectCollection filterByDate($value, $operator = null)
 * @method diRedirectCollection filterByStrictForQuery($value, $operator = null)
 *
 * @method diRedirectCollection orderById($direction = null)
 * @method diRedirectCollection orderByOldUrl($direction = null)
 * @method diRedirectCollection orderByNewUrl($direction = null)
 * @method diRedirectCollection orderByStatus($direction = null)
 * @method diRedirectCollection orderByActive($direction = null)
 * @method diRedirectCollection orderByDate($direction = null)
 * @method diRedirectCollection orderByStrictForQuery($direction = null)
 *
 * @method diRedirectCollection selectId()
 * @method diRedirectCollection selectOldUrl()
 * @method diRedirectCollection selectNewUrl()
 * @method diRedirectCollection selectStatus()
 * @method diRedirectCollection selectActive()
 * @method diRedirectCollection selectDate()
 * @method diRedirectCollection selectStrictForQuery()
 */
class diRedirectCollection extends diCollection
{
    const type = diTypes::redirect;
    protected $table = 'redirects';
    protected $modelType = 'redirect';
}
