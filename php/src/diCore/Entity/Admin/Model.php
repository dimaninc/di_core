<?php
/**
 * Created by \diModelsManager
 * Date: 08.06.2015
 * Time: 17:11
 */

namespace diCore\Entity\Admin;

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

    protected static $levels;

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
        'ip' => FieldType::ip_int,
        'host' => FieldType::string,
        'level' => FieldType::string,
        'active' => FieldType::int,
    ];

    public function getTemplateVars()
    {
        $ar = extend(parent::getTemplateVars(), [
            'level_str' => $this->getLevelTitle(),
            'level_title' => $this->getLevelTitle(),
            'name' => $this->getName(),
        ]);

        return $ar;
    }

    public function isRoot()
    {
        return $this->getLevel() === Level::root || Config::isInitiating();
    }

    public function getName()
    {
        return join(' ', array_filter($this->getNameElements()));
    }

    public function getNameElements()
    {
        return [$this->getFirstName(), $this->getLastName()];
    }

    public function getLevelTitle()
    {
        if (!$this->exists()) {
            return null;
        }

        static::cacheLevels();

        return static::$levels[$this->getLevel()] ?? '---';
    }

    public static function getLevels()
    {
        static::cacheLevels();

        return static::$levels ?? [];
    }

    public static function translateLevels($levels = [], $language = null)
    {
        foreach ($levels as $name => &$title) {
            if (is_array($title)) {
                $title = $title[$language];
            }
        }

        return $levels;
    }

    protected static function cacheLevels()
    {
        if (static::$levels === null) {
            /** @var Level $levelClassName */
            $levelClassName = \diLib::getChildClass(Level::class);

            if (class_exists($levelClassName)) {
                static::$levels = static::translateLevels(
                    $levelClassName::titles(),
                    static::__getLanguage()
                );
            }
        }
    }
}
