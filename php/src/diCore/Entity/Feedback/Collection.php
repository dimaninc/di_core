<?php
/**
 * Created by \diModelsManager
 * Date: 16.10.2017
 * Time: 12:43
 */

namespace diCore\Entity\Feedback;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByName($value, $operator = null)
 * @method $this filterByEmail($value, $operator = null)
 * @method $this filterByPhone($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByName($direction = null)
 * @method $this orderByEmail($direction = null)
 * @method $this orderByPhone($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByIp($direction = null)
 * @method $this orderByDate($direction = null)
 *
 * @method $this selectId()
 * @method $this selectUserId()
 * @method $this selectName()
 * @method $this selectEmail()
 * @method $this selectPhone()
 * @method $this selectContent()
 * @method $this selectIp()
 * @method $this selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::feedback;
	protected $table = 'feedback';
	protected $modelType = 'feedback';
}