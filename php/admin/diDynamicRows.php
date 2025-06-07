<?php
/*
    // dimaninc

    // todo:
       allow hidden/static fields
       try to make changing of position (order_num) live, without reloading of page

    // 2014/06/19
        * field names flexibility added

    // 2011/06/26
        * pics/file fields
        * order_num added automatically as a hidden field of not entered in tables.php

    // 2011/06/25
        * ::scripts array added to store the js of each field of a dynamic row
        * added js calendar to date inputs

    // 2011/05/25
        * birthday
*/

use diCore\Admin\Form;
use diCore\Admin\Submit;
use diCore\Data\Config;
use diCore\Helper\ArrayHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;
use diCore\Tool\Logger;

class diDynamicRows
{
    /** @var \diCore\Admin\BasePage */
    private $AdminPage;

    /** @var diDB */
    private $db;

    /** @var diModel */
    private $storedModel;

    /** @var callable[] */
    private $afterSaveEachCallbacks;

    /**
     * @deprecated
     * @var object
     */
    public $test_r;

    const MULTIPLE_UPLOAD_FIELD_NAME = '__new_files__';
    const MULTIPLE_UPLOAD_FIRST_ID = -10000;
    private $defaultMultiplePicField = null;

    const NEW_ID_STRING = '%NEWID%';

    public $table, $id, $field;
    protected $safeField;
    public $static_mode;
    public $inputs, $scripts, $data, $inputs_params;
    public $language = 'ru';
    public $input_objects = [];
    public $current_id;
    public $current_field;
    public $sortby = '';
    public $info_ar;
    public $abs_path;
    public $data_table;
    public $data_id;
    public $js_var_name;

    /*
     * Is lite mode (like string[] or int[] for a single multi-field)
     */
    protected $isLite = false;

    /*
     * JSON data to update main field in the end before db-save process
     */
    protected $jsonData = [];

    private $uploaded_images = [];
    private $uploaded_files = [];
    private $uploaded_images_w = [];
    private $checked_static_ar = [];

    protected $subquery;
    protected $dicontrols_code_needed = false;
    protected $max_feed_count_to_show_static_checkboxes = 20;

    private $options = [
        'en' => [
            'addRowCaption' => 'Click to add',
            'addRowText' => 'Add',
            'multipleUpload' => 'Select several files',
            'dragAndDropUpload' => '(drag and drop is allowed)',
        ],

        'ru' => [
            'addRowCaption' => 'Добавить ещё',
            'addRowText' => 'Добавить',
            'multipleUpload' => 'Выбрать несколько файлов',
            'dragAndDropUpload' => '(можно перетащить мышкой)',
        ],
    ];

    public function __construct($AdminPage, $field, $oldField = null)
    {
        if (gettype($AdminPage) == 'object') {
            $this->AdminPage = $AdminPage;
            $this->table = $this->AdminPage->getTable();
            $this->id = $this->AdminPage->getId();
            $this->field = $field;
            $this->language = $this->AdminPage->getLanguage();

            $this->info_ar = $this->AdminPage->getAllFields();
        } else {
            $this->table = $AdminPage;
            $this->id = $field;
            $this->field = $oldField;

            $_all_fields = $this->table . '_all_fields';
            global $$_all_fields;
            $this->info_ar = $$_all_fields;
        }

        $this->enrichInfo();

        $this->db = \diModel::createForTable($this->table)
            ::getConnection()
            ->getDb();
        $this->static_mode = false;
        $this->abs_path = Config::getPublicFolder();
        // stored as json if no data table
        $this->data_table = $this->info_ar[$this->field]['table'] ?? null;
        $this->storedModel = \diModel::createForTableNoStrict($this->getDataTable());

        $this->validateInfo();

        if (!empty($this->info_ar[$this->field]['options'])) {
            $this->setOption($this->info_ar[$this->field]['options']);
        }

        $this->subquery = $this->info_ar[$this->field]['subquery'](
            $this->table,
            $this->field,
            $this->id,
            $this
        );

        if (!empty($this->info_ar[$this->field]['sortby'])) {
            $this->sortby = $this->info_ar[$this->field]['sortby'];
        }

        $this->safeField = $this->formatName($this->field);
        $this->js_var_name = "di_{$this->table}_$this->safeField";
        $this->inputs = [];
        $this->data = [];
        $this->inputs_params = [];
    }

    protected function enrichInfo()
    {
        switch ($this->info_ar[$this->field]['type']) {
            case 'int[]':
            case 'string[]':
                $this->isLite = true;

                if (
                    empty($this->info_ar[$this->field]['fields']) &&
                    empty($this->info_ar[$this->field]['template'])
                ) {
                    $this->info_ar[$this->field]['fields'] = [
                        'value' => substr(
                            $this->info_ar[$this->field]['type'],
                            0,
                            -2
                        ),
                    ];

                    $this->info_ar[$this->field]['template'] =
                        '<ul class="line">' .
                        '<li class="full-width">{VALUE}</li>' .
                        '</ul>';
                }
                break;
        }

        if (empty($this->info_ar[$this->field]['subquery'])) {
            $this->info_ar[$this->field]['subquery'] = fn(
                $table,
                $field,
                $id,
                \diDynamicRows|null $DR = null
            ) => "_table = '$table' AND _field = '$field' AND _id = '$id'";
        }

        return $this;
    }

    protected function validateInfo()
    {
        $fieldsToCheck = ['template', 'fields'];
        if ($this->getDataTable() !== null) {
            $fieldsToCheck[] = 'table';
        }

        foreach ($fieldsToCheck as $f) {
            if (empty($this->info_ar[$this->field][$f])) {
                throw new Exception(
                    "You should define the '$f' attribute for '$this->field' field in '$this->table' Form Fields"
                );
            }
        }

        return $this;
    }

    public function isLite()
    {
        return $this->isLite;
    }

    /**
     * @return \diCore\Admin\BasePage
     */
    public function getAdminPage()
    {
        return $this->AdminPage;
    }

    public function getStoredId()
    {
        return $this->getStoredModel()->getId();
    }

