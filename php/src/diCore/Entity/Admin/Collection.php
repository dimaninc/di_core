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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByLogin($value, $operator = null)
 * @method $this filterByPassword($value, $operator = null)
 * @method $this filterByFirstName($value, $operator = null)
 * @method $this filterByLastName($value, $operator = null)
 * @method $this filterByEmail($value, $operator = null)
 * @method $this filterByPhone($value, $operator = null)
 * @method $this filterByAddress($value, $operator = null)
 * @method $this filterByHost($value, $operator = null)
 * @method $this filterByLevel($value, $operator = null)
 * @method $this filterByActive($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByLogin($direction = null)
 * @method $this orderByPassword($direction = null)
 * @method $this orderByFirstName($direction = null)
 * @method $this orderByLastName($direction = null)
 * @method $this orderByEmail($direction = null)
 * @method $this orderByPhone($direction = null)
 * @method $this orderByAddress($direction = null)
 * @method $this orderByHost($direction = null)
 * @method $this orderByLevel($direction = null)
 * @method $this orderByActive($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByIp($direction = null)
 *
 * @method $this selectId()
 * @method $this selectLogin()
 * @method $this selectPassword()
 * @method $this selectFirstName()
 * @method $this selectLastName()
 * @method $this selectEmail()
 * @method $this selectPhone()
 * @method $this selectAddress()
 * @method $this selectHost()
 * @method $this selectLevel()
 * @method $this selectActive()
 * @method $this selectDate()
 * @method $this selectIp()
 */
class Collection extends \diCollection
{
    const type = \diTypes::admin;
    protected $table = 'admins';
    protected $modelType = 'admin';
}
