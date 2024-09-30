<?php
/*
    // dimaninc

    // 2012/10/19
        * timestamp (datetime_str) support added

    // 2011/05/06
        * ::possible_sortby_ar added

    // 2011/04/20
        * js datetime picker support added
        * date range selects naming changed
        * ::applied_date added

    // 2011/04/18
        * auto detection of date range improved

    // 2011/04/15
        * default value #2 added (for date ranges)

    // 2010/12/22
        * default values added

    // 2010/11/29
        * ::title added
        * ::static_inputs_ar added
        * ::values_ar added
        * ::get_static_form() added
        * ::set_static_input() added

    // 2010/11/07
        * ::andor added

    // 2009/07/08
        * lots of additions

    // 2009/05/28
        * ::add_where_condition()

    // 2009/02/13
        * birthday
*/

use diCore\Admin\BasePage;
use diCore\Admin\Config;
use diCore\Admin\FilterRule;
use diCore\Admin\Form;
use diCore\Data\Configuration;
use diCore\Database\Engine;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;

class diAdminFilters
{
    const DEFAULT_WHERE_TPL = "[-field-]='[-value-]'";

    const DEFAULT_INPUT_SIZE_STRING = 40;
    const DEFAULT_INPUT_SIZE_NUMBER = 6;

    const EMPTY_STRING = '$EMPTY$';

    const PARAM_SORT_FIELD = 'sortby';
    const PARAM_SORT_DIR = 'dir';

    public static $dirAr = [
        'ru' => [
            'ASC' => 'По возрастанию',
            'DESC' => 'По убыванию',
        ],

        'en' => [
            'ASC' => 'Ascending',
            'DESC' => 'Descending',
        ],
    ];

    public static $dateRangeAr = ['d1', 'm1', 'y1', 'd2', 'm2', 'y2'];

    public static $lngStrings = [
        'en' => [
            'form.caption' => 'Sort by',
            'form.submit.title' => 'Apply filter',
            'form.reset.title' => 'Reset',
            'link.copy.title' => 'Copy link',
            'link.copy.icon' => 'svg/copy-link.svg',
            'calendar' => 'Calendar',
        ],

        'ru' => [
            'form.caption' => 'Сортировать',
            'form.submit.title' => 'Применить фильтр',
            'form.reset.title' => 'Сбросить',
            'link.copy.title' => 'Копировать ссылку',
            'link.copy.icon' => 'svg/copy-link.svg',
            'calendar' => 'Календарь',
        ],
    ];

    public $table;

    /** @var \diDB */
    private static $db;

    /** @var BasePage */
    private $AdminPage;

    /**
     * @var array
     */
    protected $buttonOptions = [];

    private $notes = [];

    protected $language = 'ru';

    public $ar = [];
    /** @var null|array */
    protected $tableData = null;
    private $predefinedData = [];
    protected $data = [];
    public $sortBy = '';
    public $dir = '';
    public $default_sortby = '';
    public $default_dir = '';
    public $where = '';
    public $where_ar = [];
    protected $ruleCallbacks = [];
    public $inputs_ar = [];
    public $input_params_ar = [];
    protected $inputPrefixes = [];
    protected $inputSuffixes = [];
    protected $inputResetButtons = [];
    public $andor = 'and';
    public $static_mode = false;
    public $static_inputs_ar = [];
    public $values_ar = [];
    public $possible_sortby_ar = [];
    public $reset = false;

    protected $sortable = true;
    protected $hidden = false;

    private $buttonsPrefix = null;
    private $buttonsSuffix = null;

    private $useCookie = true;

    /** @var \diTwig */
    private $twig;
    private $twigBasicsAssigned = false;

    public function __construct(
        $table,
        $sortBy = 'id',
        $dir = 'ASC',
        $possibleSortByAr = []
    ) {
        if ($table instanceof BasePage) {
            $this->AdminPage = $table;

            $this->table = $this->AdminPage->getTable();
            $this->language = $this->AdminPage->getAdmin()->getLanguage();
        } else {
            $this->table = $table;
        }

        self::$db = \diModel::createForTable($this->table)
            ::getConnection()
            ->getDb();

        $this->gatherInitialData($sortBy, $dir, $possibleSortByAr);
    }

    public function getTwig()
    {
        if (!$this->twig) {
            $this->twig = $this->AdminPage
                ? $this->AdminPage->getTwig()
                : \diTwig::create();

            $this->assignTwigBasics();
        }

        return $this->twig;
    }

    public function getAdminPage()
    {
        if (!$this->AdminPage) {
            throw new \Exception('AdminPage not defined in diAdminFilters');
        }

        return $this->AdminPage;
    }

    protected function assignTwigBasics()
    {
        if (!$this->twigBasicsAssigned) {
            $this->twig->assign(
                [
                    'F' => $this,
                    'lang' => $this->L(),
                    'table' => $this->table,
                ],
                true
            );

            $this->twigBasicsAssigned = true;
        }

        return $this;
    }

    protected function gatherInitialData(
        $sortBy = 'id',
        $dir = 'ASC',
        $possibleSortByAr = []
    ) {
        $this->possible_sortby_ar = is_string($possibleSortByAr)
            ? explode(',', $possibleSortByAr)
            : $possibleSortByAr;
        $this->set_default_sorter($sortBy, $dir);

        $this->reset = !!\diRequest::get('__diaf_reset');

        return $this;
    }

    public function setSortableState($state)
    {
        $this->sortable = $state;

        return $this;
    }

    public function getSortableState()
    {
        return $this->sortable;
    }

    public function getTable()
    {
        return $this->table;
    }

    public static function getDb()
    {
        return self::$db;
    }

    public function setSortBy($sortBy)
    {
        if ($sortBy) {
            $this->sortBy = $sortBy;
        }

        return $this;
    }

    public function getSortBy()
    {
        return $this->sortBy;
    }

    public function setDir($dir)
    {
        if ($dir) {
            $this->dir = strtoupper($dir);
        }

        return $this;
    }

    public function getDir()
    {
        return $this->dir;
    }

    public function getData($field = null)
    {
        if ($field === null) {
            $this->buildQuery();
        }

        return $field ? $this->data[$field] ?? null : $this->data;
    }

    public function setData($field, $value)
    {
        $this->data[$field] = $value;

        return $this;
    }

    public function getPredefinedData($field)
    {
        return $this->predefinedData[$field] ?? null;
    }

    public function setPredefinedData($field, $value)
    {
        $this->predefinedData[$field] = $value;

        return $this;
    }

    public function hideBlock()
    {
        $this->hidden = true;

        return $this;
    }

    public function isUseCookie()
    {
        return $this->useCookie;
    }

    public function setUseCookie($useCookie)
    {
        $this->useCookie = $useCookie;

        return $this;
    }

