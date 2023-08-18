<?php
/**
 * Created by diModelsManager
 * Date: 13.10.2016
 * Time: 11:40
 */

/**
 * Class diAdminWikiCollection
 * Methods list for IDE
 *
 * @method diAdminWikiCollection filterById($value, $operator = null)
 * @method diAdminWikiCollection filterByTitle($value, $operator = null)
 * @method diAdminWikiCollection filterByContent($value, $operator = null)
 * @method diAdminWikiCollection filterByVisible($value, $operator = null)
 * @method diAdminWikiCollection filterByDate($value, $operator = null)
 *
 * @method diAdminWikiCollection orderById($direction = null)
 * @method diAdminWikiCollection orderByTitle($direction = null)
 * @method diAdminWikiCollection orderByContent($direction = null)
 * @method diAdminWikiCollection orderByVisible($direction = null)
 * @method diAdminWikiCollection orderByDate($direction = null)
 *
 * @method diAdminWikiCollection selectId()
 * @method diAdminWikiCollection selectTitle()
 * @method diAdminWikiCollection selectContent()
 * @method diAdminWikiCollection selectVisible()
 * @method diAdminWikiCollection selectDate()
 */
class diAdminWikiCollection extends diCollection
{
    const type = diTypes::admin_wiki;
    protected $table = 'admin_wiki';
    protected $modelType = 'admin_wiki';
}
