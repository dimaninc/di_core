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
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByUserId($value, $operator = null)
 * @method Collection filterByName($value, $operator = null)
 * @method Collection filterByEmail($value, $operator = null)
 * @method Collection filterByPhone($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByIp($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByUserId($direction = null)
 * @method Collection orderByName($direction = null)
 * @method Collection orderByEmail($direction = null)
 * @method Collection orderByPhone($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByIp($direction = null)
 * @method Collection orderByDate($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectUserId()
 * @method Collection selectName()
 * @method Collection selectEmail()
 * @method Collection selectPhone()
 * @method Collection selectContent()
 * @method Collection selectIp()
 * @method Collection selectDate()
 */
class Collection extends \diCollection
{
	const type = \diTypes::feedback;
	protected $table = 'feedback';
	protected $modelType = 'feedback';
}