    /** @deprecated */
    public function set_input($field, $input)
    {
        return $this->setInput($field, $input);
    }

    public function setInput($field, $input)
    {
        $this->inputs_ar[$field] = $input;

        if (gettype($input) == 'object' && $input instanceof \diSelect) {
            // setting clean name for 'get' submit
            if (strpos($input->getAttr('name'), 'admin_filter[') === 0) {
                $input->setAttr('name', substr($input->getAttr('name'), 13, -1));
            }

            // getting first option
            if (!empty($this->ar[$field]['strict'])) {
                $this->setPredefinedData(
                    $field,
                    $input->getItem(0, 'value')
                )->buildQuery();
            }
        }

        return $this;
    }

    public function setInputPrefix($field, $prefix)
    {
        $this->inputPrefixes[$field] = $prefix;

        return $this;
    }

    public function setInputSuffix($field, $suffix)
    {
        $this->inputSuffixes[$field] = $suffix;

        return $this;
    }

    public function getInputPrefix($field)
    {
        return isset($this->inputPrefixes[$field])
            ? $this->inputPrefixes[$field]
            : '';
    }

    public function getInputSuffix($field)
    {
        return isset($this->inputSuffixes[$field])
            ? $this->inputSuffixes[$field]
            : '';
    }

    public function getInputResetButton($field)
    {
        return isset($this->inputResetButtons[$field])
            ? $this->getResetButton($field)
            : '';
    }

    public function setInputResetButton($field, $value = true)
    {
        $this->inputResetButtons[$field] = $value;

        return $this;
    }

    public function shouldFilterShowCopyLinkToClipboardButton()
    {
        return Config::basicCreate()::shouldFilterShowCopyLinkToClipboardButton();
    }

    public function set_static_input($field, $input)
    {
        $this->static_inputs_ar[$field] = $input;

        return $this;
    }

    public function setNote($field, $note)
    {
        $this->notes[$field] = $note;

        return $this;
    }

    public function getNote($field)
    {
        return isset($this->notes[$field]) ? $this->notes[$field] : null;
    }

    private function getFieldHtml($title, $input)
    {
        if (is_array($title)) {
            $title = $title[$this->language];
        } elseif (is_callable($title)) {
            $title = $title($this);
        }

        return "<b>$title:</b> $input";
    }

    private function getRowHtml($html, $field)
    {
        return "<div class=\"row\" data-field=\"$field\">$html</div>";
    }

    public function getBlockHtml()
    {
        if ($this->getInput(static::PARAM_SORT_FIELD)) {
            $sorterBlock = $this->getRowHtml(
                $this->getFieldHtml(
                    $this->L('form.caption'),
                    $this->getInput(static::PARAM_SORT_FIELD) .
                        ' ' .
                        $this->getInput(static::PARAM_SORT_DIR)
                ),
                static::PARAM_SORT_FIELD
            );

            if ($this->getNote(static::PARAM_SORT_FIELD)) {
                $sorterBlock .= $this->getRowHtml(
                    $this->getNote(static::PARAM_SORT_FIELD),
                    static::PARAM_SORT_FIELD . '-note'
                );
            }
        } else {
            $sorterBlock = '';
        }

        $filterRowsAr = [];
        $F = Form::basicCreate($this->getAdminPage());

        foreach ($this->ar as $a) {
            $field = $a['field'];
            $title =
                $a['title'] ?:
                ArrayHelper::get(
                    $this->AdminPage->getFormFields(),
                    $field . '.title'
                ) ?:
                $F::getFieldTitle(
                    $field,
                    $this->AdminPage->getFieldProperty($field),
                    $this->language
                ) ?:
                $field;

            $filterRowsAr[] = $this->getRowHtml(
                $this->getFieldHtml($title, $this->getInput($field)),
                $field
            );

            if ($this->getNote($field)) {
                $filterRowsAr[] = $this->getRowHtml($this->getNote($field), $field);
            }
        }

        return $this->getTwig()->parse('admin/_filter/ui', [
            'hidden' => $this->hidden,
            'href' => $this->getHref(),
            'sorter' => $sorterBlock,
            'rows' => join("\n", $filterRowsAr),
        ]);
    }

    public function getHref()
    {
        $params = array_filter($this->data);

        foreach ($params as &$value) {
            if (
                is_array($value) &&
                (isset($value['timestamp1']) || isset($value['timestamp2']))
            ) {
                unset($value['timestamp1']);
                unset($value['timestamp2']);
            }
        }

        if (
            $this->getSortBy() != $this->default_sortby ||
            $this->getDir() != $this->default_dir
        ) {
            $params[static::PARAM_SORT_FIELD] = $this->getSortBy();
            $params[static::PARAM_SORT_DIR] = $this->getDir();
        }

        if (
            $this->getAdminPage()->hasPagesNavy() &&
            !$this->getAdminPage()
                ->getPagesNavy()
                ->isStartingPage()
        ) {
            $params[
                $this->getAdminPage()
                    ->getPagesNavy()
                    ->getPageParam()
            ] = $this->getAdminPage()
                ->getPagesNavy()
                ->getPage();
        }

        return $this->getAdminPage()->getListFullHref($params);
    }

    public static function get_user_id_where(
        $userFields = ['name', 'login', 'email'],
        $userTable = 'users'
    ) {
        $condition = join(
            ' or ',
            array_map(function ($field) {
                $conn = self::getDb()->getConnection();
                $op =
                    $conn && Engine::isMySql($conn::getEngine())
                        ? 'INSTR'
                        : 'STRPOS';

                // $field = self::getDb()->escapeField($field);
                $value = self::getDb()->escapeValue('[-value-]');
                return "$op($field, $value) > 0";
            }, $userFields)
        );

        $field = self::getDb()->escapeField('[-field-]');
        $idField = self::getDb()->escapeField('id');
        $value = self::getDb()->escapeValue('[-value-]');
        $userTable = self::getDb()->escapeTable($userTable);

        return "($field > 0 AND ($field = $value or $field in (SELECT $idField FROM $userTable WHERE $condition)))";
    }

    public static function getUserWhere(
        $userFields = ['name', 'login', 'email'],
        $userTable = 'users'
    ) {
        return function ($field, $value, $not = false, $tablePrefix = '') use (
            $userFields,
            $userTable
        ) {
            $field = self::getDb()->escapeField($field);
            $value = self::getDb()->escapeValue($value);
            $intValue = self::getDb()->escapeValue((int) $value);
            $condition = join(
                ' or ',
                array_map(function ($field) use ($value) {
                    $conn = self::getDb()->getConnection();
                    $op =
                        $conn && Engine::isMySql($conn::getEngine())
                            ? 'INSTR'
                            : 'STRPOS';

                    return "$op($field, $value) > 0";
                }, $userFields)
            );

            $idField = self::getDb()->escapeField('id');
            $userTable = self::getDb()->escapeTable($userTable);

            return "($field > 0 AND ($field = $intValue or $field in (SELECT $idField FROM $userTable WHERE $condition)))";
        };
    }

