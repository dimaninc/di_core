<?php
/**
 * Created by diModelsManager
 * Date: 02.06.2016
 * Time: 17:58
 */

/**
 * Class diAdminTableEditLogCollection
 * Methods list for IDE
 *
 * @method diAdminTableEditLogCollection filterById($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByTargetTable($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByTargetId($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByAdminId($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByOldData($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByNewData($value, $operator = null)
 * @method diAdminTableEditLogCollection filterByCreatedAt($value, $operator = null)
 *
 * @method diAdminTableEditLogCollection orderById($direction = null)
 * @method diAdminTableEditLogCollection orderByTargetTable($direction = null)
 * @method diAdminTableEditLogCollection orderByTargetId($direction = null)
 * @method diAdminTableEditLogCollection orderByAdminId($direction = null)
 * @method diAdminTableEditLogCollection orderByOldData($direction = null)
 * @method diAdminTableEditLogCollection orderByNewData($direction = null)
 * @method diAdminTableEditLogCollection orderByCreatedAt($direction = null)
 *
 * @method diAdminTableEditLogCollection selectId()
 * @method diAdminTableEditLogCollection selectTargetTable()
 * @method diAdminTableEditLogCollection selectTargetId()
 * @method diAdminTableEditLogCollection selectAdminId()
 * @method diAdminTableEditLogCollection selectOldData()
 * @method diAdminTableEditLogCollection selectNewData()
 * @method diAdminTableEditLogCollection selectCreatedAt()
 */
class diAdminTableEditLogCollection extends diCollection
{
	const type = diTypes::admin_table_edit_log;
	protected $table = "admin_table_edit_log";
	protected $modelType = "admin_table_edit_log";
}