    /**
     * @return diModel
     */
    public function getStoredModel()
    {
        return $this->storedModel;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getField()
    {
        return $this->field;
    }

    public function getParentId()
    {
        return $this->id;
    }

    public function doesParentExist()
    {
        return !!$this->id;
    }

    protected function L($token)
    {
        return $this->AdminPage->getForm()->L($token);
    }

    private function getDb()
    {
        return $this->db;
    }

    public function getOption($option)
    {
        return $this->options[$this->language][$option] ?? null;
    }

    public function setOption($option, $value = null)
    {
        if ($value === null && is_array($option)) {
            $this->options[$this->language] = extend(
                $this->options[$this->language],
                $option
            );
        } else {
            $this->options[$this->language][$option] = $value;
        }

        return $this;
    }

    public function getDataTable()
    {
        return $this->data_table;
    }

    public function getCurrentModel()
    {
        return \diModel::createForTableNoStrict(
            $this->getDataTable(),
            $this->getAllData()
        );
    }

    public function getAllData()
    {
        return $this->data;
    }

    public static function extractDataSafe(\diModel|null $m, $field)
    {
        if (!$m) {
            return null;
        }

        [$masterField, $subField] = Submit::getFieldNamePair($field ?? '');

        // part of complex json field
        if ($subField && $m->hasJsonData($masterField, $subField)) {
            return $m->getJsonData($masterField, $subField);
        }

        return $m->get($field);
    }

    public function getData($field)
    {
        [$masterField, $subField] = Submit::getFieldNamePair($field ?? '');

        // part of complex json field
        if ($subField) {
            if (isset($this->jsonData[$masterField][$subField])) {
                return $this->jsonData[$masterField][$subField];
            }

            return $this->getStoredModel()->getJsonData($masterField, $subField);
        }

        return isset($this->data[$field]) ? $this->data[$field] : null;
    }

    public function setDataAr($ar)
    {
        foreach ($ar as $k => $v) {
            $this->setData($k, $v);
        }

        return $this;
    }

    public function setData($field, $value)
    {
        [$masterField, $subField] = Submit::getFieldNamePair($field);

        // part of complex json field
        if ($subField) {
            $this->jsonData[$masterField][$subField] = $value;

            return $this;
        }

        $this->getStoredModel()->set($field, $value);
        $this->data[$field] = $value;

        return $this;
    }

    protected function formatName($field)
    {
        return Submit::formatName($field);
    }

    public function setJsonData()
    {
        if (!$this->jsonData) {
            return $this;
        }

        foreach ($this->jsonData as $masterField => $data) {
            foreach ($data as $subField => $value) {
                $this->getStoredModel()->updateJsonData(
                    $masterField,
                    $subField,
                    $value
                );
            }
        }

        return $this;
    }

    function set_static_mode($static_mode)
    {
        $this->static_mode = $static_mode;
    }

    function is_flag($field, $flag)
    {
        if (!isset($this->info_ar['fields'][$field]['flags'])) {
            return false;
        }

        $fieldFlags = $this->info_ar['fields'][$field]['flags'];

        if (is_array($fieldFlags)) {
            return in_array($flag, $fieldFlags);
        }

        return $flag == $fieldFlags;
    }

    public function getOrderBy()
    {
        return $this->sortby ? "ORDER BY $this->sortby" : '';
    }

    protected function getExistingRecs()
    {
        $recs = [];

        if ($this->isLite) {
            $rows =
                $this->getAdminPage()
                    ->getForm()
                    ->getModel()
                    ->extGet($this->field) ?:
                [];

            $recs = array_map(
                fn($v, $i) => (new \diModel())->set('value', $v)->setId(-1 * $i - 1),
                $rows,
                array_keys($rows)
            );

            return $recs;
        }

        $rs =
            $this->getDataTable() && $this->doesParentExist()
                ? $this->getDb()->rs(
                    $this->getDataTable(),
                    "WHERE $this->subquery {$this->getOrderBy()}"
                )
                : null;

        while ($r = $this->getDb()->fetch($rs)) {
            $recs[] = (new \diModel($r, '#any'))->_setReadOnly(true);
        }

        return $recs;
    }

    public function getHtml()
    {
        $eventNames = [
            'afterInit',
            'beforeAddRow',
            'afterAddRow',
            'beforeDelRow',
            'afterDelRow',
        ];

        $addBottomRowInsideWrapper = $this->getOption('addBottomRowInsideWrapper');
        $direction = $this->info_ar[$this->field]['direction'] ?? 1;
        $s = '';
        $edgeOrderNumber = 0;

        if (!isset($this->info_ar[$this->field]['after_rows'])) {
            $this->info_ar[$this->field]['after_rows'] = '';
        }

        $recs = $this->getExistingRecs();

        if (!$this->static_mode && !$this->isLite && count($recs)) {
            $s .= $this->getAdvancedUploadingArea();
            $s .= $this->getAddRowButtonHtml('before');
        }

        $addBottomRowInsideWrapperAttr = $addBottomRowInsideWrapper
            ? ' data-add-bottom-row-inside-wrapper="true"'
            : '';

        $s .= "<div data-purpose=\"anchor\" data-field=\"$this->safeField\" data-position=\"top\"></div>";
        $s .= "<div class=\"dynamic-wrapper\"$addBottomRowInsideWrapperAttr>";

        /** @var \diModel $m */
        foreach ($recs as $m) {
            $this->data_id = $m->getId();

            $s .= $this->getRowHtml($m);

            $x = $m->get('order_num') ?? ($m->get('idx') ?? $m->getId());

            if (
                ($direction > 0 && $x > $edgeOrderNumber) ||
                ($direction < 0 && $x < $edgeOrderNumber)
            ) {
                $edgeOrderNumber = $x;
            }
        }

        if ($addBottomRowInsideWrapper) {
            $s .= $this->getAddRowButtonHtml('after');
        }

        $s .= '</div>';

        $jsOpts = [
            'field' => $this->safeField,
            'fieldTitle' => 'запись',
            'counter' => $edgeOrderNumber,
            'direction' => $direction,
            'language' => $this->language,
            'sortable' => !empty($this->info_ar[$this->field]['sortable']),
        ];

        foreach ($eventNames as $eventName) {
            if (!empty($this->info_ar[$this->field]['jsEvents'][$eventName])) {
                $jsOpts[$eventName] =
                    $this->info_ar[$this->field]['jsEvents'][$eventName];
            }
        }

        $s .= "<div data-purpose=\"anchor\" data-field=\"$this->safeField\" data-position=\"bottom\"></div>";
        $s .= "<script id=\"js_{$this->safeField}_resource\" type=\"text/template\">{$this->getRowHtml()}</script>";
        $s .=
            "<div id=\"js_{$this->safeField}_js_resource\" style=\"display: none;\">" .
            join("\n", $this->scripts) .
            '</div>';

        $optsStr = json_encode($jsOpts);
        $s .= "<script>var $this->js_var_name = new diDynamicRows($optsStr)</script>";

        if (!$this->static_mode) {
            if (!$addBottomRowInsideWrapper) {
                $s .= $this->getAddRowButtonHtml('after');
            }

            $s .= $this->getAdvancedUploadingArea();
        }

        if ($this->dicontrols_code_needed) {
            $s .=
                "<script>$(function() { $('[id^=\"dicontrol-\"]').diReplaceControls(); });</script>";
        }

        $s .= $this->info_ar[$this->field]['after_rows'];

        return $s;
    }

    public function getAdvancedUploadingArea()
    {
        $drag = $this->getAdminPage()
            ->getForm()
            ->getFieldProperty($this->getField(), 'drag_and_drop_uploading');
        $multiple = $this->getAdminPage()
            ->getForm()
            ->getFieldProperty($this->getField(), 'multiple_uploading');

        if (!$drag && !$multiple) {
            return '';
        }

        $attrs = [
            'data-multiple-uploads' => $multiple ? 'true' : '',
            'data-drag-and-drop-uploads' => $drag ? 'true' : '',
        ];

        $texts = [$this->getOption('multipleUpload')];

        if ($drag) {
            $texts[] = $this->getOption('dragAndDropUpload');
        }

        return sprintf(
            '<div class="admin-form-uploading-area"%1$s>%2$s</div>',
            ' ' . ArrayHelper::toAttributesString($attrs),
            join(' ', $texts)
        );
    }

    public function getAddRowButtonHtml($position)
    {
        $onClick =
            $this->getOption('addRowOnClick') ?:
            "return $this->js_var_name.add('$this->safeField');";
        $caption = $this->getOption('addRowCaption');
        $innerHtml = $this->getOption('addRowText');
        $cssClass = $this->getOption('addRowCssClass') ?: 'simple-button';

        if ($this->isLite) {
            $cssClass .= ' dynamic-add--lite';
        }

        if (is_callable($onClick)) {
            $onClick = $onClick($this);
        }

        return "<div class=\"dynamic-add $cssClass\" data-position=\"$position\" data-caption=\"$caption\" onclick=\"$onClick\">$innerHtml</div>\n";
    }

    function get_row_type($subfield)
    {
        $v = $this->info_ar[$this->field]['fields'][$subfield];

        if (!is_array($v)) {
            $v = ['type' => $v];
        }

        return $v['type'];
    }

    public function isFlag($fieldOrFlagsAr, $flag)
    {
        if (
            is_string($fieldOrFlagsAr) &&
            isset($this->info_ar[$this->field]['fields'][$fieldOrFlagsAr]['flags'])
        ) {
            $flags_ar =
                $this->info_ar[$this->field]['fields'][$fieldOrFlagsAr]['flags'];
        } elseif (is_array($fieldOrFlagsAr) && isset($fieldOrFlagsAr['flags'])) {
            $flags_ar = $fieldOrFlagsAr['flags'];
        } else {
            $flags_ar = [];
        }

        if (!is_array($flags_ar)) {
            $flags_ar = [$flags_ar];
        }

        return $flags_ar && in_array($flag, $flags_ar);
    }

    protected function getRowHtml(\diModel|null $m = null)
    {
        $id = $m ? $m->getId() : self::NEW_ID_STRING;
        $this->scripts = [];
        $ar1 = $ar2 = [];
        $hiddenInputs = [];

        foreach ($this->info_ar[$this->field]['fields'] as $k => $v) {
            if ($this->isFlag($k, 'local')) {
                continue;
            }

            if (!is_array($v)) {
                $v = ['type' => $v];
            }

            if (in_array($v['type'], ['date', 'time', 'datetime'])) {
                $defaultValue = \diDateTime::timestamp();
            } elseif (
                in_array($v['type'], ['date_str', 'time_str', 'datetime_str'])
            ) {
                $defaultValue = \diDateTime::sqlFormat();
            } else {
                $defaultValue = $v['default'] ?? '';
            }

            if (!empty($v['virtual']) && !empty($v['values_collector'])) {
                $value = $m ? $v['values_collector']($id, $m->get()) : $defaultValue;
            } else {
                $value = static::extractDataSafe($m, $k) ?? $defaultValue;
            }

            // input
            $ar1[] = '{' . strtoupper($k) . '}';
            $ar2[] = $this->get_input(
                $k,
                $id,
                $v['type'] != 'password' ? $value : ''
            );

            // value
            $ar1[] = '{' . strtoupper($k) . '#VALUE}';
            $ar2[] = $value;

            if ($v['type'] == 'password') {
                $ar1[] = '{' . strtoupper($k) . '2}';
                $ar2[] = $this->get_input($k . 2, $id, '', [
                    'type' => 'password',
                    'class' => 'password-confirm',
                ]);
            }

            if ($this->isFlag($k, 'hidden')) {
                $hiddenInputs[$k] = end($ar2);
            }
        }

        if (!empty($this->info_ar[$this->field]['customTemplateMacros'])) {
            foreach (
                $this->info_ar[$this->field]['customTemplateMacros']
                as $key => $callback
            ) {
                $ar1[] = '{' . $key . '}';
                $ar2[] = $callback($m ? $m->get() : []);
            }
        }

        $kill_div = $this->static_mode
            ? ''
            : "<span class=\"close\" title=\"{$this->L(
                'delete'
            )}\" data-field=\"$this->safeField\" data-id=\"$id\"></span>";