    public function getFilter($field)
    {
        foreach ($this->ar as $ar) {
            if ($ar['field'] === $field) {
                return $ar;
            }
        }

        return null;
    }

    public function getFilters()
    {
        return $this->ar;
    }

    /** @deprecated */
    public function add_filter(
        $field,
        $type = null,
        $where_tpl = null,
        $title = '',
        $default_value = null,
        $default_value2 = null
    ) {
        return $this->addFilter(
            $field,
            $type,
            $where_tpl,
            $title,
            $default_value,
            $default_value2
        );
    }

    // $where_tpl could be string w tokens: [-field-], [-value-]
    // or a function($field, $value) which returns string for WHERE condition
    public function addFilter(
        $field,
        $type = null,
        $where_tpl = null,
        $title = '',
        $default_value = null,
        $default_value2 = null
    ) {
        $opts = [
            'field' => is_array($field) ? '' : $field,
            'alias' => null,
            'type' => $type ?: 'str',
            'input_size' => null, // force <input size> if needed
            'rule' => null, // callback or constant for predefined callback, instead of where_tpl
            'where_tpl' => $where_tpl,
            'title' => $title,
            'placeholder' => '',
            'submitOnChange' => false, // submit filter form on change
            'default_value' => $default_value,
            'default_value2' => $default_value2,
            'strict' => false,
            'value' => null,
            'not' => false,
            'queryPrefix' => '',
            'querySuffix' => '',
            'feed' => null,
            'after' => null, // if set, added not to end, but after this field
        ];

        if (is_array($field)) {
            $opts = extend($opts, $field);
        }

        if ($opts['submitOnChange']) {
            $this->set_input_params($opts['field'], [
                'data-submit-on-change' => $opts['submitOnChange'],
            ]);
        }

        $key = $opts['alias'] ?: $opts['field'];

        if ($opts['after'] && isset($this->ar[$opts['after']])) {
            $this->ar = ArrayHelper::addItemsToAssocArrayAfterKey(
                $this->ar,
                $opts['after'],
                [
                    $key => $opts,
                ]
            );
        } else {
            $this->ar[$key] = $opts;
        }

        // getting first option
        if (!empty($opts['strict']) && $opts['feed']) {
            $sel = \diSelect::fastCreate('', '', $opts['feed']);

            $this->setPredefinedData(
                $opts['alias'] ?: $opts['field'],
                $sel->getItem(0, 'value')
            );

            unset($sel);
        }

        return $this;
    }

    public function setFilterAttr($fields, $attr, $value = null)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            if (!is_array($attr)) {
                $attr = [
                    $attr => $value,
                ];
            }

