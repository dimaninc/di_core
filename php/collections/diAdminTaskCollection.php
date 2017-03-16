<?php
/**
 * Created by diModelsManager
 * Date: 22.06.2016
 * Time: 16:54
 */

/**
 * Class diAdminTaskCollection
 * Methods list for IDE
 *
 * @method diAdminTaskCollection filterById($value, $operator = null)
 * @method diAdminTaskCollection filterByAdminId($value, $operator = null)
 * @method diAdminTaskCollection filterByTitle($value, $operator = null)
 * @method diAdminTaskCollection filterByContent($value, $operator = null)
 * @method diAdminTaskCollection filterByVisible($value, $operator = null)
 * @method diAdminTaskCollection filterByStatus($value, $operator = null)
 * @method diAdminTaskCollection filterByPriority($value, $operator = null)
 * @method diAdminTaskCollection filterByDueDate($value, $operator = null)
 * @method diAdminTaskCollection filterByDate($value, $operator = null)
 *
 * @method diAdminTaskCollection orderById($direction = null)
 * @method diAdminTaskCollection orderByAdminId($direction = null)
 * @method diAdminTaskCollection orderByTitle($direction = null)
 * @method diAdminTaskCollection orderByContent($direction = null)
 * @method diAdminTaskCollection orderByVisible($direction = null)
 * @method diAdminTaskCollection orderByStatus($direction = null)
 * @method diAdminTaskCollection orderByPriority($direction = null)
 * @method diAdminTaskCollection orderByDueDate($direction = null)
 * @method diAdminTaskCollection orderByDate($direction = null)
 *
 * @method diAdminTaskCollection selectId()
 * @method diAdminTaskCollection selectAdminId()
 * @method diAdminTaskCollection selectTitle()
 * @method diAdminTaskCollection selectContent()
 * @method diAdminTaskCollection selectVisible()
 * @method diAdminTaskCollection selectStatus()
 * @method diAdminTaskCollection selectPriority()
 * @method diAdminTaskCollection selectDueDate()
 * @method diAdminTaskCollection selectDate()
 */
class diAdminTaskCollection extends diCollection
{
	const type = diTypes::admin_task;
	protected $table = "admin_tasks";
	protected $modelType = "admin_task";
}