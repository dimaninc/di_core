<?php
/**
 * Created by \diModelsManager
 * Date: 16.10.15
 * Time: 18:02
 */

namespace diCore\Entity\Admin;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByLogin($value, $operator = null)
 * @method Collection filterByPassword($value, $operator = null)
 * @method Collection filterByFirstName($value, $operator = null)
 * @method Collection filterByLastName($value, $operator = null)
 * @method Collection filterByEmail($value, $operator = null)
 * @method Collection filterByPhone($value, $operator = null)
 * @method Collection filterByHost($value, $operator = null)
 * @method Collection filterByLevel($value, $operator = null)
 * @method Collection filterByActive($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByIp($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByLogin($direction = null)
 * @method Collection orderByPassword($direction = null)
 * @method Collection orderByFirstName($direction = null)
 * @method Collection orderByLastName($direction = null)
 * @method Collection orderByEmail($direction = null)
 * @method Collection orderByPhone($direction = null)
 * @method Collection orderByHost($direction = null)
 * @method Collection orderByLevel($direction = null)
 * @method Collection orderByActive($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByIp($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectLogin()
 * @method Collection selectPassword()
 * @method Collection selectFirstName()
 * @method Collection selectLastName()
 * @method Collection selectEmail()
 * @method Collection selectPhone()
 * @method Collection selectHost()
 * @method Collection selectLevel()
 * @method Collection selectActive()
 * @method Collection selectDate()
 * @method Collection selectIp()
 */
class Collection extends \diCollection
{
	const type = \diTypes::admin;
	protected $table = 'admins';
	protected $modelType = 'admin';
}