            $this->ar[$field] = extend($this->ar[$field], $attr);
        }

        return $this;
    }

    public function replaceFilter($name, array $newFilters)
    {
        $this->insertFiltersBefore($name, $newFilters)->removeFilter($name);

        return $this;
    }

    public function renameFilter($name, $newName)
    {
        $this->insertFiltersBefore($name, [$newName => $this->ar[$name]])
            ->removeFilter($name)
            ->setFilterAttr($newName, [
                'field' => $newName,
            ]);

        return $this;
    }

    public function insertFiltersBefore($name, array $newFilters)
    {
        $this->ar = ArrayHelper::addItemsToAssocArrayBeforeKey(
            $this->ar,
            $name,
            $newFilters
        );

        return $this;
    }

    public function insertFiltersAfter($name, array $newFilters)
    {
        $this->ar = ArrayHelper::addItemsToAssocArrayAfterKey(
            $this->ar,
            $name,
            $newFilters
        );

        return $this;
    }

    public function removeFilter($field)
    {
        $idx = $this->get_idx_by_field($field);

        if (isset($this->ar[$field])) {
            unset($this->ar[$field]);
        } elseif (isInteger($idx)) {
            array_splice($this->ar, $idx, 1);
        }

        return $this;
    }

    public function add_where_condition($condition)
    {
        $this->where_ar[] = $condition;

        return $this;
    }

    public function set_default_sorter($sortBy, $dir = null)
    {
        if (is_array($sortBy) && is_null($dir)) {
            $dir = $sortBy['dir'];
            $sortBy = $sortBy['sortBy'];
        }

        $this->default_sortby = $sortBy;
        $this->default_dir = $dir;

        $this->setSortBy($sortBy)->setDir($dir);

        return $this;
    }

    public function get_js_data($print_script_tags = false)
    {
        $ar = [];

        foreach ($this->ar as $a) {
            if (in_array($a['type'], ['date_range', 'date_str_range'])) {
                foreach (self::$dateRangeAr as $_f) {
                    $_ff = 'd' . $_f[0];
                    $_idx = $_f[1];

                    $ar[] = "{$a['field']}][{$_idx}][{$_ff}";
                }
            } else {
                $ar[] = $a['field'];
            }
        }

        if ($this->getSortableState()) {
            $ar[] = static::PARAM_SORT_FIELD;
            $ar[] = static::PARAM_SORT_DIR;
        }

        if ($print_script_tags) {
            $s =
                "<script type=\"text/javascript\">$(function() { " .
                "window.diAF = new diAdminFilters({table: '" .
                $this->getTable() .
                "', fields: ['" .
                join("','", $ar) .
                "']});" .
                " });</script>\n";
        } else {
            $s =
                "<script type=\"text/javascript\" src=\"_js/filters.js\"></script>\n" .
                "filters_ar['{$this->table}'] = ['" .
                join("','", $ar) .
                "'];\n";
        }

        return $s;
    }

    public function setButtonOptions($options)
    {
        $this->buttonOptions = extend($this->buttonOptions, $options);

        return $this;
    }

    public function setButtonsPrefix($prefix)
    {
        $this->buttonsPrefix = $prefix;

        return $this;
    }

    public function setButtonsSuffix($suffix)
    {
        $this->buttonsSuffix = $suffix;

        return $this;
    }

    public function getButtonsPrefix()
    {
        return $this->buttonsPrefix . ($this->buttonOptions['prefix'] ?? '');
    }

    public function getButtonsSuffix()
    {
        return $this->buttonsSuffix . ($this->buttonOptions['suffix'] ?? '');
    }

    public function get_where($tablePrefix = '')
    {
        $this->buildQuery([
            'tablePrefix' => $tablePrefix,
        ]);

        return $this->where;
    }

    public function getQuery()
    {
        return $this->where;
    }

    public function getRuleCallbacks()
    {
        return $this->ruleCallbacks;
    }

    public function getTableData($field = null)
    {
        if ($this->tableData === null && $this->isUseCookie()) {
            $this->tableData = (array) json_decode(
                \diRequest::cookie('admin_filter__' . $this->getTable(), '')
            );
        }

        return $field === null ? $this->tableData : $this->tableData[$field] ?? null;
    }

    public function gatherData($field)
    {
        $value = $this->getPredefinedData($field);

        if (!$this->reset) {
            $value = $this->getData($field) ?: $value;

            if ($value && is_scalar($value)) {
                return (string) $value;
            }

            $value = $this->getTableData($field) ?: $value;

            /*
            if (isset($_COOKIE["admin_filter"][$this->table][$field])) {
                $value = is_array($_COOKIE["admin_filter"][$this->table][$field])
                    ? $_COOKIE["admin_filter"][$this->table][$field]
                    : urldecode($_COOKIE["admin_filter"][$this->table][$field]);
            }
            */

            $value = \diRequest::get($field, $value);
            $value = $_GET['admin_filter'][$field] ?? $value;
        }

        return $value;
    }

    public function buildQuery($options = [])
    {
        if (!is_array($options)) {
            $options = [
                'tablePrefix' => $options ?: '',
            ];
        }

        $options = extend(
            [
                'tablePrefix' => '',
            ],
            $options
        );

        // sorter
        if (!$this->reset) {
            $this->setSortBy($this->getTableData(static::PARAM_SORT_FIELD));
            $this->setDir($this->getTableData(static::PARAM_SORT_DIR));
        }

        if ($this->possible_sortby_ar) {
            if (!in_array($this->getSortBy(), $this->possible_sortby_ar)) {
                $this->setSortBy($this->default_sortby);
            }
        }

        if (!in_array($this->getDir(), ['ASC', 'DESC'])) {
            $this->setDir($this->default_dir);
        }

        $where_ar = $this->where_ar;
        $this->ruleCallbacks = [];

        foreach ($this->ar as $idx => $a) {
            $where_tpl = $a['where_tpl'] ?: self::DEFAULT_WHERE_TPL;

            $value = $this->gatherData($a['field']);

            if ($value === null && $a['default_value'] !== null) {
                $value = $a['default_value'];
            }

            if ($value !== null) {
                switch ($a['type']) {
                    case 'int':
                    case 'float':
                    case 'double':
                        if ($value && $value[0] == '!') {
                            $a['not'] = $this->ar[$idx]['not'] = true;
                            $value = substr($value, 1);

                            if ($where_tpl && !is_callable($where_tpl)) {
                                $where_tpl = str_replace('=', '!=', $where_tpl);
                            }
                        }
                        break;
                }

                switch ($a['type']) {
                    case 'int':
                        $value = intval($value);
                        break;

                    case 'float':
                    case 'double':
                        $value = str_replace(',', '.', $value);
                        $value = doubleval($value);
                        break;

                    case 'checkboxes':
                        if (empty($where_tpl)) {
                            $where_tpl = '[-field-] in ([-value-])';
                        }
                        break;

                    case 'date_range':
                    case 'date_str_range':
                        $dMin = null;

                        if ($a['default_value'] === null) {
                            if (
                                self::getDb() instanceof
                                \diCore\Database\Legacy\Mongo
                            ) {
                                $res = self::getDb()->getAggregateValues([
                                    'collectionName' => $this->table,
                                    'field' => $a['field'],
                                    'min' => true,
                                ]);

                                $dMin = $res['min'];
                            } else {
                                $r1 = self::getDb()->r(
                                    self::getDb()->escapeTable($this->table),
                                    '',
                                    "MIN({$a['field']}) as d1_min"
                                );

                                $dMin = $r1->d1_min;
                            }
                        }

                        if ($a['type'] == 'date_str_range' && $dMin) {
                            $dMin = \diDateTime::timestamp($dMin);
                        }

                        $t1 =
                            $a['default_value'] !== null
                                ? $a['default_value']
                                : ($dMin ?:
                                'Y-m-01');
                        // 1st day of current month
                        $t2 =
                            $a['default_value2'] !== null
                                ? $a['default_value2']
                                : '+1 day';
                        // tomorrow

                        $dt1 = \diDateTime::simpleDateFormat($t1);
                        $dt2 = \diDateTime::simpleDateFormat($t2);

                        $value['timestamp1'] = \diDateTime::timestamp(
                            ArrayHelper::get($value, 0, $dt1) . ' 00:00:00'
                        );
                        $value['timestamp2'] = \diDateTime::timestamp(
                            ArrayHelper::get($value, 1, $dt2) . ' 23:59:59'
                        );

                        break;

                    default:
                    case 'str':
                    case 'string':
                        $value = StringHelper::in($value);
                        break;
                }

                $this->ar[$idx]['value'] = $value;

                if ($value || ($value == '0' && substr($a['type'], 0, 3) == 'str')) {
                    $replace_ar = [
                        '[-field-]' => $options['tablePrefix'] . $a['field'],
                        '[-value-]' => $value,
                    ];

                    if (
                        in_array($a['type'], ['date_range', 'date_str_range']) &&
                        $where_tpl == self::DEFAULT_WHERE_TPL
                    ) {
                        if ($a['type'] == 'date_range') {
                            $where_tpl = 'diaf_get_date_range_filter';
                        } elseif ($a['type'] == 'date_str_range') {
                            $where_tpl = 'diaf_get_date_str_range_filter';
                        }
                    }

                    if ($value === self::EMPTY_STRING) {
                        $value = '';
                    }

                    if (
                        $a['rule'] &&
                        ($ruleCallback = FilterRule::callback($a['rule']))
                    ) {
                        $this->ruleCallbacks[] = $ruleCallback([
                            'field' => $a['field'],
                            'value' => $value,
                            'negative' => $a['not'],
                        ]);
                        $w = null;
                    } else {
                        $w = is_callable($where_tpl)
                            ? $where_tpl(
                                $a['field'],
                                $value,
                                $a['not'],
                                $options['tablePrefix'],
                                $a['queryPrefix'],
                                $a['querySuffix']
                            )
                            : str_replace(
                                array_keys($replace_ar),
                                array_values($replace_ar),
                                $where_tpl
                            );
                    }

                    if ($w) {
                        $where_ar[] = $w;
                    }
                }
            }

            $this->setData($a['field'], $value);
        }

        $this->where = $where_ar ? 'WHERE ' . join(" $this->andor ", $where_ar) : '';

        return $this;
    }

    protected function getResetButton($field)
    {
        return "<span data-purpose=\"reset-filter\" data-field=\"$field\"></span>";
    }

    /** @deprecated */
    public function get_input($field)
    {
        return $this->getInput($field);
    }

    public function getInput($field)
    {
        return $this->getInputPrefix($field) .
            $this->getInputBody($field) .
            $this->getInputSuffix($field) .
            $this->getInputResetButton($field);
    }

    protected function getInputBody($field)
    {
        if (isset($this->inputs_ar[$field])) {
            return $this->inputs_ar[$field];
        }

        if ($ar = $this->getFilter($field)) {
            // todo: aliases for same field name
            //$name = $ar['alias'] ?: $field;
            $name = $field;

            $fieldName = 'admin_filter[' . $name . ']';

            if (empty($ar['strict'])) {
                $this->setInputResetButton($field);
            }

            switch ($ar['type']) {
                default:
                    if (
                        !$ar['value'] &&
                        in_array($ar['type'], ['int', 'float', 'double'])
                    ) {
                        $ar['value'] = '';
                    }

                    if ($ar['input_size']) {
                        $size = $ar['input_size'];
                    } else {
                        switch ($ar['type']) {
                            case 'int':
                            case 'float':
                            case 'double':
                                $size = self::DEFAULT_INPUT_SIZE_NUMBER;
                                break;

                            default:
                                $size = self::DEFAULT_INPUT_SIZE_STRING;
                        }
                    }

                    if (isset($ar['feed'])) {
                        $input = \diSelect::fastCreate(
                            $fieldName,
                            $ar['value'],
                            $ar['feed']
                        );

                        if ($ar['submitOnChange']) {
                            $input->setAttr([
                                'data-submit-on-change' => $ar['submitOnChange'],
                            ]);
                        }
                    } else {
                        $attrs = [
                            'id' => $fieldName,
                            'name' => $field,
                            'value' => StringHelper::out($ar['value']),
                            'placeholder' => StringHelper::out($ar['placeholder']),
                            'size' => $size,
                            'type' => 'text',
                        ];

                        if ($ar['submitOnChange']) {
                            $attrs['data-submit-on-change'] = $ar['submitOnChange'];
                        }

                        $input = sprintf(
                            '<input %s>',
                            ArrayHelper::toAttributesString($attrs)
                        );
                    }

                    return $input;

                case 'date_range':
                case 'date_str_range':
                    $sel = [];

                    if (self::getDb() instanceof \diCore\Database\Legacy\Mongo) {
                        $res = self::getDb()->getAggregateValues([
                            'collectionName' => $this->table,
                            'field' => $field,
                            'min' => true,
                            'max' => true,
                        ]);

                        $dMin = $res['min'];
                        $dMax = $res['max'];
                    } else {
                        $r1 = self::getDb()->r(
                            self::getDb()->escapeTable($this->table),
                            '',
                            "MIN($field) as d1_min,MAX($field) as d1_max"
                        );

                        $dMin = $r1->d1_min;
                        $dMax = $r1->d1_max;
                    }

                    foreach (self::$dateRangeAr as $_f) {
                        $_ff = 'd' . $_f[0];
                        $tpl = $_f[0];
                        if ($tpl === 'y') {
                            $tpl = 'Y';
                        }
                        $_idx = $_f[1];
                        $default = $_idx == 1 ? $dMin : $dMax;

                        $sel[$_f] = new \diSelect(
                            "admin_filter[{$field}][{$_idx}][{$_ff}]",
                            \diDateTime::format(
                                $tpl,
                                ArrayHelper::get($ar, ['value', $_idx - 1], $default)
                            )
                        );
                    }

                    for ($i = 1; $i <= 31; $i++) {
                        $sel['d1']->addItem(lead0($i));
                        $sel['d2']->addItem(lead0($i));
                    }

                    for ($i = 1; $i <= 12; $i++) {
                        $sel['m1']->addItem(lead0($i));
                        $sel['m2']->addItem(lead0($i));
                    }

                    if ($ar['type'] == 'date_str_range' && ($dMin || $dMax)) {
                        $dMin = strtotime($dMin ?: '');
                        $dMax = strtotime($dMax ?: '');
                    }

                    $y1 = min(
                        \diDateTime::format('Y') - 1,
                        !empty($ar['value'][0])
                            ? \diDateTime::format('Y', $ar['value'][0])
                            : 50000,
                        $dMin ? \diDateTime::format('Y', $dMin) : 50000
                    );
                    $y2 = max(
                        \diDateTime::format('Y') + 3,
                        !empty($ar['value'][1])
                            ? \diDateTime::format('Y', $ar['value'][1])
                            : 0,
                        $dMax ? \diDateTime::format('Y', $dMax) : 0
                    );

                    for ($i = $y1; $i <= $y2; $i++) {
                        $sel['y1']->addItem($i);
                        $sel['y2']->addItem($i);
                    }

                    $wrapSelects = function ($idx) use ($sel, $field, $ar) {
                        $glue = Config::basicCreate()::getDateRangeFilterGlue();
                        $dtSelects = join($glue, [
                            $sel['d' . $idx],
                            $sel['m' . $idx],
                            $sel['y' . $idx],
                        ]);
                        $set = !empty($ar['value'][$idx - 1]);
                        $setClass = $set ? ' set' : '';
                        $emptyContent = Config::basicCreate()::getDateRangeFilterEmptyContent(
                            $idx
                        );

                        return <<<EOF
<span class="admin-filter-date-wrapper{$setClass}" data-field="$field" data-idx="$idx">
    <span class="empty-dates">$emptyContent</span>
    <span class="reset-filter"></span>
    <span class="selects">$dtSelects</span>
</span>
EOF;
                    };

                    $s =
                        $wrapSelects(1) .
                        Config::basicCreate()::getDateRangeFilterSeparator() .
                        $wrapSelects(2);

                    // js
                    $uid = get_unique_id(8);

                    $calendar_cfg_js = "months_to_show: 2, date1: 'admin_filter[{$field}][1]', date2: 'admin_filter[{$field}][2]', able_to_go_to_past: true, language: '{$this->language}', position_base: 'parent', flex: true";
                    $onClickPrefix = "diAF.setDateEntered('$field',1,true);diAF.setDateEntered('$field',2,true);";

                    $s .=
                        " <button type=button onclick=\"{$onClickPrefix}c_{$uid}.toggle();\" class=\"calendar-toggle\">{$this->L(
                            'calendar'
                        )}</button>" .
                        "<script type=\"text/javascript\">var c_{$uid} = new diCalendar({instance_name: 'c_{$uid}', $calendar_cfg_js});</script>";

                    return $s;
            }
        }

        return null;
    }

    public function set_input_params($field, $params_ar = [])
    {
        if (!isset($this->input_params_ar[$field])) {
            $this->input_params_ar[$field] = [];
        }

        $this->input_params_ar[$field] = array_merge(
            $this->input_params_ar[$field],
            $params_ar
        );

        return $this;
    }

    public function get_idx_by_field($field)
    {
        foreach ($this->ar as $idx => $ar) {
            if ($ar['field'] == $field) {
                return $idx;
            }
        }

        return null;
    }

    /** @deprecated */
    public function set_select_from_db_input(
        $field,
        $db_rs,
        $template_text = '%title%',
        $template_value = '%id%',
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        return $this->setSelectFromDbInput(
            $field,
            $db_rs,
            $template_text,
            $template_value,
            $prefix_ar,
            $suffix_ar
        );
    }

    public function setSelectFromDbInput(
        $field,
        $db_rs,
        $template_text = '%title%',
        $template_value = '%id%',
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        if (is_array($template_text)) {
            $prefix_ar = $template_text;
            $template_text = '%title%';
        }

        if (is_array($template_value)) {
            $suffix_ar = $template_value;
            $template_value = '%id%';
        }

        $sel = new \diSelect("admin_filter[$field]", $this->getData($field));

        if (isset($this->input_params_ar[$field])) {
            $sel->setAttr($this->input_params_ar[$field]);
        }

        if ($prefix_ar) {
            $sel->addItemArray($prefix_ar);
        }

        while ($db_rs && ($db_r = self::getDb()->fetch_array($db_rs))) {
            $ar1 = [];
            $ar2 = [];

            foreach ($db_r as $k => $v) {
                $ar1[] = "%$k%";
                $ar2[] = $v;
            }

            $text = str_replace($ar1, $ar2, $template_text);
            $value = str_replace($ar1, $ar2, $template_value);

            $sel->addItem($value, $text);
        }

        if ($suffix_ar) {
            $sel->addItemArray($suffix_ar);
        }

        $this->setInput($field, $sel)->setInputResetButton($field);

        $this->values_ar[$field] = $sel->getSimpleItemsAr();

        return $this;
    }

    /**
     * @param string $field
     * @param \diCollection|array $collection
     * @param array|callable $format
     * @param array $prefixAr
     * @param array $suffixAr
     * @return $this
     */
    public function setSelectFromCollectionInput(
        $field,
        $collection,
        $format = null,
        $prefixAr = [],
        $suffixAr = []
    ) {
        if ($format === null || (is_array($format) && !is_callable($format))) {
            if (is_array($format)) {
                $suffixAr = $prefixAr;
                $prefixAr = $format;
            }

            $format = null;
        }

        $sel = new \diSelect("admin_filter[$field]", $this->getData($field));

        if (isset($this->input_params_ar[$field])) {
            $sel->setAttr($this->input_params_ar[$field]);
        }

        if ($prefixAr) {
            $sel->addItemArray($prefixAr);
        }

        $sel->addItemsCollection($collection, $format);

        if ($suffixAr) {
            $sel->addItemArray($suffixAr);
        }

        $this->setInput($field, $sel)->setInputResetButton($field);

        return $this;
    }

    protected function getValueForField($field)
    {
        switch ($field) {
            case static::PARAM_SORT_FIELD:
                return $this->getSortBy();

            case static::PARAM_SORT_DIR:
                return $this->getDir();

            default:
                return $this->getData($field);
        }
    }

    /** @deprecated */
    public function set_select_from_array_input(
        $field,
        $ar,
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        return $this->setSelectFromArrayInput($field, $ar, $prefix_ar, $suffix_ar);
    }

    public function setSelectFromArrayInput(
        $field,
        $ar,
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        $sel = new \diSelect(
            "admin_filter[$field]",
            $this->getValueForField($field)
        );

        if (isset($this->input_params_ar[$field])) {
            foreach ($this->input_params_ar[$field] as $_pn => $_pv) {
                $sel->setAttr($_pn, $_pv);
            }
        }

        if ($prefix_ar) {
            $sel->addItemArray($prefix_ar);
        }

        $sel->addItemArray($ar);

        if ($suffix_ar) {
            $sel->addItemArray($suffix_ar);
        }

        $x = $this->get_idx_by_field($field);
        if ($x !== null && $this->ar[$x]['not']) {
            foreach ($sel->getItemsAr() as $_k => $_v) {
                if ($_v['value'] == $this->getData($field)) {
                    $sel->addItem("!$_k", "НЕ {$_v['text']}");
                    $sel->setCurrentValue("!$_k");

                    break;
                }
            }
        }

        $resetNeeded = !in_array($field, [
            static::PARAM_SORT_FIELD,
            static::PARAM_SORT_DIR,
        ]);

        $this->setInput($field, $sel)->setInputResetButton($field, $resetNeeded);

        $this->values_ar[$field] = $sel->getSimpleItemsAr();

        return $this;
    }

    /** @deprecated */
    public function set_select_from_array2_input($field, $ar)
    {
        return $this->setSelectFromArray2Input($field, $ar);
    }

    public function setSelectFromArray2Input(
        $field,
        $ar,
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        $sel = new \diSelect("admin_filter[$field]", $this->getData($field));

        if (isset($this->input_params_ar[$field])) {
            foreach ($this->input_params_ar[$field] as $_pn => $_pv) {
                $sel->setAttr($_pn, $_pv);
            }
        }

        if ($prefix_ar) {
            $sel->addItemArray($prefix_ar);
        }

        $sel->addItemArray2($ar);

        if ($suffix_ar) {
            $sel->addItemArray($suffix_ar);
        }

        $this->setInput($field, $sel)->setInputResetButton($field);

        $this->values_ar[$field] = $sel->getSimpleItemsAr();

        return $this;
    }

    /** @deprecated */
    public function set_checkbox_from_array_input($field, $ar, $columns = 1)
    {
        return $this->setCheckboxFromArrayInput($field, $ar, $columns);
    }

    public function setCheckboxFromArrayInput($field, $ar, $columns = 1)
    {
        $ar2 = [];

        foreach ($ar as $k => $v) {
            $checked =
                strpos(",{$this->getData($field)},", ",$k,") !== false
                    ? " checked=\"checked\""
                    : '';

            if (false && $this->static_mode) {
                if ($checked) {
                    $ar2[] = $v;
                }
            } else {
                $ar[
                    $k
                ] = "<input type='checkbox' id='diaf_{$field}[$k]' name='{$field}[]' value='$k'$checked onclick=\"diadminfilter_toggle_cb('$field',0);\" /> <label for='diaf_{$field}[$k]' id='diaf_label_{$field}[$k]'>$v</label>";
            }
        }

        if (false && $this->static_mode) {
            $table = join(', ', $ar2);
        } else {
            $table = '<table><tr>';

            $per_column = ceil(count($ar) / $columns);

            for ($i = 0; $i < $columns; $i++) {
                $table .=
                    "<td style=\"padding-right: 20px; vertical-align: top;\">" .
                    join('<br />', array_slice($ar, $per_column * $i, $per_column)) .
                    '</td>';
            }

            $table .= '</tr></table>';
        }
        //

        $this->setInput(
            $field,
            "<input type=hidden id='admin_filter[$field]' value='{$this->getData(
                $field
            )}'>" . $table
        );

        return $this;
    }

    public function set_checkbox_from_db_input(
        $field,
        $db_rs,
        $template_text = '%title%',
        $template_value = '%id%',
        $cols_count = 1,
        $prefix_ar = [],
        $suffix_ar = [],
        $suffix_buttons_ar = []
    ) {
        return $this->setCheckboxFromDbInput(
            $field,
            $db_rs,
            $template_text,
            $template_value,
            $cols_count,
            $prefix_ar,
            $suffix_ar,
            $suffix_buttons_ar
        );
    }

    public function setCheckboxFromDbInput(
        $field,
        $db_rs,
        $template_text = '%title%',
        $template_value = '%id%',
        $cols_count = 1,
        $prefix_ar = [],
        $suffix_ar = [],
        $suffix_buttons_ar = []
    ) {
        $ar = [];
        $static_ar = [];

        foreach ($prefix_ar as $value => $text) {
            $class = ' cb_level0';
            $checked =
                strpos(",{$this->getData($field)},", ",$value,") !== false
                    ? " checked=\"checked\""
                    : '';

            $inp = "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />";
            $ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";

            if ($checked) {
                $static_ar[] = $text;
            }
        }

        while ($db_rs && ($db_r = self::getDb()->fetch_array($db_rs))) {
            $ar1 = [];
            $ar2 = [];

            foreach ($db_r as $k => $v) {
                $ar1[] = "%$k%";
                $ar2[] = $v;
            }

            $text = str_replace($ar1, $ar2, $template_text);
            $value = str_replace($ar1, $ar2, $template_value);

            $class = isset($db_r['level_num'])
                ? " cb_level{$db_r['level_num']}"
                : '';
            $checked =
                strpos(",{$this->getData($field)},", ",$value,") !== false
                    ? " checked=\"checked\""
                    : '';

            if ($checked) {
                $static_ar[] = $text;
            }

            $inp =
                !isset($db_r['level_num']) || $db_r['level_num'] > 0
                    ? "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />"
                    : '';
            $ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";
        }

        foreach ($suffix_ar as $value => $text) {
            $class = ' cb_level0';
            $checked =
                strpos(",{$this->getData($field)},", ",$value,") !== false
                    ? " checked=\"checked\""
                    : '';

            $inp = "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />";
            $ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";

            if ($checked) {
                $static_ar[] = $text;
            }
        }

        $tds_ar = [];
        $per_col = ceil(count($ar) / $cols_count);
        for ($i = 0; $i < $cols_count; $i++) {
            $tds_ar[] =
                '<td valign=top>' .
                join("\n", array_slice($ar, $i * $per_col, $per_col)) .
                '</td>';
        }

        $inputs = '<table><tr>' . join("\n", $tds_ar) . '</tr></table>';
        $static_inputs = $static_ar ? join(', ', $static_ar) : 'Нет (выбрать)';

        //this.style.display='none';
        //_ge('static_cb[$field]').style.display='block';

        $buttons_suffix = join('', $suffix_buttons_ar);

        $this->setInput(
            $field,
            "<input type=hidden id='admin_filter[$field]' value='{$this->getData(
                $field
            )}'>" .
                "<div onclick=\"diadminfilter_toggle_cb('$field',1);\" id=\"static_cb[$field]\" style=\"cursor:pointer;\">$static_inputs</div>" .
                "<div id=\"cb[$field]\" style=\"display: none; position: absolute; border: 1px solid #777; padding: 5px; background: #fff;\">" .
                "$inputs" .
                "<button type=button onclick=\"diadminfilter_toggle_cb('$field',0);\">ОК</button>" .
                "<button type=button onclick=\"diadminfilter_close_box('$field');\">Отмена</button>" .
                "<button type=button onclick=\"diadminfilter_select_all_cb('$field',1);\">Выделить все</button>" .
                "<button type=button onclick=\"diadminfilter_select_all_cb('$field',0);\">Снять выделение</button>" .
                $buttons_suffix .
                '</div>'
        );

        return $this;
    }

    public function L($token = null, $language = null)
    {
        $language = $language ?: $this->language;

        return $token
            ? static::$lngStrings[$language][$token] ?? $token
            : static::$lngStrings[$language];
    }

    function convert_from_and_to_dates()
    {
        $x = strpos($this->where, '(date BETWEEN');
        if ($x !== false) {
            $y = strpos($this->where, ')', $x + 1);

            if ($y !== false) {
                $s = substr($this->where, $x, $y - $x + 1);

                $this->where =
                    substr($this->where, 0, $x) .
                    '(' .
                    str_replace('(date ', '(from_date ', $s) .
                    ' or ' .
                    str_replace('(date ', '(to_date ', $s) .
                    ')' .
                    substr($this->where, $y + 1);
            }
        }

        return $this;
    }

    function get_static_input($field)
    {
        if (isset($this->static_inputs_ar[$field])) {
            return $this->static_inputs_ar[$field];
        }

        foreach ($this->ar as $idx => $ar) {
            if ($ar['field'] == $field) {
                switch ($ar['type']) {
                    case 'date_range':
                    case 'date_str_range':
                        return date(
                            Configuration::get('date_format'),
                            $ar['value']['timestamp1']
                        ) .
                            ' to ' .
                            date(
                                Configuration::get('date_format'),
                                $ar['value']['timestamp2']
                            );

                    default:
                        if (
                            !$ar['value'] &&
                            in_array($ar['type'], explode(',', 'int,float,double'))
                        ) {
                            $ar['value'] = '';
                        }

                        if (
                            $this->values_ar[$field] &&
                            isset($this->values_ar[$field][$ar['value']])
                        ) {
                            return $this->values_ar[$field][$ar['value']];
                        } elseif (!$ar['value'] && $ar['value'] !== 0) {
                            return 'No value';
                        } else {
                            return $ar['value'];
                        }
                }
            }
        }

        return '';
    }

    function get_static_form($glue = ' ', $skip_fields_with_empty_title = true)
    {
        $ar2 = [];

        foreach ($this->ar as $idx => $ar) {
            if (empty($ar['title'])) {
                if ($skip_fields_with_empty_title) {
                    continue;
                } else {
                    $ar['title'] = $ar['field'];
                }
            }

            $ar2[] = "{$ar['title']}: " . $this->get_static_input($ar['field']);
        }

        return join($glue, $ar2);
    }
}

