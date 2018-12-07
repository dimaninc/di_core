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
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTargetTable($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByAdminId($value, $operator = null)
 * @method Collection filterByOldData($value, $operator = null)
 * @method Collection filterByNewData($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTargetTable($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByAdminId($direction = null)
 * @method Collection orderByOldData($direction = null)
 * @method Collection orderByNewData($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTargetTable()
 * @method Collection selectTargetId()
 * @method Collection selectAdminId()
 * @method Collection selectOldData()
 * @method Collection selectNewData()
 * @method Collection selectCreatedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::admin_table_edit_log;
	protected $table = 'admin_table_edit_log';
	protected $modelType = 'admin_table_edit_log';
}