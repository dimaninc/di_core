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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByAdminId($value, $operator = null)
 * @method $this filterByTitle($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 * @method $this filterByPriority($value, $operator = null)
 * @method $this filterByDueDate($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByAdminId($direction = null)
 * @method $this orderByTitle($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByStatus($direction = null)
 * @method $this orderByPriority($direction = null)
 * @method $this orderByDueDate($direction = null)
 * @method $this orderByDate($direction = null)
 *
 * @method $this selectId()
 * @method $this selectAdminId()
 * @method $this selectTitle()
 * @method $this selectContent()
 * @method $this selectVisible()
 * @method $this selectStatus()
 * @method $this selectPriority()
 * @method $this selectDueDate()
 * @method $this selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::admin_task;
	protected $table = 'admin_tasks';
	protected $modelType = 'admin_task';
}