function diaf_get_date_range_filter($field, $value, $not = false, $table_prefix = '')
{
    global $db;

    $date1 = $value['timestamp1'] ?? null;
    $date2 = $value['timestamp2'] ?? null;

    $op = $db->getBetweenOperator($date1, $date2);

    return $op ? "({$table_prefix}{$field} {$op})" : '';
}

function diaf_get_date_str_range_filter(
    $field,
    $value,
    $not = false,
    $table_prefix = '',
    $queryPrefix = '',
    $querySuffix = ''
) {
    global $db;

    $date1 = isset($value[0]) ? $value[0] . ' 00:00:00' : null;
    $date2 = isset($value[1]) ? $value[1] . ' 23:59:59' : null;

    $op = $db->getBetweenOperator($date1, $date2);

    return $op
        ? $queryPrefix . "({$table_prefix}{$field} {$op})" . $querySuffix
        : '';
}

function diaf_minus_one($field, $value, $not = false, $table_prefix = '')
{
    $not_str = $not ? '!' : '';

    if ($value == -1) {
        return "{$table_prefix}{$field}{$not_str}='0'";
    } else {
        return "{$table_prefix}{$field}{$not_str}='$value'";
    }
}

function diaf_minus_one2($field, $value, $not = false, $table_prefix = '')
{
    $not_str = $not ? '!' : '';

    if ($value == -1) {
        return "{$table_prefix}{$field}{$not_str}='0'";
    } else {
        return "{$table_prefix}{$field}";
    }
}

