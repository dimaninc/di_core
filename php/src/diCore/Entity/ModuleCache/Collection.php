<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2017
 * Time: 17:22
 */

namespace diCore\Entity\ModuleCache;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByModuleId($value, $operator = null)
 * @method Collection filterByQueryString($value, $operator = null)
 * @method Collection filterByBootstrapSettings($value, $operator = null)
 * @method Collection filterByUpdateEveryMinutes($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 * @method Collection filterByUpdatedAt($value, $operator = null)
 * @method Collection filterByActive($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByModuleId($direction = null)
 * @method Collection orderByQueryString($direction = null)
 * @method Collection orderByBootstrapSettings($direction = null)
 * @method Collection orderByUpdateEveryMinutes($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 * @method Collection orderByUpdatedAt($direction = null)
 * @method Collection orderByActive($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTitle()
 * @method Collection selectModuleId()
 * @method Collection selectQueryString()
 * @method Collection selectBootstrapSettings()
 * @method Collection selectUpdateEveryMinutes()
 * @method Collection selectContent()
 * @method Collection selectCreatedAt()
 * @method Collection selectUpdatedAt()
 * @method Collection selectActive()
 */
class Collection extends \diCollection
{
	const type = \diTypes::module_cache;
	protected $table = 'module_cache';
	protected $modelType = 'module_cache';
}