<?php
/*
	// dimaninc

	// 2015/05/01
		* total renovation

	// 2011/10/19
		* mysql table creation updated
		* prefix and suffix code feature added for .inc file

	// 2010/05/25
		* mysql table creation fixed
		* .inc file creation fixed

	// 2006/11/06
		* float and double types added

	// 2006/09/05
		* get() and set() methods improved, $_type added

	// 2006/01/14
		* renamed from diValueStore into diConfiguration
		* auto table creating added
*/

namespace diCore\Data;

use diCore\Admin\BasePage;
use diCore\Admin\Submit;
use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;

class Configuration
{
    private $tableName = 'configuration'; // table name of values to get stored in
    private $nameField = 'name';
    private $valueField = 'value';

    private $cacheFilename = '_cfg/cache/configuration.php';
    private $fileChmod = 0666;
    private $dirChmod = 0777;
    private static $folder;

    private $tabsAr = [];
    private $otherTabName = '_other';
    private $otherTabTitle = [
        'en' => 'Other',
        'ru' => 'Прочее',
    ];

    /** @var BasePage */
    private $adminPage = null;
    private $defaultLanguage = 'ru';

    public static $data = [];
    public static $cacheLoaded = false;

    private static $instance;

    public static $inputNameReplaces = [
        '.' => '---DOT---',
    ];

    public function __construct()
    {
        self::$folder = getSettingsFolder();
        self::$instance = $this;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            new static();
        }