function diaf_minus_one_hundred($field, $value, $not = false, $table_prefix = '')
{
    $not_str = $not ? '!' : '';

    if ($value == -100) {
        return "{$table_prefix}{$field}{$not_str}='0'";
    } else {
        return "{$table_prefix}{$field}{$not_str}='$value'";
    }
}

function diaf_from_to($field, $value, $not = false, $table_prefix = '')
{
    return "(INSTR(sender,'$value')>'0' OR INSTR(recipient,'$value')>'0')";
}

/** @deprecated  */
function diaf_like($field, $value, $not = false, $table_prefix = '')
{
    return diaf_substr($field, $value, $not, $table_prefix);
}

/** @deprecated  */
function diaf_substr($field, $value, $not = false, $table_prefix = '')
{
    return $value
        ? "INSTR({$table_prefix}{$field}, '$value') > '0'"
        : "{$table_prefix}{$field} = '$value'";
}

/** @deprecated  */
function diaf_first_last_name($field, $value, $not = false, $table_prefix = '')
{
    return "(INSTR(first_name,'$value')>'0' OR INSTR(last_name,'$value')>'0')";
}

function diaf_empty($field, $value, $not = false, $table_prefix = '')
{
    return '';
}

function diaf_get_subcategories_ids($field, $value, $not = false, $table_prefix = '')
{
    $cs = new \diHierarchyCategoriesTable();
    $ar = $cs->getChildrenIdsAr($value, [$value]);

    return "{$table_prefix}{$field} in ('" . join("','", $ar) . "')";
}

