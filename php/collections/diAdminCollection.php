<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 16.10.15
 * Time: 18:02
 * 
 * Methods list for IDE
 *
 * @method diAdminCollection filterById($value, $operator = null)
 * @method diAdminCollection filterByLogin($value, $operator = null)
 * @method diAdminCollection filterByPassword($value, $operator = null)
 * @method diAdminCollection filterByFirstName($value, $operator = null)
 * @method diAdminCollection filterByLastName($value, $operator = null)
 * @method diAdminCollection filterByEmail($value, $operator = null)
 * @method diAdminCollection filterByPhone($value, $operator = null)
 * @method diAdminCollection filterByDate($value, $operator = null)
 * @method diAdminCollection filterByIp($value, $operator = null)
 * @method diAdminCollection filterByHost($value, $operator = null)
 * @method diAdminCollection filterByLevel($value, $operator = null)
 * @method diAdminCollection filterByActive($value, $operator = null)
 *
 * @method diAdminCollection orderById($direction = null)
 * @method diAdminCollection orderByLogin($direction = null)
 * @method diAdminCollection orderByPassword($direction = null)
 * @method diAdminCollection orderByFirstName($direction = null)
 * @method diAdminCollection orderByLastName($direction = null)
 * @method diAdminCollection orderByEmail($direction = null)
 * @method diAdminCollection orderByPhone($direction = null)
 * @method diAdminCollection orderByDate($direction = null)
 * @method diAdminCollection orderByIp($direction = null)
 * @method diAdminCollection orderByHost($direction = null)
 * @method diAdminCollection orderByLevel($direction = null)
 * @method diAdminCollection orderByActive($direction = null)
 *
 * @method diAdminCollection selectId()
 * @method diAdminCollection selectLogin()
 * @method diAdminCollection selectPassword()
 * @method diAdminCollection selectFirstName()
 * @method diAdminCollection selectLastName()
 * @method diAdminCollection selectEmail()
 * @method diAdminCollection selectPhone()
 * @method diAdminCollection selectDate()
 * @method diAdminCollection selectIp()
 * @method diAdminCollection selectHost()
 * @method diAdminCollection selectLevel()
 * @method diAdminCollection selectActive()
 */
class diAdminCollection extends diCollection
{
	const type = diTypes::admin;
	protected $table = "admins";
	protected $modelType = "admin";
}