<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2017
 * Time: 17:22
 */

namespace diCore\Entity\ModuleCache;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByModuleId($value, $operator = null)
 * @method $this filterByQueryString($value, $operator = null)
 * @method $this filterByBootstrapSettings($value, $operator = null)
 * @method $this filterByUpdateEveryMinutes($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByActive($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByModuleId($direction = null)
 * @method $this orderByQueryString($direction = null)
 * @method $this orderByBootstrapSettings($direction = null)
 * @method $this orderByUpdateEveryMinutes($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByActive($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTitle()
 * @method $this selectModuleId()
 * @method $this selectQueryString()
 * @method $this selectBootstrapSettings()
 * @method $this selectUpdateEveryMinutes()
 * @method $this selectContent()
 * @method $this selectActive()
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::module_cache;
    protected $table = 'module_cache';
    protected $modelType = 'module_cache';
}
