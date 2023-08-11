<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2015
 * Time: 17:11
 */

namespace diCore\Entity\Admin;

use diCore\Admin\Page\Admins as Guide;
use diCore\Data\Config;
use diCore\Database\FieldType;

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
 * @method string	getAddress
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
 * @method bool hasAddress
 * @method bool hasHost
 * @method bool hasLevel
 * @method bool hasActive
 * @method bool hasDate
 * @method bool hasIp
 *
 * @method $this setLogin($value)
 * @method $this setPassword($value)
 * @method $this setFirstName($value)
 * @method $this setLastName($value)
 * @method $this setEmail($value)
 * @method $this setPhone($value)
 * @method $this setAddress($value)
 * @method $this setHost($value)
 * @method $this setLevel($value)
 * @method $this setActive($value)
 * @method $this setDate($value)
 * @method $this setIp($value)
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

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'login' => FieldType::string,
        'password' => FieldType::string,
        'first_name' => FieldType::string,
        'last_name' => FieldType::string,
        'email' => FieldType::string,
        'phone' => FieldType::string,
        'address' => FieldType::string,
        'date' => FieldType::string,
        'ip' => FieldType::int,
        'host' => FieldType::string,
        'level' => FieldType::string,
        'active' => FieldType::string,
    ];

    public function getTemplateVars()
    {
        $ar = extend(parent::getTemplateVars(), [
            'level_str' => $this->getLevelStr(),
            'name' => $this->getName(),
        ]);

        return $ar;
    }

    public function isRoot()
    {
        return $this->getLevel() === 'root' || Config::isInitiating();
    }

    public function getName()
    {
        return join(' ', array_filter($this->getNameElements()));
    }

    public function getNameElements()
    {
        return [$this->getFirstName(), $this->getLastName()];
    }

    public function getLevelStr()
    {
        if (!$this->exists()) {
            return null;
        }

        $this->cacheLevels();

        return isset($this->levels[$this->getLevel()])
            ? $this->levels[$this->getLevel()]
            : '---';
    }

    protected function cacheLevels()
    {
        if ($this->levels === null) {
            /** @var Guide $adminPageClassName */
            $adminPageClassName = \diLib::getClassNameFor(
                'admins',
                \diLib::ADMIN_PAGE
            );

            if (class_exists($adminPageClassName)) {
                $this->levels = $adminPageClassName::$levelsAr;
            } else {
                $this->levels = Guide::$levelsAr;
            }

            $this->levels = extend(Guide::$baseLevelsAr, $this->levels);
            $this->levels = Guide::translateLevels(
                $this->levels,
                $this->__getLanguage()
            );
        }

        return $this;
    }
}
