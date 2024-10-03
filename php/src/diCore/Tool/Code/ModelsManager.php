<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 14:38
 */

namespace diCore\Tool\Code;

use diCore\Database\Connection;
use diCore\Helper\StringHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Data\Config;

class ModelsManager
{
    const fileChmod = 0664;

    const defaultModelFolder = '_cfg/models';
    const defaultCollectionFolder = '_cfg/collections';

    const typeTuneRegex = "/(\(\d+\))?(\s+unsigned)?$/";

    protected $fieldsByTable = [];

    protected $usedModelTraits = [];
    protected $usedModelNamespaces = [];
    protected $implementedModelMethods = [];

    protected $usedCollectionTraits = [];
    protected $usedCollectionNamespaces = [];
    protected $implementedCollectionMethods = [];

    private $namespace;

    public static $skippedInModelAnnotationFields = [
        'id',
        '_id', // mongo id
        'clean_title',
        'slug',
        '__v', // mongo tech field
    ];

    public static $skippedInCollectionAnnotationFields = [
        '__v', // mongo tech field
    ];

    protected function getModelTemplate()
    {
        return '<?php' .
            <<<'EOF'

/**
 * Created by ModelsManager
 * Date: {{ date }}
 * Time: {{ time }}
 */
{{ namespace }}

use diCore\Database\FieldType;{{ usedNamespaces }}

/**
 * Class {{ className }}
 * Methods list for IDE
 *
{{ getMethods }}
 *
{{ hasMethods }}
 *
{{ setMethods }}{{ localizedMethods }}
 */
class {{ className }} extends \diModel
{
    {{ usedTraits }}const type = \diTypes::{{ modelType }};{{ connectionLine }}
    const table = '{{ table }}';
    protected $table = '{{ table }}';{{ slugFieldName }}{{ localizedFields }}{{ fieldTypes }}{{ implementedMethods }}
}

EOF;
    }

    protected function getCollectionTemplate()
    {
        return '<?php' .
            <<<'EOF'

/**
 * Created by ModelsManager
 * Date: {{ date }}
 * Time: {{ time }}
 */
{{ namespace }}{{ usedNamespaces }}
/**
 * Class {{ className }}
 * Methods list for IDE
 *
{{ filterMethods }}
 *
{{ orderMethods }}
 *
{{ selectMethods }}{{ localizedMethods }}
 */
class {{ className }} extends \diCollection
{
    {{ usedTraits }}const type = \diTypes::{{ modelType }};{{ connectionLine }}
    protected $table = '{{ table }}';
    protected $modelType = '{{ modelType }}';{{ implementedMethods }}
}

EOF;
    }

