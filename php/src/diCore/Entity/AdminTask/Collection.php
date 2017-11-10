<?php
/**
 * Created by diModelsManager
 * Date: 22.06.2016
 * Time: 16:54
 */

namespace diCore\Entity\AdminTask;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByAdminId($value, $operator = null)
 * @method Collection filterByTitle($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByStatus($value, $operator = null)
 * @method Collection filterByPriority($value, $operator = null)
 * @method Collection filterByDueDate($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByAdminId($direction = null)
 * @method Collection orderByTitle($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByStatus($direction = null)
 * @method Collection orderByPriority($direction = null)
 * @method Collection orderByDueDate($direction = null)
 * @method Collection orderByDate($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectAdminId()
 * @method Collection selectTitle()
 * @method Collection selectContent()
 * @method Collection selectVisible()
 * @method Collection selectStatus()
 * @method Collection selectPriority()
 * @method Collection selectDueDate()
 * @method Collection selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::admin_task;
	protected $table = 'admin_tasks';
	protected $modelType = 'admin_task';
}