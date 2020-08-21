<?php
/**
 * Created by diModelsManager
 * Date: 02.06.2016
 * Time: 17:58
 */

namespace diCore\Entity\AdminTableEditLog;

/**
 * Class diAdminTableEditLogCollection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTargetTable($value, $operator = null)
 * @method $this filterByTargetId($value, $operator = null)
 * @method $this filterByAdminId($value, $operator = null)
 * @method $this filterByOldData($value, $operator = null)
 * @method $this filterByNewData($value, $operator = null)
 * @method $this filterByCreatedAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTargetTable($direction = null)
 * @method $this orderByTargetId($direction = null)
 * @method $this orderByAdminId($direction = null)
 * @method $this orderByOldData($direction = null)
 * @method $this orderByNewData($direction = null)
 * @method $this orderByCreatedAt($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTargetTable()
 * @method $this selectTargetId()
 * @method $this selectAdminId()
 * @method $this selectOldData()
 * @method $this selectNewData()
 * @method $this selectCreatedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::admin_table_edit_log;
	protected $table = 'admin_table_edit_log';
	protected $modelType = 'admin_table_edit_log';
}