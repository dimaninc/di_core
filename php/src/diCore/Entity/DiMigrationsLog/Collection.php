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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByAdminId($value, $operator = null)
 * @method $this filterByIdx($value, $operator = null)
 * @method $this filterByName($value, $operator = null)
 * @method $this filterByDirection($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByAdminId($direction = null)
 * @method $this orderByIdx($direction = null)
 * @method $this orderByName($direction = null)
 * @method $this orderByDirection($direction = null)
 * @method $this orderByDate($direction = null)
 *
 * @method $this selectId()
 * @method $this selectAdminId()
 * @method $this selectIdx()
 * @method $this selectName()
 * @method $this selectDirection()
 * @method $this selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::di_migrations_log;
	protected $table = 'di_migrations_log';
	protected $modelType = 'di_migrations_log';
}