    public function createEntity(
        $table,
        $modelNeeded,
        $modelClassName,
        $collectionNeeded = false,
        $collectionClassName = '',
        $namespace = ''
    ) {
        // connection::table
        if (is_array($table)) {
            list($connName, $table) = $table;
        } else {
            $connName = null;
        }

        $this->setNamespace($namespace);

        $connectionNameStr = $connName
            ? "\n    const connection_name = '{$connName}';"
            : '';
        $fields = $this->getFieldsOfTable($connName, $table);
        $this->populateTraits($fields);

        if ($modelNeeded) {
            $typeName = self::getModelNameByTable($table);
            $modelClassName = self::getModelClassNameByTable(
                $modelClassName ?: $table,
                $this->getNamespace()
            );
            $annotations = $this->getModelMethodsAnnotations(
                $fields,
                $connName,
                $modelClassName
            );

            $slugFieldName = $this->doesTableHaveField(
                $connName,
                $table,
                \diModel::SLUG_FIELD_NAME
            )
                ? "\n    const slug_field_name = self::SLUG_FIELD_NAME;"
                : '';

            $replaces = [
                '{{ date }}' => \diDateTime::simpleDateFormat(),
                '{{ time }}' => \diDateTime::simpleTimeFormat(),
                '{{ className }}' => self::extractClass($modelClassName),
                '{{ table }}' => $table,
                '{{ getMethods }}' => join("\n", $annotations['get']),
                '{{ hasMethods }}' => join("\n", $annotations['has']),
                '{{ setMethods }}' => join("\n", $annotations['set']),
                '{{ localizedMethods }}' => $annotations['localized']
                    ? "\n *\n" . join("\n", $annotations['localized'])
                    : '',
                '{{ localizedFields }}' => $annotations['localized']
                    ? "\n    protected \$localizedFields = [" .
                        join(
                            ', ',
                            array_map(function ($val) {
                                return "'" . $val . "'";
                            }, array_keys($annotations['localized']))
                        ) .
                        '];'
                    : '',
                '{{ slugFieldName }}' => $slugFieldName,
                '{{ modelType }}' => $typeName,
                '{{ namespace }}' => $this->getNamespace()
                    ? "\nnamespace " . self::extractNamespace($modelClassName) . ';'
                    : '',
                '{{ connectionLine }}' => $connectionNameStr,
                '{{ fieldTypes }}' => $this->getFieldTypesArrayStr($fields),
                '{{ usedNamespaces }}' => $this->getUsedModelNamespaces(),
                '{{ usedTraits }}' => $this->getUsedModelTraits(),
                '{{ implementedMethods }}' => $this->getImplementedModelMethods(),
            ];

            $contents = str_replace(
                array_keys($replaces),
                array_values($replaces),
                $this->getModelTemplate()
            );

            $fn = $this->getModelFilename($modelClassName);

            if (is_file(Config::getSourcesFolder() . $fn)) {
                throw new \Exception("Model $fn already exists");
            }

            FileSystemHelper::createTree(Config::getSourcesFolder(), dirname($fn));

            file_put_contents(Config::getSourcesFolder() . $fn, $contents);
            chmod(Config::getSourcesFolder() . $fn, self::fileChmod);
        }

        if ($collectionNeeded) {
            $typeName = self::getModelNameByTable($table);
            $collectionClassName = self::getCollectionClassNameByTable(
                $collectionClassName ?: $table,
                $this->getNamespace()
            );
            $collectionAnnotations = $this->getCollectionMethodsAnnotations(
                $fields,
                $connName,
                $collectionClassName
            );

            $replaces = [
                '{{ date }}' => \diDateTime::simpleDateFormat(),
                '{{ time }}' => \diDateTime::simpleTimeFormat(),
                '{{ className }}' => self::extractClass($collectionClassName),
                '{{ table }}' => $table,
                '{{ modelType }}' => $typeName,
                '{{ filterMethods }}' => join(
                    "\n",
                    $collectionAnnotations['filterBy']
                ),
                '{{ orderMethods }}' => join(
                    "\n",
                    $collectionAnnotations['orderBy']
                ),
                '{{ selectMethods }}' => join(
                    "\n",
                    $collectionAnnotations['select']
                ),
                '{{ localizedMethods }}' => $collectionAnnotations[
                    'filterByLocalized'
                ]
                    ? "\n *\n" .
                        join("\n", $collectionAnnotations['filterByLocalized']) .
                        "\n *\n" .
                        join("\n", $collectionAnnotations['orderByLocalized']) .
                        "\n *\n" .
                        join("\n", $collectionAnnotations['selectLocalized'])
                    : '',
                '{{ namespace }}' => $this->getNamespace()
                    ? "\nnamespace " .
                        self::extractNamespace($collectionClassName) .
                        ";\n"
                    : '',
                '{{ connectionLine }}' => $connectionNameStr,
                '{{ usedNamespaces }}' => $this->getUsedCollectionNamespaces(),
                '{{ usedTraits }}' => $this->getUsedCollectionTraits(),
                '{{ implementedMethods }}' => $this->getImplementedCollectionMethods(),
            ];

            $contents = str_replace(
                array_keys($replaces),
                array_values($replaces),
                $this->getCollectionTemplate()
            );

            $fn = $this->getCollectionFilename($collectionClassName);

            if (is_file(Config::getSourcesFolder() . $fn)) {
                throw new \Exception("Collection $fn already exists");
            }

            FileSystemHelper::createTree(Config::getSourcesFolder(), dirname($fn));

            file_put_contents(Config::getSourcesFolder() . $fn, $contents);
            chmod(Config::getSourcesFolder() . $fn, self::fileChmod);
        }
    }

    public static function getModelNameByTable($table)
    {
        if (in_array($table, ['news'])) {
            return $table;
        }

        if (substr($table, -3) == 'ies') {
            $table = substr($table, 0, -3) . 'y';
        } elseif (
            substr($table, -1) == 's' &&
            !in_array(substr($table, -2), ['ss', 'us', 'os', 'as', 'ys', 'is'])
        ) {
            $table = substr($table, 0, -1);
        }

        return $table;
    }

    public static function getModelClassNameByTable($table, $namespace = '')
    {
        return $namespace
            ? $namespace .
                    '\\Entity\\' .
                    camelize(self::getModelNameByTable($table), false) .
                    '\\Model'
            : camelize('di_' . self::getModelNameByTable($table) . '_model');
    }

    public static function getCollectionClassNameByTable($table, $namespace = '')
    {
        return $namespace
            ? $namespace .
                    '\\Entity\\' .
                    camelize(self::getModelNameByTable($table), false) .
                    '\\Collection'
            : camelize('di_' . self::getModelNameByTable($table) . '_collection');
    }