        return self::$instance;
    }

    public function setInitialData($ar)
    {
        self::$data = $ar;

        return $this;
    }

    public function setTabsAr($ar)
    {
        $this->tabsAr = $ar;

        $this->checkOtherTabInList();

        return $this;
    }

    public function checkOtherTabInList($force = false)
    {
        if ($force || !isset($this->tabsAr[$this->getOtherTabName()])) {
            $this->tabsAr[$this->getOtherTabName()] = $this->getOtherTabTitle();
        }

        return $this;
    }

    public function getTabsAr()
    {
        return $this->tabsAr;
    }

    public function getOtherTabName()
    {
        return $this->otherTabName;
    }

    public function getOtherTabTitle()
    {
        return $this->otherTabTitle[$this->getLanguage()];
    }

    /**
     * @return BasePage
     */
    public function getAdminPage()
    {
        return $this->adminPage;
    }

    /**
     * @param BasePage $adminPage
     * @return $this
     */
    public function setAdminPage(BasePage $adminPage)
    {
        $this->adminPage = $adminPage;

        return $this;
    }

    protected function getLanguage()
    {
        if ($this->getAdminPage()) {
            return $this->getAdminPage()->getLanguage();
        }

        return $this->defaultLanguage;
    }

    public static function getFolder()
    {
        return self::$folder;
    }

    protected function getFullCacheFilename()
    {
        $folder = Config::getConfigurationFolder();

        return $folder . $this->cacheFilename;
    }

    public function loadCache()
    {
        @include $this->getFullCacheFilename();

        if (!self::$cacheLoaded) {
            $this->loadAllFromDB()->updateCache();

            include $this->getFullCacheFilename();
        }

        return $this;
    }

    /**
     * @param string|array $name
     * @param string $property can be 'value' or 'width/height/type' (for images)
     * @return mixed|null
     * @throws \Exception
     */
    public static function get($name, $property = 'value')
    {
        if ($name2 = self::exists($name)) {
            return self::getPropertyOption($name2, $property);
        }

        self::throwException($name);
    }

    /**
     * @param string|array $name
     * @param null|mixed $default
     * @param string $property   can be 'value' or 'width/height/type' (for images)
     * @return null
     */
    public static function safeGet($name, $default = null, $property = 'value')
    {
        if ($name = self::exists($name)) {
            return self::getPropertyOption($name, $property);
        }

        return $default;
    }

    public static function getArray($pattern = null)
    {
        $ar = [];

        foreach (self::$data as $key => $value) {
            if ($pattern !== null && !preg_match($pattern, $key)) {
                continue;
            }

            $ar[$key] = self::get($key);
        }

        return $ar;
    }

    public static function getTemplateArray($pattern = null)
    {
        return array_change_key_case(self::getArray($pattern), CASE_LOWER);
    }

    /**
     * @param  string|array $name
     * @return bool|string
     */
    public static function exists($name)
    {
        if (is_array($name)) {
            foreach ($name as $n) {
                if (static::exists($n)) {
                    return $n;
                }
            }

            return false;
        }

        return isset(self::$data[$name]) ? $name : null;
    }

    /**
     * @param string $dimension width/height
     * @param string $table table name
     * @param string $field field name
     * @param int $imageType Submit::IMAGE_TYPE_MAIN, etc.
     * @return bool|string|null
     */
    public static function getDimensionParam($dimension, $table, $field, $imageType)
    {
        $suffix = Submit::getPreviewSuffix($imageType);

        return Configuration::exists([
            $table . '_' . $field . $suffix . '_' . $dimension,
            $table . $suffix . '_' . $dimension,
            $table . '_' . $dimension,
        ]);
    }

    public static function getFilename($name)
    {
        $fn = static::get($name);

        return $fn ? getSettingsFolder() . $fn : null;
    }

    private function getDB()
    {
        return Connection::get()->getDb();
    }

    public function setTableName($table)
    {
        $this->tableName = $table;

        return $this;
    }

    public function setNameField($field)
    {
        $this->nameField = $field;

        return $this;
    }

    public function setValueField($field)
    {
        $this->valueField = $field;

        return $this;
    }

    public function setCacheFilename($fn)
    {
        $this->cacheFilename = $fn;

        return $this;
    }

    public function loadAllFromDB()
    {
        $rs = $this->getDB()->rs($this->tableName);
        while ($rs && ($r = $this->getDB()->fetch_array($rs))) {
            if (!self::exists($r[$this->nameField])) {
                continue;
            }

            self::$data[$r[$this->nameField]][
                $this->valueField
            ] = $this->adjustAfterDB(
                $r[$this->valueField],
                self::getPropertyType($r[$this->nameField])
            );
        }

        return $this;
    }

    public static function getPropertyOption($name, $option)
    {
        return self::$data[$name][$option] ?? null;
    }

    public static function getPropertyType($name)
    {
        return self::getPropertyOption($name, 'type');
    }

    public static function throwException($name)
    {
        $d = debug_backtrace();
        $info = isset($d[0]) ? "{$d[0]['file']}:{$d[0]['line']}" : 'no debug info';

        throw new \Exception(
            "There's no variable '$name' in diConfiguration::\$data ($info)"
        );
    }

    public function getFromDB($name)
    {
        if (!self::exists($name)) {
            self::throwException($name);
        }

        $r = $this->getDB()->ar(
            $this->tableName,
            "WHERE $this->nameField = '$name'"
        );

        return $this->adjustAfterDB(
            $r ? $r[$this->valueField] : self::$data[$name]['value'],
            self::getPropertyType($name)
        );
    }

    public function setToDB($name, $value)
    {
        $this->createTable();

        $this->getDB()->insert_or_update(
            $this->tableName,
            [
                $this->nameField => $this->getDB()->escape_string($name),
                $this->valueField => $this->adjustBeforeDB(
                    $value,
                    self::getPropertyType($name)
                ),
            ],
            $this->nameField
        );

        $this->getDB()->dierror();

        return $this;
    }

    public function store()
    {
        $checkboxesAr = [];

        foreach ($_POST as $k => $v) {
            $k = str_replace(
                array_values(static::$inputNameReplaces),
                array_keys(static::$inputNameReplaces),
                $k
            );

            if (is_array($v)) {
                foreach ($v as $_k => $_v) {
                    $full_k = $k . '[' . $_k . ']';

                    if (self::exists($full_k)) {
                        $this->setToDB($full_k, $_v);
                    }

                    if (self::getPropertyType($full_k) == 'checkbox') {
                        $checkboxesAr[] = $full_k;
                    }
                }

                continue;
            }

            if (self::exists($k)) {
                $this->setToDB($k, $v);

                if (self::getPropertyType($k) == 'checkbox') {
                    $checkboxesAr[] = $k;
                }
            }
        }

        $folder = \diPaths::fileSystem() . self::getFolder();
        FileSystemHelper::createTree(
            \diPaths::fileSystem(),
            self::getFolder(),
            $this->dirChmod
        );

        foreach ($_FILES as $k => $v) {
            if (
                !self::exists($k) ||
                !in_array(self::getPropertyType($k), ['pic', 'file']) ||
                !empty($_FILES[$k]['error'])
            ) {
                continue;
            }

            do {
                $pic = Submit::getGeneratedFilename(
                    $folder,
                    $_FILES[$k]['name'],
                    self::getPropertyOption($k, 'naming') ?:
                    Submit::FILE_NAMING_RANDOM
                );
                $fullPic = $folder . $pic;
            } while (is_file($fullPic));

            if (!move_uploaded_file($_FILES[$k]['tmp_name'], $fullPic)) {
                throw new \Exception(
                    "Unable to copy file {$_FILES[$k]['name']} to $fullPic"
                );
            }

            // removing old file after storing new one to renew filename
            if (self::get($k) && is_file($folder . self::get($k))) {
                unlink($folder . self::get($k));
            }

            $this->setToDB($k, $pic);
        }

        foreach (self::$data as $_k => $_v) {
            if (!isset($_v['type'])) {
                continue;
            }

            if ($_v['type'] == 'checkbox') {
                if (!in_array($_k, $checkboxesAr)) {
                    self::$data[$_k]['value'] = 0;

                    $this->setToDB($_k, 0);
                }
            }
        }

        $this->updateCache();

        return $this;
    }

    public static function hasFlag($name, $flag)
    {
        if (!self::exists($name)) {
            return false;
        }

        $flags = self::getPropertyOption($name, 'flags');

        if (!is_array($flags)) {
            $flags = [$flags];
        }

        return in_array($flag, $flags);
    }

    public static function getData()
    {
        return self::$data;
    }

    private function getCreateTableSql()
    {
        $charset = Config::getDbEncoding();
        $collation = Config::getDbCollation();

        switch (Connection::get()::getEngine()) {
            case Engine::SQLITE:
                return [
                    "CREATE TABLE IF NOT EXISTS `$this->tableName`(
                        id integer not null primary key autoincrement,
                        $this->nameField varchar(255),
                        $this->valueField text
                    );",
                    "CREATE INDEX IF NOT EXISTS `{$this->tableName}_idx` ON `$this->tableName` ($this->nameField);",
                ];

            case Engine::POSTGRESQL:
                return [
                    "CREATE TABLE IF NOT EXISTS \"$this->tableName\"(
                        \"id\" SERIAL PRIMARY KEY,
                        \"$this->nameField\" varchar(255),
                        \"$this->valueField\" text,
                        UNIQUE(\"$this->nameField\")
		            );",
                ];

            case Engine::MYSQL:
                return [
                    "CREATE TABLE IF NOT EXISTS `$this->tableName`(
                        `id` int not null auto_increment,
                        `$this->nameField` varchar(255),
                        `$this->valueField` text,
                        unique key `idx`(`{$this->nameField}`),
                        primary key(`id`)
		            ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation;",
                ];

            default:
                throw new \Exception('Database engine not supported for config');
        }
    }

    private function createTable()
    {
        foreach ($this->getCreateTableSql() as $sql) {
            $res = $this->getDb()->q($sql);
        }

        if (!$res) {
            throw new \Exception(
                'Unable to init configuration table: ' . $this->getDb()->getLogStr()
            );
        }

        return $this;
    }

    public function updateCache($options = [])
    {
        $options = extend(
            [
                'prefixCode' => '',
                'suffixCode' => '',
            ],
            $options
        );

        $cache_file = '';
        $cache_file .= $this->phpHeader();

        if ($options['prefixCode']) {
            $cache_file .= $options['prefixCode'];
        }

        $rs = $this->getDB()->rs($this->tableName);
        while ($rs && ($r = $this->getDB()->fetch($rs))) {
            $name = $r->{$this->nameField};

            if (!self::exists($name)) {
                continue;
            }

            $type = self::getPropertyType($name);
            $s = $this->adjustBeforeDB($r->{$this->valueField}, $type, true);

            if (
                !in_array($type, ['int', 'integer', 'float', 'double', 'checkbox'])
            ) {
                $s = "\"$s\"";
            }

            $cache_file .= "self::\$data[\"$name\"][\"value\"] = $s;\n";

            if ($type == 'pic') {
                $ff =
                    \diPaths::fileSystem() .
                    self::getFolder() .
                    $r->{$this->valueField};
                list($w, $h, $t) = is_file($ff) ? getimagesize($ff) : [0, 0, 0];

                if ($w && $h) {
                    $cache_file .= "self::\$data[\"{$name}\"][\"img_width\"] = $w;\n";
                    $cache_file .= "self::\$data[\"{$name}\"][\"img_height\"] = $h;\n";
                    $cache_file .= "self::\$data[\"{$name}\"][\"img_type\"] = $t;\n";
                }
            }
        }

        if ($options['suffixCode']) {
            $cache_file .= $options['suffixCode'];
        }

        $cache_file .= $this->phpFooter();

        file_put_contents($this->getFullCacheFilename(), $cache_file);
        chmod($this->getFullCacheFilename(), $this->fileChmod);

        return $this;
    }

    private function adjustBeforeDB($value, $type, $forCache = false)
    {
        switch ($type) {
            default:
                return $forCache ? addcslashes($value, '"') : addslashes($value);

            case 'checkbox':
            case 'int':
            case 'integer':
                return intval($value);

            case 'float':
            case 'double':
                return doubleval(str_replace(',', '.', $value));
        }
    }

    private function adjustAfterDB($value, $type)
    {
        switch ($type) {
            default:
                return $value;

            case 'checkbox':
            case 'int':
            case 'integer':
                return intval($value);

            case 'float':
            case 'double':
                return doubleval(str_replace(',', '.', $value));
        }
    }

    private function phpHeader()
    {
        return "<?php\n";
    }

    private function phpFooter()
    {
        return "self::\$cacheLoaded = true;";
    }
}