function diaf_le($field, $value, $not = false, $table_prefix = '')
{
    return "{$table_prefix}{$field}<='$value'";
}

function diaf_ge($field, $value, $not = false, $table_prefix = '')
{
    return "{$table_prefix}{$field}>='$value'";
}

function diaf_bin_ip($field, $value, $not = false, $table_prefix = '')
{
    return "{$table_prefix}{$field}='" . ip2bin($value) . "'";
}

/** @deprecated  */
function diaf_host($field, $value, $not = false, $table_prefix = '')
{
    return "(INSTR({$table_prefix}{$field},'$value')>'0' or INSTR({$table_prefix}{$field},'www.$value')>'0')";
}

/** @deprecated  */
function diaf_checkboxes($field, $value, $not = false, $table_prefix = '')
{
    $ar = explode(',', $value);
    $ar2 = [];

    foreach ($ar as $x) {
        $ar2[] = "INSTR(CONCAT(',',{$table_prefix}{$field},','),',$x,')>0";
    }

    return '(' . join(' or ', $ar2) . ')';
}

/** @deprecated  */
function diaf_checkboxes2($field, $value, $not = false, $table_prefix = '')
{
    $ar = explode(',', $value);
    $ar2 = [];

    foreach ($ar as $x) {
        if ($x == -1) {
            $ar2[] = "{$table_prefix}{$field}=''";
        } else {
            $ar2[] = "INSTR(CONCAT(',',{$table_prefix}{$field},','),',$x,')>0";
        }
    }

    return '(' . join(' or ', $ar2) . ')';
}

