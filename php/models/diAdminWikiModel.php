<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:31
 */
/**
 * Class diAdminWikiModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getContent
 * @method integer	getVisible
 * @method string	getDate
 *
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasVisible
 * @method bool hasDate
 *
 * @method diAdminWikiModel setTitle($value)
 * @method diAdminWikiModel setContent($value)
 * @method diAdminWikiModel setVisible($value)
 * @method diAdminWikiModel setDate($value)
 */
class diAdminWikiModel extends diModel
{
    const type = diTypes::admin_wiki;
    protected $table = 'admin_wiki';
}
