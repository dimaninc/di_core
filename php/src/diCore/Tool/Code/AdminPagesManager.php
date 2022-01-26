<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.01.2016
 * Time: 18:38
 */

namespace diCore\Tool\Code;

use diCore\Admin\Data\FormFlag;
use diCore\Database\Connection;
use diCore\Helper\StringHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Data\Config;

class AdminPagesManager
{
    const fileChmod = 0664;

    const defaultFolder = '_admin/_inc/lib/pages';

    protected $fieldsByTable = [];

    private $namespace;

    protected $skipInColumnsFields = [
        'id',
        '_id',
        '__v',
        'visible',
        'order_num',
        'to_show_content',
        'level_num',
        'parent',
    ];

    protected $localFieldNames = [
        'clean_title',
        'slug',
        'order_num',
        'pic_w',
        'pic_h',
        'pic_t',
        'pic2_w',
        'pic2_h',
        'pic2_t',
        'pic3_w',
        'pic3_h',
        'pic3_t',
    ];

    protected $picFieldNames = [
        'pic',
        'pic2',
        'pic3',
        'logo',
        'img',
    ];

    protected $checkboxFieldNames = [
        'active',
        'activated',
        'visible',
        'top',
    ];

    protected $dateTimeFieldNames = [
        'date',
        'reg_date',
        'last_visit_date',
        'pay_date',
        'created_at',
        'edited_at',
        'updated_at',
        'done_at',
    ];

    protected $orderNumFieldNames = [
        'order_num',
    ];

    protected $staticFieldNames = [
        'date',
        'created_at',
        'edited_at',
        'updated_at',
    ];

    protected $untouchableFieldNames = [
        'date',
        'created_at',
        'edited_at',
        'updated_at',
    ];

    protected $initiallyHiddenFieldNames = [
        'date',
        'created_at',
        'edited_at',
        'updated_at',
    ];