        $order_num_div =
            !isset($this->info_ar[$this->field]['fields']['order_num']) &&
            $m &&
            $m->exists('order_num')
                ? "<input type=hidden name=\"{$this->safeField}_order_num[$id]\" value=\"{$m->get(
                    'order_num'
                )}\" data-field-name=\"order_num\">"
                : '';

        $cssClass = $this->isLite ? ' dynamic-row--lite' : '';

        return "<div id=\"{$this->safeField}_div[$id]\" class=\"dynamic-row$cssClass\" data-id=\"$id\" data-main-field=\"$this->field\">" .
            "<input type=hidden name=\"{$this->safeField}_ids_ar[]\" value=\"$id\" data-field-name=\"ids_ar\">" .
            join("\n", $hiddenInputs) .
            $kill_div .
            $order_num_div .
            str_replace($ar1, $ar2, $this->info_ar[$this->field]['template']) .
            '</div>';
    }

    function get_input($field, $id, $value, $properties = [])
    {
        $ar = $this->info_ar[$this->field]['fields'][$field] ?? $properties;

        if (!is_array($ar)) {
            $ar = [
                'type' => $ar,
            ];
        }

        $f = $this->formatName($field);
        $name = "{$this->safeField}_{$f}[$id]";
        $input_params = '';

        $this->data[$name] = $value;
        $this->current_field = $field;
        $this->current_id = $id;

        if (!isset($ar['feed'])) {
            switch ($ar['type']) {
                case 'checkbox':
                    $this->set_checkbox_input($name);
                    break;

                case 'radio':
                    $this->setRadioInput($name, "{$this->field}_$f", $id);
                    break;

                case 'date':
                case 'date_str':
                    $this->set_datetime_input($name);
                    break;

                case 'time':
                case 'time_str':
                    $this->set_datetime_input($name, false, true);
                    break;

                case 'datetime':
                case 'datetime_str':
                    $this->set_datetime_input($name, true, true);
                    break;

                case 'text':
                    $this->setTextareaInput($name);
                    break;

                case 'wysiwyg':
                    $this->setWysiwygInput($name);
                    break;

                case 'color':
                    $this->setColorInput($name);
                    break;

                case 'pic':
                    $this->set_pic_input($name);
                    break;

                case 'file':
                    $this->set_file_input($name);
                    break;

                default:
                    $valueOut = StringHelper::out($value);

                    if ($this->static_mode || $this->isFlag($ar, 'static')) {
                        $this->inputs[$name] = $valueOut;
                        break;
                    }

                    if (in_array($ar['type'], ['int', 'float', 'double'])) {
                        $input_params .= ' size="6"';
                    } elseif (!empty($ar['input_size'])) {
                        $input_params .= " size=\"{$ar['input_size']}\"";
                    }

                    if (isset($ar['class'])) {
                        $input_params .= " class='{$ar['class']}'";
                    }

                    if (isset($ar['placeholder'])) {
                        $input_params .= " placeholder='{$ar['placeholder']}'";
                    }

                    if ($ar['type'] == 'password') {
                        $type = 'password';
                    } else {
                        $type = 'text';
                    }

                    $input_params .= " data-field-name='$field'";
                    $input_params .= " type=\"$type\" name=\"$name\" id=\"$name\" value=\"$valueOut\"";

                    $this->inputs[$name] = "<input $input_params>";
                    break;
            }
        } else {
            $template_text = $ar['template_text'] ?? '%title%';
            $template_value = $ar['template_value'] ?? '%id%';
            $format = $ar['format'] ?? null;
            $prefix_ar = $ar['prefix_ar'] ?? [];
            $suffix_ar = $ar['suffix_ar'] ?? [];
            $columns = $ar['columns'] ?? null;
            /** @var \diTags | null $tagsClass */
            $tagsClass = $ar['tagsClass'] ?? null;

            if (in_array($ar['type'], ['checkboxes', 'tags'])) {
                if ($tagsClass && $id !== self::NEW_ID_STRING) {
                    $this->data[$name] = $tagsClass::tagIdsAr(
                        \diTypes::getId($this->getTable()),
                        $id
                    );
                }

                $options = [
                    'format' => $format ?? $template_text,
                ];

                if ($columns) {
                    $options['columns'] = $columns;
                }

                $this->set_cb_list_input($name, $ar['feed'], $options);
            } elseif (is_array($ar['feed'])) {
                $this->set_select_from_array_input(
                    ['name' => $name, 'field' => $field],
                    $ar['feed'],
                    $prefix_ar,
                    $suffix_ar
                );
            } elseif (\diDB::is_rs($ar['feed'])) {
                $this->set_select_from_db_input(
                    ['name' => $name, 'field' => $field],
                    $ar['feed'],
                    $template_text,
                    $template_value,
                    $prefix_ar,
                    $suffix_ar
                );
            } elseif ($ar['feed'] instanceof diCollection) {
                $this->setSelectFromCollectionInput(
                    ['name' => $name, 'field' => $field],
                    $ar['feed'],
                    $format,
                    $prefix_ar,
                    $suffix_ar
                );
            } else {
                throw new \Exception(
                    "Unknown feed for \${$this->table}_form_fields[\"$this->field\"][\"fields\"][\"$field\"]"
                );
            }
        }

        if ($this->isFlag($field, 'hidden')) {
            $this->inputs[$name] =
                "<input type=hidden name=\"$name\" value=\"" .
                StringHelper::out($value) .
                "\">";
        }

        return $this->inputs[$name];
    }

    protected function setWysiwygInput($field)
    {
        $outData = StringHelper::out($this->data[$field]);

        if ($this->static_mode) {
            $this->inputs[$field] = $outData;

            return;
        }

        $props = $this->info_ar[$this->field]['fields'][$this->current_field] ?? [];
        $attrStr = ArrayHelper::toAttributesString(
            extend(
                [
                    'id' => $field,
                    'name' => $field,
                    'cols' => 60,
                    'rows' => 10,
                    'data-field-name' => $field,
                ],
                $props['attributes'] ?? []
            )
        );
        $outData = StringHelper::out($this->data[$field]);

        $this->inputs[
            $field
        ] = "<div class='wysiwyg'><textarea $attrStr>$outData</textarea></div>";
    }

    protected function setTextareaInput($field)
    {
        $outData = StringHelper::out($this->data[$field]);

        if ($this->static_mode) {
            $this->inputs[$field] = nl2br($outData);

            return;
        }

        $props = $this->info_ar[$this->field]['fields'][$this->current_field] ?? [];
        $attributes = extend(
            [
                'id' => $field,
                'name' => $field,
                'cols' => 50,
                'rows' => 10,
                'data-field-name' => $field,
            ],
            $props['attributes'] ?? []
        );

        if (!empty($props['placeholder'])) {
            $attributes['placeholder'] = $props['placeholder'];
        }

        $attrStr = ArrayHelper::toAttributesString($attributes);
        $outData = StringHelper::out($this->data[$field]);

        $this->inputs[$field] = "<textarea $attrStr>$outData</textarea>";
    }

    function set_select_from_array_input(
        $name,
        $ar,
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        if (is_array($name)) {
            // list('field' => $field, 'name' => $name) = $name;
            $field = $name['field'];
            $name = $name['name'];
        } else {
            $field = '';
        }

        if ($this->static_mode) {
            $this->inputs[$name] = isset($ar[$this->data[$name]])
                ? StringHelper::out($ar[$this->data[$name]])
                : '---';
        } else {
            $sel = new diSelect($name, $this->data[$name]);
            $sel->setAttr('data-field-name', $field);

            if (isset($this->inputs_params[$name])) {
                $sel->setAttr($this->inputs_params[$name]);
            }

            $sel->addItemArray($prefix_ar);
            $sel->addItemArray($ar);
            $sel->addItemArray($suffix_ar);

            $this->inputs[$name] = $sel;
        }
    }

    function set_select_from_db_input(
        $name,
        $db_rs,
        $template_text = '%title%',
        $template_value = '%id%',
        $prefix_ar = [],
        $suffix_ar = []
    ) {
        if (is_array($name)) {
            // list('field' => $field, 'name' => $name) = $name;
            $field = $name['field'];
            $name = $name['name'];
        } else {
            $field = '';
        }

        $field2 = substr($name, strlen($this->field) + 1);
        $field2 = substr($field2, 0, strpos($field2, '['));

        if (!isset($this->input_objects[$field2])) {
            $sel = new diSelect($name, $this->data[$name]);
            $sel->setAttr('data-field-name', $field);

            if (isset($this->inputs_params[$name])) {
                $sel->setAttr($this->inputs_params[$name]);
            }

            if ($prefix_ar) {
                $sel->addItemArray($prefix_ar);
            }

            $static_text = '';

            while ($db_rs && ($db_r = $this->getDb()->fetch_array($db_rs))) {
                $ar1 = [];
                $ar2 = [];

                foreach ($db_r as $k => $v) {
                    $ar1[] = "%$k%";
                    $ar2[] = $v;
                }

                $text = str_replace($ar1, $ar2, $template_text);
                $value = str_replace($ar1, $ar2, $template_value);

                if ($value == $this->data[$name]) {
                    $static_text = $text;
                }

                $sel->addItem($value, $text);
            }

            if ($suffix_ar) {
                $sel->addItemArray($suffix_ar);
            }

            $this->input_objects[$field2] = $sel;
        } else {
            /** @var diSelect $sel */
            $sel = $this->input_objects[$field2];

            $sel->setAttr([
                'name' => $name,
                'id' => $name,
            ])->setCurrentValue($this->data[$name]);

            $a1 = $sel->getSimpleItemsAr();
            $static_text = isset($a1[$sel->getCurrentValue()])
                ? $a1[$sel->getCurrentValue()]
                : '---';
        }

        $this->inputs[$name] = $sel->getHTML();

        if ($this->static_mode) {
            $this->inputs[$name] = $static_text;
        }
    }

    public function setSelectFromCollectionInput(
        $name,
        diCollection $col,
        $format = null,
        $prefixAr = [],
        $suffixAr = []
    ) {
        if (is_array($name)) {
            // list('field' => $field, 'name' => $name) = $name;
            $field = $name['field'];
            $name = $name['name'];
        } else {
            $field = '';
        }

        $field2 = substr($name, strlen($this->field) + 1);
        $field2 = substr($field2, 0, strpos($field2, '['));

        if (!isset($this->input_objects[$field2])) {
            $sel = diSelect::fastCreate(
                $name,
                $this->data[$name],
                $col,
                $prefixAr,
                $suffixAr,
                $format
            );
            $sel->setAttr('data-field-name', $field);

            if (isset($this->inputs_params[$name])) {
                $sel->setAttr($this->inputs_params[$name]);
            }

            $this->input_objects[$field2] = $sel;

            $static_text = 'Please contact dimaninc@gmail.com about this';
        } else {
            /** @var diSelect $sel */
            $sel = $this->input_objects[$field2];
            $sel->setAttr('data-field-name', $field);

            $sel->setAttr([
                'name' => $name,
                'id' => $name,
            ])->setCurrentValue($this->data[$name]);

            $a1 = $sel->getSimpleItemsAr();
            $static_text = isset($a1[$sel->getCurrentValue()])
                ? $a1[$sel->getCurrentValue()]
                : '---';
        }

        $this->inputs[$name] = $sel;

        if ($this->static_mode) {
            $this->inputs[$name] = $static_text;
        }

        return $this;
    }

    public function setColorInput($field)
    {
        $this->setData($field, Form::normalizeColor($this->getData($field) ?: ''));

        $color = $this->getData($field);
        $view = "<div data-purpose=\"color-view\" data-field=\"$field\" style=\"background: $color\"></div>";

        if (!$this->static_mode) {
            $options = Form::defaultColorOptions();

            $this->inputs[
                $field
            ] = "<input type=\"text\" name=\"$field\" value=\"$color\" data-jscolor=\"$options\" size=\"20\" />";
        } else {
            $this->inputs[$field] = $view . ' ' . $this->getData($field);
        }

        return $this;
    }

    function set_checkbox_input($field)
    {
        if ($this->static_mode) {
            $this->inputs[$field] = $this->L(
                (int) $this->data[$field] ? 'yes' : 'no'
            );
        } else {
            $input_params = '';

            if (isset($this->inputs_params[$field])) {
                foreach ($this->inputs_params[$field] as $_pn => $_pv) {
                    $input_params .= $_pn * 1 == 0 ? " $_pn=\"$_pv\"" : " $_pv";
                }
            }

            $checked = (int) $this->data[$field] ? ' checked' : '';
            $this->inputs[
                $field
            ] = "<input type='checkbox' name='$field' id='$field'$checked$input_params data-field-name='$field'>";
        }
    }

    protected function setRadioInput($field, $name, $id = null)
    {
        $id = $id ?: $this->data_id;

        if ($this->static_mode) {
            $this->inputs[$field] = $this->L(
                (int) $this->data[$field] ? 'yes' : 'no'
            );
        } else {
            $input_params = '';

            if (isset($this->inputs_params[$field])) {
                foreach ($this->inputs_params[$field] as $_pn => $_pv) {
                    $input_params .= $_pn * 1 == 0 ? " $_pn=\"$_pv\"" : " $_pv";
                }
            }

            $checked = (int) $this->data[$field] ? ' checked=checked' : '';
            $this->inputs[
                $field
            ] = "<input type='radio' name='$name' id='$field' value='$id'$checked$input_params data-field-name='$field'>";
        }
    }

    function get_checkbox_code($opts)
    {
        //$this->dicontrols_code_needed = true;

        $opts = extend(
            [
                'name' => '',
                'id' => '',
                'value' => '',
                'text' => '',
                'checked' => false,
                'disabled' => false,
                'attributes' => [],
            ],
            $opts
        );

        //if ($this->static_mode) $checked .= " disabled=true";
        //return "<label><input type='checkbox' name='{$name}' value='$id'$checked /> $r->title</label>";

        $classes = ['dicheckbox'];

        if ($opts['checked']) {
            $classes[] = 'checked';

            if (isset($this->checked_static_ar)) {
                $this->checked_static_ar[] = $opts['text'];
            }
        }

        if ($opts['disabled']) {
            $classes[] = 'disabled';
        }

        if ($opts['checked']) {
            $opts['attributes']['checked'] = 'checked';
        }

        $opts['data-field-name'] = $opts['name'];

        $attrs = ArrayHelper::toAttributesString($opts['attributes']);

        return //"<s class=\"" . join(" ", $classes) . "\" id=\"dicontrol-{$opts["id"]}\"></s>" .
            "<input type=\"checkbox\" id=\"{$opts['id']}\" name=\"{$opts['name']}\" value=\"{$opts['value']}\" {$attrs}>" .
                "<label for=\"{$opts['id']}\">{$opts['text']}</label>";
    }

    public function set_cb_list_input($field, $feed, $options = [])
    {
        $options = extend(
            [
                'columns' => 2,
                'format' => \diSelect::getDefaultCollectionFormatter(),
            ],
            $options
        );

        $format = $options['format'];
        $tags_ar = [];
        $this->checked_static_ar = [];

        $values =
            $this->getFieldProperty($field, 'values') ?: $this->getData($field);

        if (is_callable($values)) {
            $values = $values($this->getTable(), $field, $this->getParentId());
        } elseif (!is_array($values)) {
            $values = explode(',', $values ?? '');
        }

        $defaultCheckedHelper = fn($id, self $DR, $field) => (is_string(
            $this->getData($field)
        ) &&
            StringHelper::contains(",{$this->getData($field)},", ",$id,")) ||
            in_array($id, $values);
        $checkedHelper =
            $this->getFieldProperty($field, 'checkedHelper') ?:
            $defaultCheckedHelper;

        if (\diDB::is_rs($feed)) {
            while ($r = $this->getDb()->fetch($feed)) {
                $checked = $checkedHelper($r->id, $this, $field);

                $tags_ar[] = $this->get_checkbox_code([
                    'name' => "{$field}[]",
                    'id' => "$field-$r->id",
                    'value' => $r->id,
                    'text' => $r->title,
                    'checked' => $checked,
                    'disabled' => $this->static_mode,
                ]);
            }

            $this->getDb()->reset($feed);
            $feed_rc = $this->getDb()->count($feed);
        } elseif (is_array($feed)) {
            foreach ($feed as $k => $v) {
                $checked = $checkedHelper($k, $this, $field);

                $tags_ar[] = $this->get_checkbox_code([
                    'name' => "{$field}[]",
                    'id' => "$field-$k",
                    'value' => $k,
                    'text' => $v,
                    'checked' => $checked,
                    'disabled' => $this->static_mode,
                ]);
            }

            $feed_rc = count($feed);
        } elseif ($feed instanceof \diCollection) {
            /** @var \diModel $m */
            foreach ($feed as $m) {
                $checked = $checkedHelper($m->getId(), $this, $field);

                $data = [
                    'value' => $m->getId(),
                    'text' => $m->get('title'),
                    'attributes' => [],
                ];

                if (is_callable($format)) {
                    $data = extend($data, call_user_func($format, $m));
                } else {
                    $ar1 = $ar2 = [];

                    foreach ($m->get() as $k => $v) {
                        $ar1[] = "%$k%";
                        $ar2[] = $v;
                    }

                    $data['text'] = str_replace($ar1, $ar2, $format);
                }

                $tags_ar[] = $this->get_checkbox_code([
                    'name' => "{$field}[]",
                    'id' => "$field-{$data['value']}",
                    'value' => $data['value'],
                    'text' => $data['text'],
                    'checked' => $checked,
                    'disabled' => $this->static_mode,
                    'attributes' => $data['attributes'],
                ]);
            }

            $feed_rc = $feed->count();
        } else {
            throw new \Exception("Unknown feed for field $field");
        }

        // table
        $table = '<table><tr>';

        $per_column = ceil(count($tags_ar) / $options['columns']);

        for ($i = 0; $i < $options['columns']; $i++) {
            $table .=
                "<td style=\"padding-right: 20px; vertical-align: top;\">" .
                join(
                    '<br />',
                    array_slice($tags_ar, $per_column * $i, $per_column)
                ) .
                '</td>';
        }

        $table .= '</tr></table>';

        $maxTags =
            $this->info_ar[$this->field]['fields'][$this->current_field][
                'max_count_for_static'
            ] ?? $this->max_feed_count_to_show_static_checkboxes;

        if ($maxTags && $feed_rc > $maxTags) {
            if (!$this->checked_static_ar) {
                $this->checked_static_ar[] = $this->L('not_selected');
            }

            $table =
                "<div class=\"didynamic-static-checkboxes\"><span>" .
                join(', ', $this->checked_static_ar) .
                "</span></div><div class=\"didynamic-checkboxes hidden\">$table</div>";
        }

        $this->inputs[$field] = $table; //."<div style=\"margin: 5px 0;\"><input type=\"text\" name=\"{$field}{$new_field_suffix}\" value=\"\" style=\"width:100%;\" /></div>";

        return $this;
    }

    function set_datetime_input(
        $field,
        $date = true,
        $time = false,
        $calendar_cfg = true
    ) {
        if ($this->data[$field]) {
            $str_field_type =
                substr($this->get_row_type($this->current_field), -4) == '_str';
            //if (!$str_field_type)
            //  $str_field_type = !is_numeric($value);

            $v = getdate(
                $str_field_type
                    ? strtotime($this->data[$field])
                    : $this->data[$field]
            );
            $dy = $v['year'];
            $dm = lead0($v['mon']);
            $dd = lead0($v['mday']);
            $th = lead0($v['hours']);
            $tm = lead0($v['minutes']);
        } else {
            $dy = '';
            $dm = '';
            $dd = '';
            $th = '';
            $tm = '';
        }

        $d = !$this->static_mode
            ? "<input type=\"text\" name=\"{$field}[dd]\" id=\"{$field}[dd]\" value=\"$dd\" size=\"2\">." .
                "<input type=\"text\" name=\"{$field}[dm]\" id=\"{$field}[dm]\" value=\"$dm\" size=\"2\">." .
                "<input type=\"text\" name=\"{$field}[dy]\" id=\"{$field}[dy]\" value=\"$dy\" size=\"4\">"
            : date('d.m.Y', $this->data[$field]);

        $t = !$this->static_mode
            ? "<input type=\"text\" name=\"{$field}[th]\" id=\"{$field}[th]\" value=\"$th\" size=\"2\">:" .
                "<input type=\"text\" name=\"{$field}[tm]\" id=\"{$field}[tm]\" value=\"$tm\" size=\"2\">"
            : date('H:i', $this->data[$field]);

        $this->inputs[$field] = '';
        if ($date) {
            $this->inputs[$field] .= $d;
        }
        if ($this->inputs[$field]) {
            $this->inputs[$field] .= ' ';
        }
        if ($time) {
            $this->inputs[$field] .= $t;
        }

        if ($date && $calendar_cfg) {
            $uid = "{$this->table}_{$this->field}_{$field}";
            //$uid = get_unique_id(8);
            $uid2 = preg_replace('#\[.+$#', '', $uid);

            if ($calendar_cfg === true) {
                $calendar_cfg_js = "months_to_show: 1, date1: '$field', able_to_go_to_past: true";
            } else {
                $calendar_cfg_js = $calendar_cfg;
            }

            $calendar_btn = <<<EOF
 <button type="button" data-calendar-uid="{$uid}[{$this->current_id}]" class="w_hover">{$this->L(
                'calendar'
            )}</button>
EOF;

            $calendar_script = <<<EOF
if (typeof c_{$uid2} === 'undefined') c_{$uid2} = {};
c_{$uid} = {};
c_{$uid}[{$this->current_id}] = new diCalendar({
  instance_name: 'c_{$uid}[{$this->current_id}]',
  uid: '{$uid}[{$this->current_id}]',
  position_base: 'parent',
  language: '$this->language',
  $calendar_cfg_js
});
EOF;

            if (is_numeric($this->current_id)) {
                $this->inputs[$field] .= <<<EOF
$calendar_btn

<script type="text/javascript">
$calendar_script
</script>
EOF;
            } else {
                $this->inputs[$field] .= $calendar_btn;
                $this->scripts[$field] = $calendar_script;
            }
        }
    }

    private function getDelLinkCode($field)
    {
        preg_match('/^([^[]+)\[(\d+)\]$/', $field, $matches);

        if (!$matches) {
            return '';
        }

        $field2 = substr($matches[1], strlen($this->field) + 1);
        $id = $matches[2];
        $message = StringHelper::isWebPicFilename($this->getData($field))
            ? $this->L('delete_pic_confirmation')
            : $this->L('delete_file_confirmation');
        $path = diLib::getAdminWorkerPath('files', 'del_dynamic', [
            $this->table,
            $this->id,
            $this->getCurrentModel()->getTable() ?: $this->field,
            $field2,
            $id,
        ]);

        return ", <a href=\"$path\" data-field=\"$field\" data-confirm=\"$message\" " .
            "class=\"del-file\">{$this->L('delete')}</a>";
    }

    function get_pic_html_for_input(
        $field,
        $fullName,
        $hideIfNoFile = false,
        $showDelLink = true
    ) {
        preg_match('/^([^[]+)\[([^]]+)\]$/', $field, $matches);

        $field2 = substr($matches[1], strlen($this->field) + 1);
        $f = StringHelper::unslash(Config::getPublicFolder()) . $fullName;
        $ext = strtoupper(StringHelper::fileExtension($fullName));
        $imgWrapperNeeded = false;

        if (is_file($f)) {
            $httpName =
                \diPaths::http($this->storedModel, false, $field) .
                '/' .
                StringHelper::unslash($fullName, false);

            if (
                !StringHelper::contains($httpName, '://') &&
                \diLib::getSubFolder()
            ) {
                $httpName = '/' . $httpName;
            }

            $imgTag = '';
            $ff_w = $ff_h = null;
            $ff_s = filesize($f);
            $previewWithText = false;

            if (diSwiffy::is($f)) {
                list($ff_w, $ff_h) = diSwiffy::getDimensions($f);
                $imgTag = diSwiffy::getHtml($fullName, $ff_w, $ff_h);
                $imgWrapperNeeded = true;
            } elseif (in_array($ext, ['MP4', 'M4V', 'OGV', 'WEBM', 'AVI'])) {
                //$mime_type = self::get_mime_type_by_ext($ext);
                // type=\"$mime_type\"
                $imgTag = "<div><video preload=\"none\" controls width=400 height=225><source src=\"$httpName\"></video></div>";
            } elseif (in_array($ext, ['MP3', 'OGG'])) {
                $imgTag = "<div><audio preload=\"none\" controls=\"controls\"><source src=\"$httpName\"></audio></div>";
            } else {
                list($ff_w, $ff_h, $ff_t) = getimagesize($f);

                if ($ff_t == 4 || $ff_t == 13) {
                    $imgTag = "<script type=\"text/javascript\">run_movie(\"$fullName\", \"$ff_w\", \"$ff_h\", \"opaque\");</script>";
                } elseif ($ff_t) {
                    $imgTag = "<img src=\"$httpName\" alt=\"$field\" />";
                    $imgWrapperNeeded = true;
                }
            }

            $info = join(
                ', ',
                array_filter([
                    $ext,
                    $ff_w && $ff_h ? $ff_w . 'x' . $ff_h : null,
                    size_in_bytes($ff_s),
                    \diDateTime::simpleFormat(filemtime($f)),
                ])
            );

            if ($imgTag && $imgWrapperNeeded) {
                $additionalClassName = $previewWithText ? 'text' : 'embed';

                $imgTag = "<div class='container {$additionalClassName}'>$imgTag</div>";
            }
        } else {
            $info = "No file ($fullName)";
            $imgTag = '';
            $httpName = '#no-file';
        }

        $del_link = $showDelLink // && $imgTag
            ? $this->getDelLinkCode($field)
            : '';

        $this->uploaded_images_w[$field] = !empty($ff_w) ? $ff_w : 0;

        //$fullName
        return !empty($this->data[$field]) &&
            (is_file(diPaths::fileSystem() . $fullName) || !$hideIfNoFile)
            ? "<div class=\"existing-pic-holder\" data-field='$field2'>" .
                    $imgTag .
                    "<a href='$httpName' class='link'>" .
                    basename($fullName) .
                    '</a>' .
                    "<div class=\"info\">$info$del_link</div></div>"
            : '';
    }

    protected function getPicsFolder()
    {
        global $dynamic_pics_folder;

        $m = $this->getCurrentModel();
        $defaultFolder = $dynamic_pics_folder . $this->table . '/';

        if (!$m->getTable()) {
            return $defaultFolder;
        }

        return $m->getPicsFolder() ?: $defaultFolder;
    }

    function set_pic_input($field, $path = false, $hide_if_no_file = false)
    {
        if ($path === false) {
            $path = '/' . $this->getPicsFolder();
        }

        $showImageType =
            $this->getFieldProperty($this->current_field, 'showImageType') ?:
            Submit::IMAGE_TYPE_MAIN;
        $path .= Submit::getFolderByImageType($showImageType);

        $v = isset($this->data[$field]) ? $this->data[$field] : '';

        $file_info = $this->get_pic_html_for_input(
            $field,
            $path . $v,
            $hide_if_no_file
        );
        $this->uploaded_images[$field] = $file_info;

        $this->inputs[$field] =
            $this->is_flag($field, 'static') || $this->static_mode
                ? "$file_info<input type=\"hidden\" name=\"$field\" value=\"$v\">"
                : "$file_info<div class=\"file-input-wrapper\" data-caption=\"{$this->L(
                    'choose_file'
                )}\"><input type=\"file\" name=\"$field\" value=\"\" size=\"40\"></div>";
    }

    function set_file_input($field, $path = false, $hide_if_no_file = false)
    {
        if ($path === false) {
            $path = '/' . $this->getPicsFolder();
        }

        $v = $this->data[$field] ?? '';

        $file_info = $this->get_pic_html_for_input(
            $field,
            $path . $v,
            $hide_if_no_file
        );

        $this->uploaded_files[$field] = $file_info;

        $field2 = substr($field, strlen($this->field) + 1);
        $field2 = substr($field2, 0, strpos($field2, '['));

        $this->inputs[$field] =
            $this->is_flag($field, 'static') || $this->static_mode
                ? "$file_info<input type=\"hidden\" name=\"$field\" value=\"$v\">"
                : "$file_info<div class=\"file-input-wrapper\" data-caption=\"{$this->L(
                        'choose_file'
                    )}\">" .
                    "<input type=\"file\" name=\"$field\" value=\"\" size=\"40\" " .
                    $this->getInputAttributesString($field2) .
                    '></div>';
    }

    private function getInputAttributesString($field, $forceAttributes = [])
    {
        $ar = $this->getInputAttributes($field, $forceAttributes);

        return $ar
            ? ArrayHelper::toAttributesString($ar, true, ArrayHelper::ESCAPE_HTML)
            : '';
    }

    private function getInputAttributes($field, $forceAttributes = [])
    {
        return extend(
            $this->getFieldProperty($field, 'attrs') ?: [],
            isset($this->inputs_params[$field]) ? $this->inputs_params[$field] : [],
            $forceAttributes
        );
    }

    private function getFieldProperty($field = null, $property = null)
    {
        $a = $this->info_ar[$this->field]['fields'];

        if ($field !== null && $property !== null && isset($a[$field][$property])) {
            return $a[$field][$property];
        } elseif ($field && $property === null && isset($a[$field])) {
            return $a[$field];
        }

        return null;
    }

    private function getProperty($property)
    {
        $a = $this->info_ar[$this->field];

        if (isset($a[$property])) {
            return $a[$property];
        }

        return null;
    }

    public function submit()
    {
        global $dynamic_pics_folder, $tn_folder, $tn2_folder, $tn3_folder;

        $resultIds = [];
        $initialIds = $_POST["{$this->safeField}_ids_ar"] ?? [];

        $fileFields = [];
        $fields = (array) $this->getProperty('fields');
        $techFieldsCallback =
            $this->getProperty('techFieldsCallback') ?:
            $this->getProperty('tech_fields_ar');
        $techFieldsSet = false;
        $beforeSaveCallback = $this->getProperty('beforeSave');
        $afterSaveCallback =
            $this->getProperty('afterSave') ?: $this->getProperty('after_save');
        $afterAllSavedCallback = $this->getProperty('afterAllSaved');

        // in lite mode here to store values
        $liteValues = [];

        foreach ($fields as $k => $v) {
            if (!is_array($v)) {
                $v = ['type' => $v];
            }

            if (in_array($v['type'], ['file', 'pic'])) {
                $fileFields[] = $k;

                if (!empty($v['defaultMultiplePic'])) {
                    $this->defaultMultiplePicField = $k;
                }
            }
        }

        if (!$this->defaultMultiplePicField && $fileFields) {
            reset($fileFields);
            $this->defaultMultiplePicField = current($fileFields);
        }

        foreach ($initialIds as $id) {
            if (!(int) $id) {
                continue;
            }

            $this->data_id = (int) $id;
            $this->test_r =
                $id > 0 && $this->getDataTable()
                    ? $this->getDb()->r(
                        $this->getDataTable(),
                        "WHERE $this->subquery AND id = '$id'"
                    )
                    : null;

            $rowExists = !!$this->test_r;

            $this->getStoredModel()
                ->destroy()
                ->initFrom($this->test_r);

            $this->data = [];
            $this->jsonData = [];

            // tech fields
            if (is_callable($techFieldsCallback)) {
                $techData = $techFieldsCallback(
                    $this->table,
                    $this->field,
                    $this->id,
                    $this
                );

                $this->setDataAr($techData);

                $techFieldsSet = true;
            }

            // form fields
            foreach ($fields as $k => $opts) {
                if (!is_array($opts)) {
                    $opts = ['type' => $opts];
                }

                if (!empty($opts['virtual'])) {
                    continue;
                }

                if (!isset($opts['default'])) {
                    $opts['default'] = '';
                }

                $isFileType = in_array($opts['type'], ['pic', 'file']);

                // data (not files)
                $this->setRowDataForField($k, $opts, $id);

                if (!empty($this->data[$k]) || !$isFileType) {
                    if ($opts['type'] === 'radio') {
                        $rf = "{$this->safeField}_$k";
                        $val = isset($_POST[$rf]) && $_POST[$rf] == $id ? 1 : 0;

                        $this->setData($k, $val);
                    } elseif (isset($this->data[$k])) {
                        $this->setData($k, $this->data[$k]);
                    }
                }
            }

            if (is_callable($beforeSaveCallback)) {
                $techData = $beforeSaveCallback($this, $id);

                $this->data = extend($this->data, $techData);

                $this->setDataAr($techData);
            }

            if ($this->getDataTable() && !$rowExists && !$techFieldsSet) {
                $this->data['_table'] = $this->table;
                $this->data['_field'] = $this->field;
                $this->data['_id'] = $this->id;

                $this->getStoredModel()->set([
                    '_table' => $this->table,
                    '_field' => $this->field,
                    '_id' => $this->id,
                ]);
            }

            $this->setJsonData();

            if ($this->isLite) {
                $liteValues[] = $this->getStoredModel()->get('value');
            } else {
                $this->getStoredModel()->save();
                $resultIds[] = $this->getStoredModel()->getId();
            }
        }

        $resultIds = array_merge($resultIds, $this->submitMultipleFiles());

        if ($this->getDataTable()) {
            // killing unneeded files
            $filesToKill = [];
            $m = \diModel::createForTableNoStrict($this->getDataTable());
            $pics_folder = $m->modelType()
                ? $m->getPicsFolder()
                : $dynamic_pics_folder . "$this->table/";

            $exceptQuery = $resultIds
                ? "AND id{$this->getDb()::in($resultIds, false, false)}"
                : '';
            $killQuery = "WHERE $this->subquery $exceptQuery";
            $kill_rs = $this->getDb()->rs($this->getDataTable(), $killQuery);
            while ($kill_r = $this->getDb()->fetch($kill_rs)) {
                foreach ($fileFields as $field) {
                    $filesToKill[] = $kill_r->$field;
                }
            }
            $this->getDb()->delete($this->getDataTable(), $killQuery);

            foreach ($filesToKill as $fn) {
                @unlink($this->abs_path . $pics_folder . $fn);
                @unlink($this->abs_path . $pics_folder . $tn_folder . $fn);
                @unlink($this->abs_path . $pics_folder . $tn2_folder . $fn);
                @unlink($this->abs_path . $pics_folder . $tn3_folder . $fn);
            }

            // making order num to look ok
            $test = $this->getDb()->ar($this->getDataTable());

            if (isset($test['order_num'])) {
                $order_num = 0;

                $rs = $this->getDb()->rs(
                    $this->getDataTable(),
                    "WHERE $this->subquery ORDER BY order_num ASC,id ASC"
                );
                while ($rs && ($r = $this->getDb()->fetch($rs))) {
                    $this->getDb()->update(
                        $this->getDataTable(),
                        [
                            'order_num' => ++$order_num,
                        ],
                        $r->id
                    );
                }
            }

            if (is_callable($afterSaveCallback) || $this->afterSaveEachCallbacks) {
                foreach ($resultIds as $_idx => $_id) {
                    $initialId = $initialIds[$_idx];

                    if ($afterSaveCallback) {
                        $afterSaveCallback($this, $_id, $initialId);
                    }

                    foreach (
                        $this->afterSaveEachCallbacks
                        as ['origId' => $origId, 'callback' => $cb]
                    ) {
                        if ($initialId == $origId) {
                            $cb($_id);
                        }
                    }
                }
            }
        }

        $this->afterSaveEachCallbacks = [];

        if (is_callable($afterAllSavedCallback)) {
            $afterAllSavedCallback($this);
        }

        if ($this->isLite) {
            return $liteValues;
        }

        return null;
    }

    protected function submitMultipleFiles()
    {
        if (
            !$this->getProperty('multipleUploading') &&
            !$this->getProperty('multiple_uploading')
        ) {
            return [];
        }

        $ids = [];

        $multiField = self::getMultipleUploadFieldName($this->field);

        $atLeastOneUploaded =
            isset($_FILES[$multiField]['size']) &&
            array_sum($_FILES[$multiField]['size']) > 0;

        if ($atLeastOneUploaded) {
            $id = self::MULTIPLE_UPLOAD_FIRST_ID;

            $maxOrderNum = $this->getDb()->r(
                $this->getDataTable(),
                "WHERE $this->subquery",
                'MAX(order_num) AS o'
            );
            $orderNum = $maxOrderNum ? (int) $maxOrderNum->o : 0;

            $fields = (array) $this->getProperty('fields');
            $techFieldsCallback =
                $this->getProperty('techFieldsCallback') ?:
                $this->getProperty('tech_fields_ar');
            $techFieldsSet = false;
            $multiUploadCallback = $this->getProperty('multiUploadCallback');
            $beforeSaveCallback = $this->getProperty('beforeSave');

            foreach ($_FILES[$multiField]['name'] as $idx => $name) {
                $id--;

                if (!$name) {
                    continue;
                }

                if (
                    !empty($_FILES[$multiField]['error'][$idx]) &&
                    $_FILES[$multiField]['error'][$idx] != 4
                ) {
                    Logger::getInstance()->log(
                        $idx . ' error: ' . $_FILES[$multiField]['error'][$idx],
                        'multiple'
                    );

                    continue;
                }

                $orderNum++;
                $this->data_id = (int) $id;

                $this->test_r = null;
                $this->getStoredModel()
                    ->destroy()
                    ->initFrom($this->test_r);

                $this->data = [];

                // tech fields
                if (is_callable($techFieldsCallback)) {
                    $_a = $techFieldsCallback(
                        $this->table,
                        $this->field,
                        $this->id,
                        $this
                    );

                    // $this->data = extend($this->data, $_a);
                    // $this->getStoredModel()->set($_a);
                    $this->setDataAr($_a);

                    $techFieldsSet = true;
                }

                // form fields
                foreach ($fields as $k => $v) {
                    if (!is_array($v)) {
                        $v = ['type' => $v];
                    }

                    if (!empty($v['virtual'])) {
                        continue;
                    }

                    if (!isset($v['default'])) {
                        $v['default'] = '';
                    }

                    // files
                    $this->setRowDataForField($k, $v, $id);

                    if (in_array($v['type'], ['pic', 'file']) && !$this->data[$k]) {
                    } else {
                        if ($v['type'] === 'radio') {
                            // $this->getStoredModel()->set($k, 0);
                            $this->setData($k, 0);
                        } elseif ($v['type'] === 'checkbox') {
                            // $this->getStoredModel()->set($k, $v['default']);
                            $this->setData($k, $v['default']);
                        } elseif (isset($this->data[$k])) {
                            // $this->getStoredModel()->set($k, $this->data[$k]);
                            $this->setData($k, $this->data[$k]);
                        }
                    }
                }

                if (is_callable($multiUploadCallback)) {
                    $_a = $multiUploadCallback(
                        $this->table,
                        $this->field,
                        $this->id,
                        $this
                    );

                    // $this->data = extend($this->data, $_a);
                    // $this->getStoredModel()->set($_a);
                    $this->setDataAr($_a);
                }

                if (is_callable($beforeSaveCallback)) {
                    $_a = $beforeSaveCallback($this);

                    // $this->data = extend($this->data, $_a);
                    // $this->getStoredModel()->set($_a);
                    $this->setDataAr($_a);
                }

                if (!$techFieldsSet) {
                    $this->getStoredModel()->set([
                        '_table' => $this->table,
                        '_field' => $this->field,
                        '_id' => $this->id,
                    ]);
                }

                if (
                    isset($fields['order_num']) &&
                    !$this->getStoredModel()->has('order_num')
                ) {
                    $this->data['order_num'] = $orderNum;

                    $this->getStoredModel()->set('order_num', $orderNum);
                }

                if (isset($fields['default']) && $orderNum == 1) {
                    $this->data['default'] = 1;

                    $this->getStoredModel()->set('default', 1);
                }

                if (isset($fields['by_default']) && $orderNum == 1) {
                    $this->data['by_default'] = 1;

                    $this->getStoredModel()->set('by_default', 1);
                }

                try {
                    $this->getStoredModel()->save();

                    $ids[] = $this->getStoredModel()->getId();
                } catch (\Exception $e) {
                    Logger::getInstance()->variable('exception', $e->getMessage());
                }
            }
        }

        return $ids;
    }

    protected function addAfterSaveEachCallback($origId, $callback)
    {
        $this->afterSaveEachCallbacks[] = [
            'origId' => $origId,
            'callback' => $callback,
        ];

        return $this;
    }

    public function setRowDataForField($field, $opts, $origId)
    {
        if ($this->isFlag($field, 'local')) {
            return $this;
        }

        $f = $this->formatName($field);
        $ff = "{$this->safeField}_$f";

        switch ($opts['type']) {
            case 'password':
                $this->data[$field] = $_POST[$ff][$origId] ?? $opts['default'];
                $this->data[$field . '2'] =
                    $_POST[$ff . '2'][$origId] ?? $opts['default'];
                break;

            case 'date':
            case 'date_str':
                $this->make_datetime($field, $origId);
                break;

            case 'time':
            case 'time_str':
                $this->make_datetime($field, $origId, false, true);
                break;

            case 'datetime':
            case 'datetime_str':
                $this->make_datetime($field, $origId, true, true);
                break;

            case 'pic':
            case 'file':
                $this->store_pic($field, $origId, $opts);
                break;

            case 'checkboxes':
            case 'tags':
                /** @var \diTags $tagsClass */
                $tagsClass = $this->getFieldProperty($field, 'tagsClass');
                $saver = $this->getFieldProperty($field, 'saverAfterSubmit');

                if ($tagsClass) {
                    $this->addAfterSaveEachCallback(
                        $origId,
                        fn($rowId) => $tagsClass::saveFromPost(
                            \diTypes::getId($this->getDataTable()),
                            $rowId,
                            [$ff, $origId]
                        )
                    );

                    break;
                }

                if ($saver) {
                    $this->addAfterSaveEachCallback(
                        $origId,
                        fn($rowId) => $saver($field, $origId, $rowId, $this)
                    );

                    break;
                }

                $this->data[$field] = !empty($_POST[$ff][$origId])
                    ? ',' . join(',', $_POST[$ff][$origId]) . ','
                    : '';

                break;

            default:
                $this->data[$field] =
                    $_POST[$ff][$origId] ??
                    ($opts['type'] == 'checkbox' ? 0 : $opts['default']);
        }

        if (empty($opts['no_input_adjust'])) {
            switch ($opts['type']) {
                case 'int':
                case 'tinyint':
                case 'smallint':
                case 'integer':
                case 'date':
                case 'time':
                case 'datetime':
                    $this->data[$field] = intval($this->data[$field]);
                    break;

                case 'float':
                    $this->data[$field] = str_replace(',', '.', $this->data[$field]);
                    $this->data[$field] = floatval($this->data[$field]);
                    break;

                case 'double':
                    $this->data[$field] = str_replace(',', '.', $this->data[$field]);
                    $this->data[$field] = doubleval($this->data[$field]);
                    break;

                case 'pic':
                case 'file':
                    if (empty($this->data[$field])) {
                        $this->data[$field] = '';
                    }
                    break;

                case 'password':
                    if (
                        $this->data[$field] &&
                        $this->data[$field] == $this->data[$field . '2']
                    ) {
                        $this->data[
                            $field
                        ] = $this->getCurrentModel()::hashPasswordFromRawToDb(
                            $this->data[$field],
                            $field
                        );
                    } else {
                        $r = $this->getDb()->r(
                            $this->getDataTable(),
                            $origId,
                            $field
                        );
                        $this->data[$field] = $r ? $r->$field : '';
                    }
                    break;

                case 'checkbox':
                case 'radio':
                    $this->data[$field] = $this->data[$field] ? true : false;
                    break;
            }
        }

        return $this;
    }

    function make_datetime($field, $id, $date = true, $time = false)
    {
        $f = $this->formatName($field);
        $ff = "{$this->safeField}_$f";

        $ar = getdate();

        if ($date) {
            if (isset($_POST[$ff][$id]['dd'])) {
                $ar['mday'] = (int) $_POST[$ff][$id]['dd'];
            }
            if (isset($_POST[$ff][$id]['dm'])) {
                $ar['mon'] = (int) $_POST[$ff][$id]['dm'];
            }
            if (isset($_POST[$ff][$id]['dy'])) {
                $ar['year'] = (int) $_POST[$ff][$id]['dy'];
            }
        }

        if ($time) {
            if (isset($_POST[$ff][$id]['th'])) {
                $ar['hours'] = (int) $_POST[$ff][$id]['th'];
            }
            if (isset($_POST[$ff][$id]['tm'])) {
                $ar['minutes'] = (int) $_POST[$ff][$id]['tm'];
            }
            if (isset($_POST[$ff][$id]['ts'])) {
                $ar['seconds'] = (int) $_POST[$ff][$id]['ts'];
            }
        }

        $ar['seconds'] = 0;

        $this->data[$field] = mktime(
            $ar['hours'],
            $ar['minutes'],
            $ar['seconds'],
            $ar['mon'],
            $ar['mday'],
            $ar['year']
        );

        if (substr($this->get_row_type($field), -4) == '_str') {
            $this->data[$field] = $this->data[$field]
                ? date('Y-m-d H:i:s', $this->data[$field])
                : '';
        }
    }

    private function isMultipleUploadRecord($id)
    {
        return $id <= self::MULTIPLE_UPLOAD_FIRST_ID;
    }

    public static function getMultipleUploadFieldName($field)
    {
        return self::MULTIPLE_UPLOAD_FIELD_NAME . Submit::formatName($field);
    }

    protected function store_pic($field, $id, $field_config)
    {
        global $tn_folder;

        $multiUploadMode = $this->isMultipleUploadRecord($id);
        $pics_folder = '/' . $this->getPicsFolder();

        FileSystemHelper::createTree(
            $this->abs_path,
            $pics_folder . $tn_folder,
            0775
        );

        $f = $this->formatName($field);
        $ff = $multiUploadMode
            ? self::getMultipleUploadFieldName($this->safeField)
            : "{$this->safeField}_$f";

        if ($multiUploadMode) {
            $id = self::MULTIPLE_UPLOAD_FIRST_ID - $id - 1;

            if (
                $this->defaultMultiplePicField &&
                $this->defaultMultiplePicField != $field
            ) {
                return $this;
            }
        }

        if (empty($_FILES[$ff]['name'][$id]) || $_FILES[$ff]['error'][$id]) {
            return $this;
        }

        $ext =
            '.' . strtolower(StringHelper::fileExtension($_FILES[$ff]['name'][$id]));

        if ($this->test_r && $this->test_r->$field) {
            $this->data[$field] = StringHelper::replaceFileExtension(
                $this->test_r->$field,
                $ext
            );
        } else {
            $this->data[$field] = Submit::getGeneratedFilename(
                Config::getPublicFolder() . $pics_folder,
                $_FILES[$ff]['name'][$id],
                $this->getFieldProperty($field, 'naming'),
                $this->getFieldProperty($field, 'maxNameLength')
            );
        }

        $fileOptions = Submit::prepareFileOptions(
            $field,
            $this->getFieldProperty($field, 'fileOptions') ?:
            $this->getStoredModel()->getPicStoreSettings($field) ?:
            [],
            $this->getStoredModel(),
            $this->getTable()
        );
        $callback = isset($field_config['callback'])
            ? $field_config['callback']
            : [\diDynamicRows::class, 'storePicSimple'];

        $F = [
            'name' => $_FILES[$ff]['name'][$id],
            'type' => $_FILES[$ff]['type'][$id],
            'tmp_name' => $_FILES[$ff]['tmp_name'][$id],
            'error' => $_FILES[$ff]['error'][$id],
            'size' => $_FILES[$ff]['size'][$id],
        ];

        if ($callback && is_callable($callback)) {
            $callback($F, $pics_folder, $field, $this->data, $this, [
                'fileOptions' => $fileOptions,
            ]);
        }

        return $this;
    }

    public static function storePicSimple(
        $F,
        $folder,
        $field,
        &$ar,
        \diDynamicRows $DR,
        $options = []
    ) {
        Submit::storeDynamicPicCallback(
            $F,
            $DR->getAdminPage()->getSubmit(),
            extend(
                [
                    'what' => $field,
                    'field' => $field,
                    'group_field' => $DR->getField(),
                    'data_table' => $DR->getDataTable(),
                ],
                $options
            ),
            $ar,
            $folder
        );
    }

    public static function storeFileSimple(
        $F,
        $folder,
        $field,
        &$ar,
        diDynamicRows $DR,
        $options = []
    ) {
        $fn = $ar[$field];

        FileSystemHelper::createTree(diPaths::fileSystem(), $folder, 0777);

        $full_fn = diPaths::fileSystem() . $folder . $fn;

        if (is_file($full_fn)) {
            unlink($full_fn);
        }

        move_uploaded_file($F['tmp_name'], $full_fn);
        @chmod($full_fn, 0775);

        list($ar["{$field}_w"], $ar["{$field}_h"], $ar["{$field}_t"]) = getimagesize(
            $full_fn
        );
    }
}

/** @deprecated  */
function simple_dyn_pic_store(
    $F,
    $pics_folder,
    $field,
    &$ar,
    \diDynamicRows $DynamicRows
) {
    diDynamicRows::storePicSimple($F, $pics_folder, $field, $ar, $DynamicRows);
}

/** @deprecated  */
function simple_dyn_file_store(
    $F,
    $pics_folder,
    $field,
    &$ar,
    \diDynamicRows $DynamicRows
) {
    diDynamicRows::storeFileSimple($F, $pics_folder, $field, $ar, $DynamicRows);
}