    protected function getFieldTypesArrayStr($fields)
    {
        $s = "\n\n    protected static \$fieldTypes = [";

        foreach ($fields as $field => $type) {
            $type = self::tuneTypeForModel($type);
            $s .= "\n        '{$field}' => FieldType::{$type},";
        }

        $s .= "\n    ];";

        return $s;
    }

    protected function isFieldSkippedInModelAnnotation($field)
    {
        if (
            in_array($field, ['created_at', 'updated_at']) &&
            in_array('AutoTimestamps', $this->usedModelTraits)
        ) {
            return true;
        }

        return in_array($field, static::$skippedInModelAnnotationFields);
    }

    protected function isFieldSkippedInCollectionAnnotation($field)
    {
        if (
            in_array($field, ['created_at', 'updated_at']) &&
            in_array('AutoTimestamps', $this->usedCollectionTraits)
        ) {
            return true;
        }

        return in_array($field, static::$skippedInCollectionAnnotationFields);
    }

    protected function getModelMethodsAnnotations($fields, $connName, $className)
    {
        $ar = [
            'get' => [],
            'has' => [],
            'set' => [],
            'localized' => [],
        ];
        $localizedNeeded = [];

        /*
        if ($this->getNamespace()) {
            $className = self::extractClass($className);
        }
        */

        $className = '$this';

        foreach ($fields as $field => $type) {
            if ($this->isFieldSkippedInModelAnnotation($field)) {
                continue;
            }

            $typeStr = self::tuneType($type);
            $typeTab = "\t";
            if (strlen($typeStr) <= 4) {
                $typeTab .= "\t";
            }

            $ar['get'][] =
                ' * @method ' .
                $typeStr .
                $typeTab .
                $this->getDb($connName)->getFieldMethodForModel($field, 'get');
            $ar['has'][] =
                ' * @method bool ' .
                $this->getDb($connName)->getFieldMethodForModel($field, 'has');
            $ar['set'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel($field, 'set') .
                "(\$value)";

            // localization tests
            $fieldComponents = explode('_', $field);

            if (
                $fieldComponents &&
                in_array($fieldComponents[0], \diCurrentCMS::$possibleLanguages)
            ) {
                $f = substr($field, strlen($fieldComponents[0]) + 1);

                if (isset($fields[$f])) {
                    $localizedNeeded[$f] = $type;
                }
            }
            //
        }

        foreach ($localizedNeeded as $field => $type) {
            $typeStr = self::tuneType($type);
            $typeTab = "\t";
            if (strlen($typeStr) <= 4) {
                $typeTab .= "\t";
            }

            $ar['localized'][$field] =
                ' * @method ' .
                $typeStr .
                $typeTab .
                $this->getDb($connName)->getFieldMethodForModel($field, 'localized');
        }

        return $ar;
    }

    protected function getCollectionMethodsAnnotations(
        $fields,
        $connName,
        $className
    ) {
        $ar = [
            'filterBy' => [],
            'filterByLocalized' => [],
            'orderBy' => [],
            'orderByLocalized' => [],
            'select' => [],
            'selectLocalized' => [],
        ];
        $localizedNeeded = [];

        /*
        if ($this->getNamespace()) {
            $className = self::extractClass($className);
        }
        */

        $className = '$this';

        foreach ($fields as $field => $type) {
            if ($this->isFieldSkippedInCollectionAnnotation($field)) {
                continue;
            }

            $ar['filterBy'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel($field, 'filterBy') .
                "(\$value, \$operator = null)";
            $ar['orderBy'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel($field, 'orderBy') .
                "(\$direction = null)";
            $ar['select'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel($field, 'select');

            // localization tests
            $fieldComponents = explode('_', $field);

            if (
                $fieldComponents &&
                in_array($fieldComponents[0], \diCurrentCMS::$possibleLanguages)
            ) {
                $f = substr($field, strlen($fieldComponents[0]) + 1);

                if (isset($fields[$f])) {
                    $localizedNeeded[$f] = $type;
                }
            }
        }

        foreach ($localizedNeeded as $field => $type) {
            $ar['filterByLocalized'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel(
                    $field,
                    'filterByLocalized'
                ) .
                "(\$value, \$operator = null)";
            $ar['orderByLocalized'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel(
                    $field,
                    'orderByLocalized'
                ) .
                "(\$direction = null)";
            $ar['selectLocalized'][] =
                " * @method $className " .
                $this->getDb($connName)->getFieldMethodForModel(
                    $field,
                    'selectLocalized'
                );
        }

        return $ar;
    }

    public static function tuneType($type)
    {
        $type = preg_replace(static::typeTuneRegex, '', strtolower($type));

        switch ($type) {
            case 'integer':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'integer';

            case 'float':
            case 'double':
            case 'double precision':
                return 'double';

            case 'bool':
            case 'boolean':
                return 'bool';

            case 'array':
            case 'json':
                return 'array';

            default:
                return 'string';
        }
    }

    public static function tuneTypeForModel($type)
    {
        $type = preg_replace(static::typeTuneRegex, '', strtolower($type));

        switch ($type) {
            case 'integer':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'int';

            case 'date':
            case 'time':
            case 'datetime':
            case 'timestamp':
            case 'float':
                return $type;

            case 'timestamp without time zone':
                return 'timestamp';

            case 'double':
            case 'double precision':
                return 'double';

            case 'bool':
            case 'boolean':
                return 'bool';

            case 'array':
            case 'json':
            case 'jsonb':
                return 'json';

            default:
                return 'string';
        }
    }

    protected function getFieldsOfTable($connName, $table)
    {
        if (!isset($this->fieldsByTable[$table])) {
            $this->fieldsByTable[$table] = $this->getDb($connName)->getFields(
                $table
            );
        }

        return $this->fieldsByTable[$table];
    }

    protected function doesTableHaveField($connName, $table, $field)
    {
        $allFields = $this->getFieldsOfTable($connName, $table);

        return isset($allFields[$field]);
    }

    protected function getDb($connName = null)
    {
        return Connection::get($connName)->getDb();
    }

    protected function getModelFilename($className)
    {
        if ($this->getNamespace()) {
            $className = self::extractClass(self::extractNamespace($className));
        }

        return $this->getModelFolder() .
            $className .
            ($this->getNamespace() ? '/Model' : '') .
            '.php';
    }

    protected function getCollectionFilename($className)
    {
        if ($this->getNamespace()) {
            $className = self::extractClass(self::extractNamespace($className));
        }

        return $this->getCollectionFolder() .
            $className .
            ($this->getNamespace() ? '/Collection' : '') .
            '.php';
    }

    protected function getFolderForNamespace()
    {
        /*
        $pathPrefix = \diLib::isNamespaceRoot($this->getNamespace())
            ? Config::getSourcesFolder()
            : '';
        */

        return 'src/' . $this->getNamespace() . '/Entity/';
    }

    protected function getModelFolder()
    {
        return StringHelper::slash(
            $this->getNamespace()
                ? $this->getFolderForNamespace()
                : static::defaultModelFolder
        );
    }

    protected function getCollectionFolder()
    {
        return StringHelper::slash(
            $this->getNamespace()
                ? $this->getFolderForNamespace()
                : static::defaultCollectionFolder
        );
    }

    public static function extractNamespace($className)
    {
        return \diLib::parentNamespace($className);
    }

    public static function extractClass($className)
    {
        return \diLib::childNamespace($className);
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }

    protected function populateTraits($fields)
    {
        if (isset($fields['created_at']) && isset($fields['updated_at'])) {
            $this->usedModelTraits[] = 'AutoTimestamps';
            $this->usedModelNamespaces[] = 'diCore\Traits\Model\AutoTimestamps';
            $this->implementedModelMethods[] = <<<'EOF'
    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }
EOF;

            $this->usedCollectionTraits[] = 'AutoTimestamps';
            $this->usedCollectionNamespaces[] =
                'diCore\Traits\Collection\AutoTimestamps';
        }

        return $this;
    }

    protected function getUsedModelTraits()
    {
        return $this->usedModelTraits
            ? join(
                    "\n",
                    array_map([self::class, 'mapUsed'], $this->usedModelTraits)
                ) . "\n\n    "
            : '';
    }

    protected function getUsedCollectionTraits()
    {
        return $this->usedCollectionTraits
            ? join(
                    "\n",
                    array_map([self::class, 'mapUsed'], $this->usedCollectionTraits)
                ) . "\n\n    "
            : '';
    }

    protected function getUsedModelNamespaces()
    {
        return $this->usedModelNamespaces
            ? "\n" .
                    join(
                        "\n",
                        array_map(
                            [self::class, 'mapUsed'],
                            $this->usedModelNamespaces
                        )
                    )
            : '';
    }

    protected function getUsedCollectionNamespaces()
    {
        return $this->usedCollectionNamespaces
            ? "\n" .
                    join(
                        "\n",
                        array_map(
                            [self::class, 'mapUsed'],
                            $this->usedCollectionNamespaces
                        )
                    ) .
                    "\n"
            : '';
    }

    public static function mapUsed($name)
    {
        return "use $name;";
    }

    protected function getImplementedModelMethods()
    {
        return $this->implementedModelMethods
            ? "\n\n" . join("\n", $this->implementedModelMethods)
            : '';
    }

    protected function getImplementedCollectionMethods()
    {
        return $this->implementedCollectionMethods
            ? "\n\n" . join("\n", $this->implementedCollectionMethods)
            : '';
    }
}