    public function createPage($table, $caption, $className, $namespace = '')
    {
        // connection::table
        if (is_array($table)) {
            list($connName, $table) = $table;
        } else {
            $connName = null;
        }

        $this->setNamespace($namespace);

        $contents = <<<'EOF'
<?php
/**
 * Created by \diAdminPagesManager
 * Date: %1$s
 * Time: %2$s
 */
%11$s
use diCore\Admin\Data\FormFlag;
%12$s
class %3$s extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => '%9$s',
                'dir' => '%10$s',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('%4$s');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#href' => [],
%8$s
            '#edit' => '',
            '#del' => '',
            '#visible' => '',
            '#up' => '',
            '#down' => '',
        ]);
    }

    public function renderForm()
    {
    }

    public function submitForm()
    {
    }

    public function getFormTabs()
    {
        return [];
    }

    public function getFormFields()
    {
        return [
%6$s
        ];
    }

    public function getLocalFields()
    {
        return [
%7$s
        ];
    }

    public function getModuleCaption()
    {
        return '%5$s';
    }
}
EOF;

        $fields = $this->getFieldsOfTable($connName, $table);
        $className = $className ?: self::getClassNameByTable($table, $this->getNamespace());
        $caption = $caption ?: \diTypes::getTitle(\diTypes::getId($table));
        $fieldsInfo = $this->getFieldsInfo($fields);
        $columns = $this->getColumns($connName, $table);
        $sortBy = isset($fields['order_num']) ? 'order_num' : 'id';
        $dir = isset($fields['order_num']) ? 'ASC' : 'DESC';

        $contents = sprintf($contents,
            date('d.m.Y'),
            date('H:i'),
            ModelsManager::extractClass($className),
            $table,
            $caption,
            join("\n\n", $fieldsInfo['form']),
            join("\n\n", $fieldsInfo['local']),
            join("\n", $columns),
            $sortBy,
            $dir,
            $this->getNamespace() ? "\nnamespace " . ModelsManager::extractNamespace($className) . ";\n" : '',
            $this->getNamespace() ? "use " . ModelsManager::getModelClassNameByTable($table, $this->getNamespace()) . ";\n" : ''
        );

        $fn = $this->getPageFilename($className);

        if (is_file(Config::getSourcesFolder() . $fn)) {
            throw new \Exception("Admin page $fn already exists");
        }

        FileSystemHelper::createTree(Config::getSourcesFolder(), dirname($fn));

        file_put_contents(Config::getSourcesFolder() . $fn, $contents);
        chmod(Config::getSourcesFolder() . $fn, self::fileChmod);
    }

    public static function getClassNameByTable($table, $namespace = '')
    {
        return $namespace
            ? $namespace . '\\Admin\\Page\\' . camelize($table, false)
            : camelize('di_' . $table . '_page');
    }

    protected function getPageFilename($className)
    {
        if ($this->getNamespace()) {
            $className = ModelsManager::extractClass($className);
        }

        return $this->getFolder() . $className . '.php';
    }

    protected function getFieldsInfo($fields)
    {
        $ar = [
            'form' => [],
            'local' => [],
        ];

        foreach ($fields as $field => $type) {
            if (in_array($field, ['id', '_id', '__v'])) {
                continue;
            }

            $sort = in_array($field, $this->localFieldNames)
                ? 'local'
                : 'form';

            $ar[$sort][] = $this->getFieldInfo($field, $type);
        }

        return $ar;
    }

    protected function getFieldInfo($field, $type)
    {
        $typeTuned = $this->tuneType($field, $type);

        return <<<EOF
            '{$field}' => [
                'type' => '{$typeTuned}',
                'title' => '',
                'default' => '',{$this->getFlagsStr($field)}{$this->getExtraPropertiesStr($field)}
            ],
EOF;
    }

    protected function getExtraPropertiesStr($field)
    {
        $ar = [];

        if (in_array($field, $this->orderNumFieldNames)) {
            $ar[] = "'direction' => 1,";
        }

        if ($ar) {
            array_splice($ar, 0, 0, ['']);
        }

        return join("\n                ", $ar);
    }

    protected function getFlagsStr($field)
    {
        $fieldAlt = underscore($field);
        $flags = [];

        if (
            in_array($field, $this->staticFieldNames) ||
            in_array($fieldAlt, $this->staticFieldNames)
        ) {
            $flags[] = FormFlag::static;
        }

        if (
            in_array($field, $this->untouchableFieldNames) ||
            in_array($fieldAlt, $this->untouchableFieldNames)
        ) {
            $flags[] = FormFlag::untouchable;
        }

        if (
            in_array($field, $this->initiallyHiddenFieldNames) ||
            in_array($fieldAlt, $this->initiallyHiddenFieldNames)
        ) {
            $flags[] = FormFlag::initially_hidden;
        }

        return $flags
            ? "\n                'flags' => [" . join(', ', array_map(function($f) {
                    return 'FormFlag::' . $f;
                }, $flags)) . '],'
            : '';
    }

    protected function tuneType($field, $type)
    {
        if (in_array($field, $this->checkboxFieldNames)) {
            return 'checkbox';
        } elseif (in_array($field, $this->dateTimeFieldNames)) {
            return 'datetime_str';
        } elseif (in_array($field, $this->picFieldNames)) {
            return 'pic';
        } elseif (in_array($field, $this->orderNumFieldNames)) {
            return 'order_num';
        }

        $type = preg_replace('/\(.+$/', '', mb_strtolower($type));

        switch ($type) {
            case 'timestamp':
            case 'datetime':
                return 'datetime_str';

            case 'date':
                return 'date_str';

            case 'time':
                return 'time_str';

            case 'double':
            case 'float':
                return $type;

            case 'integer':
            case 'tinyint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'int';

            default:
                return 'string';
        }
    }

    protected function getFieldsOfTable($connName, $table)
    {
        if (!isset($this->fieldsByTable[$table])) {
            $this->fieldsByTable[$table] = $this->getDb($connName)->getFields($table);
        }

        return $this->fieldsByTable[$table];
    }

    protected function getColumns($connName, $table)
    {
        $modelName = ModelsManager::extractClass(
            ModelsManager::getModelClassNameByTable(
                $table,
                $this->getNamespace()
            )
        );

        $ar = [];

        foreach ($this->getFieldsOfTable($connName, $table) as $field => $type) {
            $fieldAlt = underscore($field);

            if (
                in_array($field, $this->skipInColumnsFields) ||
                in_array($fieldAlt, $this->skipInColumnsFields)
            ) {
                continue;
            }

            if (
                in_array($field, $this->dateTimeFieldNames) ||
                in_array($fieldAlt, $this->dateTimeFieldNames)
            ) {
                $methodName = $this->getDb($connName)->getFieldMethodForModel($field, 'get');

                $ar[] = <<<EOF
            '$field' => [
                'value' => function($modelName \$m) {
                    return \diDateTime::simpleFormat(\$m->{$methodName}());
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
EOF;
            }
            else
            {
                $ar[] = <<<EOF
            '$field' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
EOF;
            }
        }

        return $ar;
    }

    protected function getDb($connName = null)
    {
        return Connection::get($connName)->getDb();
    }

    protected function getFolderForNamespace()
    {
        return 'src/' . $this->getNamespace() . '/Admin/Page/';
    }

    public function getFolder()
    {
        return StringHelper::slash($this->getNamespace()
            ? $this->getFolderForNamespace()
            : static::defaultFolder);
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }
}