function diaf_tags($field, $value, $not = false, $table_prefix = '')
{
    global $F;

    $w_suffix =
        $value == -1
            ? " OR id NOT IN (SELECT target_id FROM tag_links WHERE type='$F->table')"
            : '';

    return "(id IN (SELECT target_id FROM tag_links WHERE tag_id IN ($value) AND type='$F->table')$w_suffix)";
}

function diaf_checkboxes_minus_one($field, $value, $not = false, $table_prefix = '')
{
    $ar = explode(',', $value);
    foreach ($ar as $k => $v) {
        if ($ar[$k] == -1) {
            $ar[$k] = 0;
        }
    }

    $value = join(',', $ar);

    return $value ? "{$table_prefix}{$field} in ($value)" : '1=0';
}

function diaf_checkboxes_past_present_future(
    $field,
    $value,
    $not = false,
    $table_prefix = ''
) {
    $ar = explode(',', $value);
    $ar2 = [];

    $t = time();

    if (in_array('past', $ar)) {
        $ar2[] = "{$table_prefix}{$field}2<'$t'";
    }
    if (in_array('present', $ar)) {
        $ar2[] = "({$table_prefix}{$field}1<='$t' and {$table_prefix}{$field}2>='$t')";
    }
    if (in_array('future', $ar)) {
        $ar2[] = "{$table_prefix}{$field}1>'$t'";
    }

    return '(' . join(' or ', $ar2) . ')';
}

function diaf_several_ints($field, $value, $not = false, $table_prefix = '')
{
    $ar = preg_split("/[\x20,;\.\t\s]+/", $value);
    foreach ($ar as $k => $v) {
        $ar[$k] = $v * 1;
    }

    $value = join(',', $ar);

    return $value ? "{$table_prefix}{$field} in ($value)" : ''; //1=0
}

function diaf_several_ints_or_clean_titles(
    $field,
    $value,
    $not = false,
    $table_prefix = ''
) {
    $ar = preg_split("/[\x20,;\.\t\s]+/", $value);
    foreach ($ar as $k => $v) {
        $ar[$k] = "'$v'";
    } //*1

    $value = join(',', $ar);

    return $value
        ? "({$table_prefix}{$field} in ($value) or clean_title in ($value))"
        : '1=0';
}

function diaf_price2($field, $value, $not = false, $table_prefix = '')
{
    if ($value == 1) {
        return "(price2!='0' and '" .
            time() .
            "' BETWEEN action_date1 and action_date2)";
    } elseif ($value == 2) {
        return "(price2='0' or '" .
            time() .
            "'<action_date1 or '" .
            time() .
            "'>action_date2)";
    }
    return '';
}
