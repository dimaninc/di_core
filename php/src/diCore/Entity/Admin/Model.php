<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2015
 * Time: 17:11
 */

namespace diCore\Entity\Admin;

use diCore\Admin\Page\Admins as Guide;

/**
 * Class Model
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
 * @method Model setLogin($value)
 * @method Model setPassword($value)
 * @method Model setFirstName($value)
 * @method Model setLastName($value)
 * @method Model setEmail($value)
 * @method Model setPhone($value)
 * @method Model setHost($value)
 * @method Model setLevel($value)
 * @method Model setActive($value)
 * @method Model setDate($value)
 * @method Model setIp($value)
 */
class Model extends \diBaseUserModel
{
	const type = \diTypes::admin;
	const table = 'admins';
	const slug_field_name = 'login';
	protected $table = 'admins';

	const COMPLEX_QUERY_PREFIX = 'admin_';

	protected $levels;

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
		$ar = extend(parent::getTemplateVars(), [
			'level_str' => $this->getLevelStr(),
			'name' => $this->getName(),
		]);

		return $ar;
	}

	public function getName()
	{
		return sprintf('%s %s', $this->getFirstName(), $this->getLastName());
	}

	public function getLevelStr()
	{
		if (!$this->exists())
		{
			return null;
		}

		$this->cacheLevels();

		return isset($this->levels[$this->getLevel()])
			? $this->levels[$this->getLevel()]
			: '---';
	}

	protected function cacheLevels()
	{
		if ($this->levels === null)
		{
			/** @var Guide $adminPageClassName */
			$adminPageClassName = \diLib::getClassNameFor('admins', \diLib::ADMIN_PAGE);

			if (class_exists($adminPageClassName))
			{
				$this->levels = $adminPageClassName::$levelsAr;
			}
			else
			{
				$this->levels = Guide::$levelsAr;
			}

			$this->levels = extend(Guide::$baseLevelsAr, $this->levels);
			$this->levels = Guide::translateLevels($this->levels, $this->__getLanguage());
		}

		return $this;
	}
}