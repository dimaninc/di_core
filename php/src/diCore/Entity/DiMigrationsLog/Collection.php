<?php
/**
 * Created by \diModelsManager
 * Date: 30.01.2018
 * Time: 17:27
 */

namespace diCore\Entity\DiMigrationsLog;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByAdminId($value, $operator = null)
 * @method Collection filterByIdx($value, $operator = null)
 * @method Collection filterByName($value, $operator = null)
 * @method Collection filterByDirection($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByAdminId($direction = null)
 * @method Collection orderByIdx($direction = null)
 * @method Collection orderByName($direction = null)
 * @method Collection orderByDirection($direction = null)
 * @method Collection orderByDate($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectAdminId()
 * @method Collection selectIdx()
 * @method Collection selectName()
 * @method Collection selectDirection()
 * @method Collection selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::di_migrations_log;
	protected $table = 'di_migrations_log';
	protected $modelType = 'di_migrations_log';
}