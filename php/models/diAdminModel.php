<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2015
 * Time: 17:11
 */
/**
 * Class diAdminModel
 * Methods list for IDE
 *
 * @method string	getLogin
 * @method string	getPassword
 * @method string	getFirstName
 * @method string	getLastName
 * @method string	getEmail
 * @method string	getPhone
 * @method string	getHost
 * @method string	getLevel
 * @method integer	getActive
 * @method string	getDate
 * @method integer	getIp
 *
 * @method bool hasLogin
 * @method bool hasPassword
 * @method bool hasFirstName
 * @method bool hasLastName
 * @method bool hasEmail
 * @method bool hasPhone
 * @method bool hasHost
 * @method bool hasLevel
 * @method bool hasActive
 * @method bool hasDate
 * @method bool hasIp
 *
 * @method diAdminModel setLogin($value)
 * @method diAdminModel setPassword($value)
 * @method diAdminModel setFirstName($value)
 * @method diAdminModel setLastName($value)
 * @method diAdminModel setEmail($value)
 * @method diAdminModel setPhone($value)
 * @method diAdminModel setHost($value)
 * @method diAdminModel setLevel($value)
 * @method diAdminModel setActive($value)
 * @method diAdminModel setDate($value)
 * @method diAdminModel setIp($value)
 */
class diAdminModel extends diBaseUserModel
{
	const COMPLEX_QUERY_PREFIX = 'admin_';

	const type = diTypes::admin;
	protected $table = "admins";
	protected $slugFieldName = "login";

	protected $fields = [
		'login',
		'password',
		'first_name',
		'last_name',
		'email',
		'phone',
		'host',
		'level',
		'active',
		'date',
		'ip',
	];

	public function getTemplateVars()
	{
		$ar = parent::getTemplateVars();

		$ar["level_str"] = $this->getLevelStr();
		$ar["name"] = sprintf("%s %s", $this->getFirstName(), $this->getLastName());

		return $ar;
	}

	protected function getLevelStr()
	{
		if (!$this->exists())
		{
			return null;
		}

		if (class_exists("diAdminsCustomPage"))
		{
			$levelsAr = diAdminsCustomPage::$levelsAr;
		}
		else
		{
			$levelsAr = diAdminsPage::$levelsAr;
		}

		$levelsAr = array_merge(diAdminsPage::$baseLevelsAr, $levelsAr);

		$levelsAr = diAdminsPage::translateLevels($levelsAr, $this->__getLanguage());

		return isset($levelsAr[$this->getLevel()]) ? $levelsAr[$this->getLevel()] : '---';
	}
}