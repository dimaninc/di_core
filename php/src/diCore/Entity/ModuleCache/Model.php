<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2017
 * Time: 17:22
 */

namespace diCore\Entity\ModuleCache;

use diCore\Traits\Model\AutoTimestamps;

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
 * @method integer	getActive
 *
 * @method bool hasTitle
 * @method bool hasModuleId
 * @method bool hasQueryString
 * @method bool hasBootstrapSettings
 * @method bool hasUpdateEveryMinutes
 * @method bool hasContent
 * @method bool hasActive
 *
 * @method $this setTitle($value)
 * @method $this setModuleId($value)
 * @method $this setQueryString($value)
 * @method $this setBootstrapSettings($value)
 * @method $this setUpdateEveryMinutes($value)
 * @method $this setContent($value)
 * @method $this setActive($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::module_cache;
    protected $table = 'module_cache';

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
}
