<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2017
 * Time: 17:22
 */

namespace diCore\Entity\ModuleCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getModuleId
 * @method string	getQueryString
 * @method string	getBootstrapSettings
 * @method integer	getUpdateEveryMinutes
 * @method string	getContent
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
 * @method integer	getActive
 *
 * @method bool hasTitle
 * @method bool hasModuleId
 * @method bool hasQueryString
 * @method bool hasBootstrapSettings
 * @method bool hasUpdateEveryMinutes
 * @method bool hasContent
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 * @method bool hasActive
 *
 * @method Model setTitle($value)
 * @method Model setModuleId($value)
 * @method Model setQueryString($value)
 * @method Model setBootstrapSettings($value)
 * @method Model setUpdateEveryMinutes($value)
 * @method Model setContent($value)
 * @method Model setCreatedAt($value)
 * @method Model setUpdatedAt($value)
 * @method Model setActive($value)
 */
class Model extends \diModel
{
    const type = \diTypes::module_cache;
    protected $table = 'module_cache';
}
