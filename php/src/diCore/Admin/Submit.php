<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2017
 * Time: 16:58
 */

namespace diCore\Admin;

use diCore\Admin\Data\FormFlag;
use diCore\Data\Config;
use diCore\Data\Configuration;
use diCore\Database\Connection;
use diCore\Entity\DynamicPic\Collection as DynamicPics;
use diCore\Helper\ArrayHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\ImageHelper;
use diCore\Helper\Slug;
use diCore\Helper\StringHelper;

class Submit
{
    const FILE_NAMING_RANDOM = 1;
    const FILE_NAMING_ORIGINAL = 2;

    const FILE_NAME_RANDOM_LENGTH = 10;
    const FILE_NAME_GLUE = '-';

    const IMAGE_STORE_MODE_UPLOAD = 1;
    const IMAGE_STORE_MODE_REBUILD = 2;

    public static $defaultDynamicPicCallback = [
        self::class,
        'storeDynamicPicCallback',
    ];
    const dynamicPicsTable = 'dipics';

    public static $defaultSlugSourceFieldsAr = [
        'slug_source',
        'menu_title',
        'title',
    ];
    public static $allowedDynamicPicsFieldsAr = [
        'id',
        '_table',
        '_field',
        '_id',
        'title',
        'content',
        'orig_fn',
        'pic',
        'pic_t',
        'pic_w',
        'pic_h',
        'pic_tn',
        'pic_tn_t',
        'pic_tn_w',
        'pic_tn_h',
        'pic_tn2_t',
        'pic_tn2_w',
        'pic_tn2_h',
        'date',
        'by_default',
        'visible',
        'order_num',
        'color_id',
    ];

    const FILE_CHMOD = 0664;
    const DIR_CHMOD = 0775;

    const IMAGE_TYPE_MAIN = 0;
    const IMAGE_TYPE_PREVIEW = 1;
    const IMAGE_TYPE_PREVIEW2 = 2;
    const IMAGE_TYPE_PREVIEW3 = 3;
    const IMAGE_TYPE_PREVIEW4 = 4;
    const IMAGE_TYPE_PREVIEW5 = 5;
    const IMAGE_TYPE_ORIG = 10;
    const IMAGE_TYPE_BIG = 11;

    const IMAGE_RULE_DIVIDE_BY_2 = 2;
    const IMAGE_RULE_DIVIDE_BY_3 = 3;

    /** @var \diCore\Admin\BasePage */
    private $AdminPage;

    public $table;

    public $_form_fields;
    public $_local_fields;
    public $_all_fields;
    public $_ff;
    public $_lf;
    public $_af;

    public $page;
    public $redirect_href_ar;

    /*
     * JSON data to update main field in the end before db-save process
     */
    protected $jsonData = [];

    private $slugFieldName = 'clean_title';

    /** @var \diModel */
    private $model;

    public function __construct($table, $id = 0)
    {
        if (gettype($table) == 'object') {
            $this->AdminPage = $table;

            $this->table = $this->AdminPage->getTable();
            $id = $this->AdminPage->getId();

            $this->_form_fields = $this->AdminPage->getFormFieldsFiltered();
            $this->_local_fields = $this->AdminPage->getLocalFieldsFiltered();
            $this->_all_fields = $this->AdminPage->getAllFields();
            $this->_ff = $this->AdminPage->getFormFieldNames();
            $this->_lf = $this->AdminPage->getLocalFieldNames();
            $this->_af = $this->AdminPage->getAllFieldNames();
        } else {
            //back compatibility
            $this->table = $table;

            $this->_form_fields = $GLOBALS[$this->table . '_form_fields'] ?? [];
            $this->_local_fields = $GLOBALS[$this->table . '_local_fields'] ?? [];
            $this->_all_fields = $GLOBALS[$this->table . '_all_fields'] ?? [];
            $this->_ff = $GLOBALS[$this->table . '_ff'] ?? [];
            $this->_lf = $GLOBALS[$this->table . '_lf'] ?? [];
            $this->_af = $GLOBALS[$this->table . '_af'] ?? [];
        }

        $this->model = \diModel::createForTableNoStrict(
            $this->getTable(),
            $id,
            'id'
        );
        $this->model->setFieldsOnSaveCallback(function ($ar) {
            $ar = ArrayHelper::filterByKey($ar, $this->_af);

            foreach ($ar as $field => $value) {
                $type = $this->getFieldProperty($field, 'type');
                $skip = false;

                if (
                    in_array($type, [
                        'separator',
                        'dynamic_pics',
                        'dynamic_files',
                        'dynamic',
                        'string[]',
                        'int[]',
                    ])
                ) {
                    $skip = true;
                }

                if ($this->getId() && !$value && in_array($type, ['pic', 'file'])) {
                    $skip = true;
                }

                if (
                    $this->isFlag($field, 'virtual') ||
                    $this->isFlag($field, 'untouchable')
                ) {
                    $skip = true;
                }

                if ($skip) {
                    unset($ar[$field]);
                }
            }

            return $ar;
        });

        $this->setSlugFieldName();

        $this->page = \diRequest::post('page', 0);

        $this->redirect_href_ar = [
            'path' => $this->table,
        ];

        if ($this->page) {
            $this->redirect_href_ar['page'] = $this->page;
        }

        if (!empty($_POST['make_preview'])) {
            foreach ($_POST['make_preview'] as $k => $_tmp) {
                if ($this->isFlag($k, 'preview')) {
                    $this->redirect_href_ar['path'] = $this->table . '_form';
                    $this->redirect_href_ar['id'] = $this->getId();
                    $this->redirect_href_ar["make_preview[$k]"] = 1;
                }
            }
        }
    }

    private function setSlugFieldName($field = null)
    {
        if ($field) {
            $this->slugFieldName = $field;

            return $this;
        }

        if ($this->_af && in_array(\diModel::SLUG_FIELD_NAME, $this->_af)) {
            $this->slugFieldName = \diModel::SLUG_FIELD_NAME;
        }

        return $this;
    }

    public function getDb()
    {
        return Connection::get()->getDb();
    }

    public function getModel()
    {
        return $this->model ?: new \diModel();
    }

    /** @deprecated  */
    public function getCurRec($field = null)
    {
        return $this->getModel()->getOrigData($field);
    }

    public function wasFieldChanged($field)
    {
        return $this->getModel()->changed($field);
    }

    /**
     * @param $type integer
     * @return string
     * @throws \Exception
     */
    public static function getPreviewSuffix($type)
    {
        switch ($type) {
            case self::IMAGE_TYPE_MAIN:
                return '';

            case self::IMAGE_TYPE_PREVIEW:
                return '_tn';

            case self::IMAGE_TYPE_PREVIEW2:
                return '_tn2';

            case self::IMAGE_TYPE_PREVIEW3:
                return '_tn3';

            case self::IMAGE_TYPE_PREVIEW4:
                return '_tn4';

            case self::IMAGE_TYPE_PREVIEW5:
                return '_tn5';

            case self::IMAGE_TYPE_ORIG:
                return '_orig';

            case self::IMAGE_TYPE_BIG:
                return '_big';

            default:
                throw new \Exception("Unknown type '$type'");
        }
    }

    public static function parseImageType($name)
    {
        if (isInteger($name)) {
            return $name;
        }

        switch ($name) {
            case 'orig':
                return self::IMAGE_TYPE_ORIG;

            case 'big':
                return self::IMAGE_TYPE_BIG;

            case '':
            case 'main':
                return self::IMAGE_TYPE_MAIN;

            default:
                throw new \Exception('Unknown image type: ' . $name);
        }
    }

    /**
     * @deprecated
     */
    function redirect()
    {
        $params_ar = [];
        foreach ($this->redirect_href_ar as $k => $v) {
            $params_ar[] = "$k=$v";
        }

        $params = join('&', $params_ar);

        header("Location: ../index.php?$params");
    }

    function set_redirect_param($k, $v)
    {
        $this->redirect_href_ar[$k] = $v;

        return $this;
    }

    /** @deprecated */
    public function is_submit()
    {
        return $this->isSubmit();
    }

    public function isSubmit()
    {
        foreach ($this->_form_fields as $f => $v) {
            $field = self::formatName($f);

            if (
                !isset($_POST[$field]) &&
                !isset($_POST[$field . Form::NEW_FIELD_SUFFIX]) &&
                !isset($_FILES[$field]) &&
                !in_array($v['type'], [
                    'checkbox',
                    'checkboxes',
                    'dynamic',
                    'dynamic_pics',
                    'dynamic_files',
                    'separator',
                    'string[]',
                    'int[]',
                ]) &&
                !$this->isFlag($f, FormFlag::virtual) &&
                !$this->isFlag($f, FormFlag::initially_hidden)
            ) {
                // echo $f;

                return false;
            }
        }

        return true;
    }

    /**
     * @param $field string|array
     * @param $callback callable
     * @return $this
     */
    public function processData($field, $callback)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        foreach ($field as $f) {
            $this->setData($f, $callback($this->getData($f), $f));
        }

        return $this;
    }

    public static function getFieldNamePair($field)
    {
        $x = strpos($field, '.');

        if ($x === false) {
            return [$field, null];
        }

        $masterField = substr($field, 0, $x);
        $subField = substr($field, $x + 1);

        return [$masterField, $subField];
    }

    public function setData($field, $value = null)
    {
        if (is_string($field)) {
            [$masterField, $subField] = static::getFieldNamePair($field);

            // part of complex json field
            if ($subField) {
                $this->jsonData[$masterField][$subField] = $value;

                return $this;
            }
        }

        $this->getModel()->set($field, $value);

        return $this;
    }

    public function getData($field = null)
    {
        [$masterField, $subField] = self::getFieldNamePair($field ?? '');

        // part of complex json field
        if ($subField) {
            if (isset($this->jsonData[$masterField][$subField])) {
                return $this->jsonData[$masterField][$subField];
            }

            return $this->getModel()->getJsonData($masterField, $subField);
        }

        return $this->getModel()->get($field);
    }

    public function getId()
    {
        return $this->getModel()->getId();
    }

    public function getTable()
    {
        return $this->table;
    }

    /** @deprecated */
    function process_data()
    {
        return $this->gatherData();
    }

    public static function formatName($field)
    {
        return str_replace('.', '___', $field);
    }

    public function gatherData()
    {
        foreach ($this->_form_fields as $f => $v) {
            if (!isset($v['default'])) {
                $v['default'] = '';
            }

            $type = in_array($v['type'], ['float', 'double', 'int'])
                ? $v['type']
                : null;
            $field = self::formatName($f);
            $value = \diRequest::post($field, $v['default'] ?? null, $type);

            if (!empty($v['preprocessor'])) {
                $value = \diRequest::post($field) ?: $v['default'] ?? null;
                $value = $v['preprocessor']($value, $f, $this);
            }

            switch ($v['type']) {
                case 'password':
                    $this->setData($f, $value ?: '')->setData(
                        $f . '2',
                        \diRequest::post($field . '2', $v['default'] ?? '', 'string')
                    );
                    break;

                case 'date':
                case 'date_str':
                    $this->make_datetime($f, true, false);
                    break;

                case 'time':
                case 'time_str':
                    $this->make_datetime($f, false, true);
                    break;

                case 'datetime':
                case 'datetime_str':
                    $this->make_datetime($f, true, true);
                    break;

                case 'checkbox':
                    $this->setData($f, \diRequest::post($field) ? 1 : 0);
                    break;

                case 'checkboxes':
                    $saver = $this->getFieldProperty($f, 'saverBeforeSubmit') ?: [
                        self::class,
                        'checkboxesSaver',
                    ];
                    $saver($this, $f);
                    break;

                case 'file':
                case 'pic':
                case 'separator':
                    break;

                case 'json':
                    $this->setData($f, $value ?: null);
                    break;

                default:
                    $this->setData($f, $value);
                    break;
            }
        }

        // new fields
        foreach ($this->_ff as $f) {
            if (!empty($_POST[$f . Form::NEW_FIELD_SUFFIX])) {
                $this->setData($f, \diRequest::post($f . Form::NEW_FIELD_SUFFIX));
            }
        }

        // local fields
        foreach ($this->_local_fields as $f => $v) {
            if (!$this->getData($f)) {
                $this->setData($f, $v['default'] ?? null);
            }
        }

        // adjusting fields type
        foreach ($this->_all_fields as $f => $v) {
            if (
                $this->isFlag($f, 'virtual') ||
                in_array($v['type'], ['separator'])
            ) {
                continue;
            }

            switch ($v['type']) {
                case 'order_num':
                    $direction = $v['direction'] ?? 1;
                    $force = !empty($v['force']);

                    if (!$this->getId() || $force) {
                        $this->getModel()->calculateAndSetOrderNum($direction);
                    }
                    break;

                case 'int':
                case 'tinyint':
                case 'smallint':
                case 'integer':
                case 'date':
                case 'time':
                case 'datetime':
                    $this->processData($f, function ($v) {
                        return intval($v);
                    });
                    break;

                case 'date_str':
                case 'time_str':
                case 'datetime_str':
                    if (!$this->getData($f)) {
                        $this->setData($f, null);
                    }
                    break;

                case 'float':
                    $this->processData($f, function ($v) {
                        return floatval(StringHelper::fixFloatDot($v));
                    });
                    break;

                case 'double':
                    $this->processData($f, function ($v) {
                        return doubleval(StringHelper::fixFloatDot($v));
                    });
                    break;

                case 'pic':
                case 'file':
                    if (!$this->getData($f)) {
                        $this->setData($f, '');
                    }
                    break;

                case 'password':
                    if (
                        $this->getData($f) &&
                        $this->getData($f) == $this->getData($f . '2')
                    ) {
                        $this->processData($f, function ($v) use ($f) {
                            return $this->getModel()::hashPasswordFromRawToDb(
                                $v,
                                $f
                            );
                        });
                    } else {
                        $this->setData($f, $this->getModel()->getOrigData($f) ?: '');
                    }
                    break;

                case 'checkbox':
                    $this->setData($f, $this->getData($f) ? true : false);
                    break;

                case 'ip':
                    $this->processData($f, 'ip2bin');
                    break;

                case 'enum':
                    if (!in_array($this->getData($f), $v['values'])) {
                        $this->setData($f, $v['default'] ?? null);
                    }
                    break;
            }
        }

        return $this;
    }

    public function setJsonData()
    {
        if (!$this->jsonData) {
            return $this;
        }

        foreach ($this->jsonData as $masterField => $data) {
            foreach ($data as $subField => $value) {
                $this->getModel()->updateJsonData($masterField, $subField, $value);
            }
        }

        return $this;
    }

    /** @deprecated */
    public function store_data()
    {
        return $this->storeData();
    }

    public function storeData()
    {
        $dynamicFields = [];
        $dynamicPicsFields = [];

        foreach ($this->_all_fields as $f => $v) {
            if (in_array($v['type'], ['dynamic_pics', 'dynamic_files'])) {
                $dynamicPicsFields[] = $f;
            } elseif ($v['type'] == 'dynamic' || substr($v['type'], -2) === '[]') {
                $dynamicFields[] = $f;
            }
        }

        $orig = $this->getModel()->getOrigWithId();

        $this->setJsonData();

        $this->getModel()
            ->save()
            ->setOrigData($orig);

        if ($this->AdminPage) {
            $this->AdminPage->setId($this->getId());
        }

        $this->set_redirect_param('id', $this->getId());

        foreach ($dynamicPicsFields as $f) {
            $this->store_dynamic_pics($f);
        }

        foreach ($dynamicFields as $f) {
            $this->storeDynamic($f);
        }

        foreach ($this->_all_fields as $f => $v) {
            switch ($v['type']) {
                case 'checkboxes':
                    $saver = $this->getFieldProperty($f, 'saverAfterSubmit');
                    if ($saver) {
                        $saver($this, $f);
                    }
                    break;

                case 'tags':
                    $this->storeTags($f);
                    break;
            }
        }

        return $this->getId();
    }

    function storeTags($field)
    {
        /** @var \diTags $class */
        $class = $this->getFieldOption($field, 'class') ?: 'diTags';

        $class::saveFromPost(
            \diTypes::getId($this->getTable()),
            $this->getId(),
            $field
        );

        return $this;
    }

    public function getOptionsFor($field)
    {
        return $this->getFieldOption($field);
    }

    public function getWatermarkOptionsFor($field, $type)
    {
        $opts = $this->getOptionsFor($field);

        if ($opts && isset($opts['watermarks'])) {
            foreach ($opts['watermarks'] as $o) {
                if (isset($o['type']) && $o['type'] == $type) {
                    return $o;
                }
            }
        }

        return [
            'name' => null,
            'x' => null,
            'y' => null,
        ];
    }

    public function getFieldProperty($field, $property = null)
    {
        $o = isset($this->_all_fields[$field])
            ? (array) $this->_all_fields[$field]
            : [];

        return is_null($property) ? $o : $o[$property] ?? null;
    }

    public function getFieldOption($field, $option = null)
    {
        $o = isset($this->_all_fields[$field]['options'])
            ? (array) $this->_all_fields[$field]['options']
            : [];

        return is_null($option) ? $o : $o[$option] ?? null;
    }

    /** @deprecated */
    public function is_flag($field, $flag)
    {
        return $this->isFlag($field, $flag);
    }

    public function isFlag($field, $flag)
    {
        if (is_string($field) && isset($this->_all_fields[$field]['flags'])) {
            $f_ar = $this->_all_fields[$field]['flags'];
        } elseif (is_array($field) && isset($field['flags'])) {
            $f_ar = $field['flags'];
        } else {
            return false;
        }

        return is_array($f_ar) ? in_array($flag, $f_ar) : $f_ar == $flag;
    }

    public function getOriginForSlug()
    {
        return $this->getModel()->getSourceForSlug() ?:
            self::$defaultSlugSourceFieldsAr;
    }

    public function getSlugFieldName()
    {
        return $this->slugFieldName;
    }

    public function makeSlug($origin = null, $slugField = null, $extraOptions = [])
    {
        if (is_array($origin) && !$extraOptions) {
            $extraOptions = $origin;
            $origin = null;
        }

        $slugField = $slugField ?: $this->slugFieldName;

        if (is_null($origin)) {
            $origin = $this->getOriginForSlug();
        }

        if (is_array($origin)) {
            foreach ($origin as $field) {
                if ($origin = $this->getData($field)) {
                    break;
                }
            }
        }

        $this->setData(
            $slugField,
            Slug::generate(
                $origin,
                $this->getTable(),
                $this->getId(),
                $this->getModel()::getIdFieldName(),
                $slugField,
                $this->getModel()::slug_delimiter,
                extend($extraOptions, [
                    'db' => $this->getModel()
                        ::getConnection()
                        ->getDb(),
                ])
            )
        );

        return $this;
    }

    public function makeOrderAndLevelNum()
    {
        if (!$this->getId()) {
            $h = new \diHierarchyTable($this->getTable());

            $skipIdsAr = $this->getData('parent')
                ? $h->getChildrenIdsAr($this->getData('parent'), [
                    $this->getData('parent'),
                ])
                : [];

            $r = $this->getDb()->r(
                $this->getTable(),
                $skipIdsAr ?: '',
                'MAX(order_num) AS num'
            );

            $this->setData(
                'level_num',
                $h->getChildLevelNum($this->getData('parent'))
            )->setData('order_num', (int) $r->num + 1);

            $this->getDb()->update(
                $this->getTable(),
                [
                    '*order_num' => 'order_num + 1',
                ],
                'WHERE ' .
                    $this->getDb()->escapeFieldValue(
                        'order_num',
                        $this->getData('order_num'),
                        '>='
                    )
            );
        } else {
            $r = $this->getDb()->r(
                $this->getTable(),
                $this->getId(),
                'level_num,order_num'
            );
            if ($r) {
                $this->setData('level_num', $r->level_num)->setData(
                    'order_num',
                    $r->order_num
                );
            }
        }

        return $this;
    }

    static function get_datetime_from_ar(
        $post,
        $date = true,
        $time = false,
        $format = 'int'
    ) {
        $ar = getdate();

        if ($date) {
            if (isset($post['dd'])) {
                $ar['mday'] = (int) $post['dd'];
            }
            if (isset($post['dm'])) {
                $ar['mon'] = (int) $post['dm'];
            }
            if (isset($post['dy'])) {
                $ar['year'] = (int) $post['dy'];
            }
        }

        if ($time) {
            if (isset($post['th'])) {
                $ar['hours'] = (int) $post['th'];
            }
            if (isset($post['tm'])) {
                $ar['minutes'] = (int) $post['tm'];
            }
            /*
            if (isset($post['ts'])) {
                $ar['seconds'] = (int) $post['ts'];
            }
            */
        }

        $ar['seconds'] = 0;
        $value = null;

        if (
            ($date && $ar['mday'] && $ar['mon'] && $ar['year']) ||
            ($time && $post['th'] !== '' && $post['tm'] !== '')
        ) {
            $value = mktime(
                $ar['hours'],
                $ar['minutes'],
                $ar['seconds'],
                $ar['mon'],
                $ar['mday'],
                $ar['year']
            );
        }

        /*
		$value = !$date || ()
			? mktime($ar['hours'], $ar['minutes'], $ar['seconds'], $ar['mon'], $ar['mday'], $ar['year'])
			: 0;
		*/

        if ($format == 'str') {
            $tpl = [];

            if ($date) {
                $tpl[] = 'Y-m-d';
            }

            if ($time) {
                $tpl[] = 'H:i:s';
            }

            $value = $value ? date(join(' ', $tpl), $value) : null;
        }

        return $value;
    }

    function make_datetime($field, $date = true, $time = false)
    {
        if ($this->isFlag($field, 'static') || $this->isFlag($field, 'hidden')) {
            $default =
                substr($this->_all_fields[$field]['type'], -4) == '_str' ? '' : 0;

            $this->setData(
                $field,
                \diRequest::post(self::formatName($field), $default)
            );
        } else {
            $this->setData(
                $field,
                $this->get_datetime_from_ar(
                    \diRequest::post(self::formatName($field), []),
                    $date,
                    $time,
                    substr($this->_all_fields[$field]['type'], -4) == '_str'
                        ? 'str'
                        : 'int'
                )
            );
        }

        return $this;
    }

    public static function getFolderByImageType($type)
    {
        global $big_folder, $orig_folder;

        switch ($type) {
            case self::IMAGE_TYPE_MAIN:
                return '';

            case self::IMAGE_TYPE_BIG:
                return $big_folder ?? 'big/';

            case self::IMAGE_TYPE_PREVIEW:
            case self::IMAGE_TYPE_PREVIEW2:
            case self::IMAGE_TYPE_PREVIEW3:
            case self::IMAGE_TYPE_PREVIEW4:
            case self::IMAGE_TYPE_PREVIEW5:
                return $GLOBALS[
                    'tn' .
                        ($type != self::IMAGE_TYPE_PREVIEW ? $type : '') .
                        '_folder'
                ] ??
                    'preview' .
                        ($type != self::IMAGE_TYPE_PREVIEW ? $type : '') .
                        '/';

            case self::IMAGE_TYPE_ORIG:
                return $orig_folder ?? 'orig/';
        }

        throw new \Exception("No folder for image type '$type' defined");
    }

    /**
     * @param $field
     * @param callable|string|array|null $callbackOrFolder
     * @return Submit
     * @throws \Exception
     */
    public function storeFile($field, $callbackOrFolder = null)
    {
        return $this->storeImage($field, $callbackOrFolder);
    }

    public static function prepareFileOptions(
        $field,
        $filesOptions,
        \diModel $model,
        $table = null
    ) {
        if (!$filesOptions) {
            $filesOptions = [[]];
        }

        foreach ($filesOptions as &$opts) {
            $widthParam = Configuration::getDimensionParam(
                'width',
                $table ?: $model->getTable(),
                $field,
                $opts['type'] ?? self::IMAGE_TYPE_MAIN
            );
            $heightParam = Configuration::getDimensionParam(
                'height',
                $table ?: $model->getTable(),
                $field,
                $opts['type'] ?? self::IMAGE_TYPE_MAIN
            );

            $defaultOpt = [
                'type' => self::IMAGE_TYPE_MAIN,
                'folder' =>
                    $model->getPicsFolder() ?:
                    get_pics_folder(
                        $table ?: $model->getTable(),
                        Config::getUserAssetsFolder()
                    ),
                'subfolder' => null,
                'resize' => null,
                'quality' => null,
                'afterSave' => null,
                'watermark' => [
                    'name' => null,
                    'x' => null,
                    'y' => null,
                ],
                'width' => Configuration::safeGet($widthParam),
                'height' => Configuration::safeGet($heightParam),
            ];

            $opts = extend($defaultOpt, $opts);

            if (
                $opts['type'] != self::IMAGE_TYPE_MAIN &&
                is_null($opts['subfolder'])
            ) {
                $opts['subfolder'] = self::getFolderByImageType($opts['type']);
            }
        }

        return $filesOptions;
    }

    protected function tryToEmulateChunkFile($field)
    {
        $f = self::formatName($field);
        $tmpFilename = \diRequest::post('__uploaded__' . $f);
        $origFilename = \diRequest::post('__orig_filename__' . $f);
        $tmpPath = get_tmp_folder() . $this->getTable() . '/' . $field . '/';

        if ($tmpFilename && $origFilename) {
            $_FILES[$f] = [
                'name' => $origFilename,
                'tmp_name' => \diPaths::fileSystem() . $tmpPath . $tmpFilename,
                'error' => 0,
                'size' => filesize(\diPaths::fileSystem() . $tmpPath . $tmpFilename),
            ];
        }

        return $this;
    }

    public function storeImage($fields, $filesOptions = [])
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $f) {
            $this->tryToEmulateChunkFile($f);
        }

        $hasFilesOptions =
            $filesOptions ||
            $this->getModel()->getPicStoreSettings(current($fields));

        // back compatibility
        if (
            is_callable($filesOptions) ||
            !$hasFilesOptions ||
            (is_string($filesOptions) && $filesOptions)
        ) {
            return $filesOptions
                ? $this->store_pics($fields, $filesOptions)
                : $this->store_pics($fields);
        }

        $callback = [static::class, 'storeImageCallback'];

        if (!is_callable($callback)) {
            throw new \Exception(
                'Callback is not callable: ' . print_r($callback, true)
            );
        }

        foreach ($fields as $f) {
            $field = self::formatName($f);

            $fieldFileOptions = self::prepareFileOptions(
                $f,
                $filesOptions ?: $this->getModel()->getPicStoreSettings($f) ?: [],
                $this->getModel(),
                $this->getTable()
            );
            $baseFolder = $fieldFileOptions[0]['folder'];

            if (!empty($_FILES[$field]) && empty($_FILES[$field]['error'])) {
                $oldExt = mb_strtolower(
                    StringHelper::fileExtension($this->getData($f))
                );
                $newExt = mb_strtolower(
                    StringHelper::fileExtension($_FILES[$field]['name'])
                );

                if (!$this->getData($f)) {
                    $this->generateFilename(
                        $f,
                        $baseFolder,
                        $_FILES[$field]['name']
                    );
                } elseif ($oldExt != $newExt) {
                    $this->setData(
                        $f,
                        StringHelper::replaceFileExtension(
                            $this->getData($f),
                            $newExt
                        )
                    );
                }

                foreach ($fieldFileOptions as &$opts) {
                    FileSystemHelper::createTree(
                        \diPaths::fileSystem($this->getModel(), true, $f),
                        $opts['folder'] . $opts['subfolder'],
                        self::DIR_CHMOD
                    );

                    $widthParam = Configuration::getDimensionParam(
                        'width',
                        $this->getTable(),
                        $f,
                        $opts['type']
                    );
                    $heightParam = Configuration::getDimensionParam(
                        'height',
                        $this->getTable(),
                        $f,
                        $opts['type']
                    );

                    $resultWidth = Configuration::safeGet($widthParam);
                    $resultHeight = Configuration::safeGet($heightParam);

                    if (!empty($opts['rule'])) {
                        [$sourceWidth, $sourceHeight] = getimagesize(
                            $_FILES[$field]['tmp_name']
                        );

                        [$resultWidth, $resultHeight] = self::getResultDimensions([
                            'widthParam' => $widthParam,
                            'heightParam' => $heightParam,
                            'rule' => $opts['rule'],
                            'sourceWidth' => $sourceWidth,
                            'sourceHeight' => $sourceHeight,
                        ]);
                    }

                    if (empty($opts['width'])) {
                        $opts['width'] = $resultWidth;
                    }

                    if (empty($opts['height'])) {
                        $opts['height'] = $resultHeight;
                    }

                    /*
                    $opts = extend($opts, [
                        'width' => $resultWidth,
                        'height' => $resultHeight,
                    ]);
                    */
                }

                $callback($this, $f, $fieldFileOptions, $_FILES[$field]);
            }
        }

        return $this;
    }

    public static function cleanFilename($filename)
    {
        // do we need "!" in filenames?
        return preg_replace('/[^a-zA-Z0-9.()_-]/', '', $filename);
    }

    public static function getFilenameFromTitle(\diModel $m, $fields)
    {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $source =
            ArrayHelper::someValue(
                $fields,
                function ($field) use ($m) {
                    return $m->get($field);
                },
                true
            ) ?? '';

        return self::cleanFilename(transliterate_rus_to_eng($source));
    }

    public static function getGeneratedFilename(
        $folder,
        $origFilename,
        $naming = self::FILE_NAMING_ORIGINAL,
        $maxLen = 0
    ) {
        $baseName =
            self::cleanFilename(
                transliterate_rus_to_eng(StringHelper::fileBaseName($origFilename))
            ) ?:
            get_unique_id(self::FILE_NAME_RANDOM_LENGTH);
        $endingIdx = 0;
        $extension = '.' . strtolower(StringHelper::fileExtension($origFilename));

        $extLen = mb_strlen($extension);
        $maxBaseLen = $maxLen ? min(mb_strlen($baseName), $maxLen - $extLen) : 0;

        do {
            switch ($naming) {
                default:
                case self::FILE_NAMING_RANDOM:
                    $filename = get_unique_id(self::FILE_NAME_RANDOM_LENGTH);
                    break;

                case self::FILE_NAMING_ORIGINAL:
                    $filename = $baseName;
                    $suffix = $endingIdx ? self::FILE_NAME_GLUE . $endingIdx : '';
                    $suffixLen = mb_strlen($suffix);
                    $maxFnLen = $maxBaseLen - $suffixLen;

                    if ($maxBaseLen && mb_strlen($filename) > $maxFnLen) {
                        $filename = mb_substr($filename, 0, $maxFnLen);
                    }

                    if ($endingIdx) {
                        $filename .= self::FILE_NAME_GLUE . $endingIdx;
                    }

                    $endingIdx++;
                    break;
            }
        } while (is_file($folder . $filename . $extension));

        return $filename . $extension;
    }

    public static function cleanGeneratedFilename($fn)
    {
        $ext = StringHelper::fileExtension($fn);
        $fn = StringHelper::fileBaseName($fn);
        $fn = preg_replace('#' . self::FILE_NAME_GLUE . '\d+#', '', $fn);

        return $fn . $ext;
    }

    protected function generateFilename($field, $folder, $origFilename)
    {
        $this->setData(
            $field,
            self::getGeneratedFilename(
                \diPaths::fileSystem($this->getModel(), true, $field) . $folder,
                $origFilename,
                $this->getFieldProperty($field, 'naming'),
                $this->getFieldOption($field, 'maxNameLength')
            )
        );

        return $this;
    }

    // $callback is a function($_FILES[$f], $field, $pics_folder, $fn, &$this)
    public function store_pics($picFields, $callbackOrFolder = null)
    {
        $defaultCallback = [static::class, 'storeFileCallback'];

        $callback = is_callable($callbackOrFolder)
            ? $callbackOrFolder
            : $defaultCallback;
        $folder =
            is_callable($callbackOrFolder) || !$callbackOrFolder
                ? ($this->getModel()->getPicsFolder() ?:
                get_pics_folder(
                    $this->getTable() ?: $this->getModel()->getTable(),
                    Config::getUserAssetsFolder()
                ))
                : $callbackOrFolder;

        if (!is_array($picFields)) {
            $picFields = explode(',', $picFields);
        }

        FileSystemHelper::createTree(
            \diPaths::fileSystem($this->getModel(), true, $picFields[0]),
            $folder . get_tn_folder(),
            self::DIR_CHMOD
        );

        foreach ($picFields as $field) {
            if (!$field) {
                continue;
            }

            $f = self::formatName($field);
            //$this->setData($field, $this->getCurRec($field));

            if (isset($_FILES[$f]) && !$_FILES[$f]['error']) {
                $oldExt = $this->getData($field)
                    ? strtolower(StringHelper::fileExtension($this->getData($field)))
                    : '';
                $newExt = strtolower(
                    StringHelper::fileExtension($_FILES[$f]['name'])
                );

                if (!$this->getData($field)) {
                    $this->generateFilename($field, $folder, $_FILES[$f]['name']);
                } elseif ($oldExt != $newExt) {
                    $this->setData(
                        $field,
                        StringHelper::replaceFileExtension(
                            $this->getData($field),
                            $newExt
                        )
                    );
                }

                // new arguments order for static method callback
                if (is_array($callback)) {
                    $callback(
                        $this,
                        $field,
                        [
                            'folder' => $folder,
                        ],
                        $_FILES[$f]
                    );
                } else {
                    $callback(
                        $_FILES[$f],
                        $field,
                        $folder,
                        $this->getData($field),
                        $this
                    );
                }
            }
        }

        return $this;
    }

    public static function checkBase64Files($field, $id)
    {
        if (
            empty($_FILES[$field]['name'][$id]) &&
            !empty($_POST['base64_' . $field][$id])
        ) {
            $base64 = $_POST['base64_' . $field][$id];
            preg_match("#^data:(.+/[^;]+);base64,(.+)$#", $base64, $regs);
            $raw = $regs[2];

            $_FILES[$field]['name'][$id] = 'clipboard-image.png';
            $_FILES[$field]['type'][$id] = $regs[1];
            $_FILES[$field]['tmp_name'][$id] = tempnam(
                sys_get_temp_dir(),
                'clipboard-image'
            );
            $_FILES[$field]['error'][$id] = 0;

            if (!$_FILES[$field]['tmp_name'][$id]) {
                throw new \Exception(
                    'Unable to create temporary file for clipboard image'
                );
            }

            file_put_contents($_FILES[$field]['tmp_name'][$id], base64_decode($raw));

            $_FILES[$field]['size'][$id] = filesize(
                $_FILES[$field]['tmp_name'][$id]
            );

            unset($_POST['base64_' . $field][$id]);
        }
    }

    private function store_dynamic_pics($field)
    {
        if (empty($_POST["{$field}_order_num"])) {
            return $this;
        }

        $ar = $_POST["{$field}_order_num"];
        $pics_folder = get_pics_folder($this->getTable());

        $root = \diPaths::fileSystem($this->getModel());

        $ids_ar = [];

        FileSystemHelper::createTree(
            $root,
            [
                $pics_folder . get_tn_folder(),
                $pics_folder . get_tn_folder(2),
                $pics_folder . get_tn_folder(3),
            ],
            self::DIR_CHMOD
        );

        $w = "_table='{$this->getTable()}' and _field='$field' and _id='{$this->getId()}'";

        foreach ($ar as $id => $order_num) {
            if (!(int) $id) {
                continue;
            }

            $test_r =
                $id > 0
                    ? $this->getDb()->r(
                        self::dynamicPicsTable,
                        "WHERE $w and id='$id'"
                    )
                    : false;

            $db_ar = [
                'order_num' => (int) $order_num,
                'by_default' =>
                    \diRequest::post($field . '_by_default') == $id ? 1 : 0,
                'visible' => !empty($_POST[$field . '_visible'][$id]) ? 1 : 0,
                'title' => isset($_POST[$field . '_title'][$id])
                    ? StringHelper::in($_POST[$field . '_title'][$id])
                    : '',
                'content' => isset($_POST[$field . '_content'][$id])
                    ? StringHelper::in($_POST[$field . '_content'][$id])
                    : '',
            ];

            if (isset($_POST[$field . '_alt_title'][$id])) {
                $db_ar['alt_title'] = StringHelper::in(
                    $_POST[$field . '_alt_title'][$id]
                );
            }

            if (isset($_POST[$field . '_html_title'][$id])) {
                $db_ar['html_title'] = StringHelper::in(
                    $_POST[$field . '_html_title'][$id]
                );
            }

            // pic
            $f = 'pic';
            $varName = $field . '_' . $f;

            self::checkBase64Files($varName, $id);

            if (
                isset($_FILES[$varName]['name'][$id]) &&
                !$_FILES[$varName]['error'][$id]
            ) {
                $ext =
                    '.' .
                    strtolower(
                        StringHelper::fileExtension($_FILES[$varName]['name'][$id])
                    );

                if ($test_r && $test_r->$f) {
                    $db_ar[$f] = StringHelper::replaceFileExtension(
                        $test_r->$f,
                        $ext
                    );
                } else {
                    $db_ar[$f] = self::getGeneratedFilename(
                        \diPaths::fileSystem($this->getModel()) . $pics_folder,
                        $_FILES[$varName]['name'][$id],
                        $this->getFieldProperty($field, 'naming'),
                        $this->getFieldOption($field, 'maxNameLength')
                    );
                }

                $db_ar['orig_fn'] = StringHelper::in($_FILES[$varName]['name'][$id]);

                $callback =
                    $this->_all_fields[$field]['callback'] ??
                    self::$defaultDynamicPicCallback;

                $F = [
                    'name' => $_FILES[$varName]['name'][$id],
                    'type' => $_FILES[$varName]['type'][$id],
                    'tmp_name' => $_FILES[$varName]['tmp_name'][$id],
                    'error' => $_FILES[$varName]['error'][$id],
                    'size' => $_FILES[$varName]['size'][$id],
                ];

                if (is_callable($callback)) {
                    $callback(
                        $F,
                        $this,
                        [
                            'field' => $field,
                            'what' => $f,
                            'custom' => false,
                        ],
                        $db_ar,
                        $pics_folder
                    );
                }
            }
            //

            // pic tn
            $f = 'pic_tn';
            $varName = $field . '_' . $f;

            self::checkBase64Files($varName, $id);

            if (
                isset($_FILES[$varName]['name'][$id]) &&
                !$_FILES[$varName]['error'][$id]
            ) {
                if ($test_r && $test_r->$f) {
                    $db_ar[$f] = $test_r->$f;
                } else {
                    $db_ar[$f] = self::getGeneratedFilename(
                        \diPaths::fileSystem($this->getModel()) . $pics_folder,
                        $_FILES[$varName]['name'][$id],
                        $this->getFieldProperty($field, 'naming'),
                        $this->getFieldOption($field, 'maxNameLength')
                    );
                }

                $callback = isset($this->_all_fields[$field]['callback'])
                    ? $this->_all_fields[$field]['callback'] . '_tn'
                    : '';

                $F = [
                    'name' => $_FILES[$varName]['name'][$id],
                    'type' => $_FILES[$varName]['type'][$id],
                    'tmp_name' => $_FILES[$varName]['tmp_name'][$id],
                    'error' => $_FILES[$varName]['error'][$id],
                    'size' => $_FILES[$varName]['size'][$id],
                ];

                if ($callback && is_callable($callback)) {
                    $callback(
                        $F,
                        $this,
                        [
                            'field' => $field,
                            'what' => $f,
                        ],
                        $db_ar,
                        $pics_folder
                    );
                }
            }
            //

            $callback = $this->_all_fields[$field]['after_submit_callback'] ?? null;

            if (is_callable($callback)) {
                $callback($id, $field, $test_r, $db_ar, $this);
            }

            $db_ar = array_intersect_key(
                $db_ar,
                array_flip(self::$allowedDynamicPicsFieldsAr)
            );

            if ($test_r) {
                $this->getDb()->update(
                    self::dynamicPicsTable,
                    $db_ar,
                    $test_r->id
                ) or $this->getDb()->dierror();

                $ids_ar[] = $test_r->id;
            } else {
                $db_ar['_table'] = $this->getTable();
                $db_ar['_field'] = $field;
                $db_ar['_id'] = $this->getId();

                ($ids_ar[] = $this->getDb()->insert(
                    self::dynamicPicsTable,
                    $db_ar
                )) or $this->getDb()->dierror();
            }
        }

        // it's killing time!
        $killCol = DynamicPics::createByTarget(
            $this->getTable(),
            $this->getId(),
            $field
        );
        $killCol->filterById($ids_ar, '!=')->hardDestroy();

        // making order num to look ok
        $order_num = 0;

        $orderCol = DynamicPics::createByTarget(
            $this->getTable(),
            $this->getId(),
            $field
        );
        $orderCol->orderByOrderNum()->orderById();

        /** @var \diCore\Entity\DynamicPic\Model $m */
        foreach ($orderCol as $m) {
            $m->setOrderNum(++$order_num)->save();
        }

        return $this;
    }

    public static function rebuildDynamicPics($module, $field = null, $id = null)
    {
        $Submit = new self(BasePage::liteCreate($module));
        $callback =
            $Submit->_all_fields[$field]['callback'] ??
            self::$defaultDynamicPicCallback;
        $id = (int) $id;
        $ar = [];

        $pics = DynamicPics::create()->filterByTargetTable($Submit->getTable());

        if ($field) {
            $pics->filterByTargetField($field);
        }

        if ($id) {
            $pics->filterByTargetId($id);
        }

        /** @var \diCore\Entity\DynamicPic\Model $pic */
        foreach ($pics as $pic) {
            $fn =
                \diPaths::fileSystem() .
                get_pics_folder($Submit->getTable()) .
                get_orig_folder() .
                $pic->getPic();

            $ar[] = $fn;

            $F = [
                'name' => $pic->getOrigFn(),
                'type' => 'image/jpeg',
                'tmp_name' => $fn,
                'error' => 0,
                'size' => filesize($fn),
            ];

            $update = [
                'pic' => $pic->getPic(),
            ];

            if (is_callable($callback)) {
                $callback(
                    $F,
                    $Submit,
                    [
                        'field' => $pic->getTargetField(),
                        'what' => 'pic',
                    ],
                    $update,
                    get_pics_folder($Submit->getTable())
                );
            }

            $pic->set($update)->save();
        }

        return $ar;
    }

    protected function storeDynamic($field)
    {
        $dr = new \diDynamicRows($this->AdminPage, $field);

        $liteValues = $dr->submit();

        if ($dr->isLite()) {
            $this->setData($field, $liteValues);
            $this->setJsonData();
            $this->getModel()->save();
        }

        return $this;
    }

    /**
     * @param $obj Submit
     * @param $field string
     * @param $options array
     * @param $F array
     */
    public static function storeImageCallback(&$obj, $field, $options, $F)
    {
        $origType = $F['diOrigType'] ?? null;
        $mode =
            $origType !== null
                ? self::IMAGE_STORE_MODE_REBUILD
                : self::IMAGE_STORE_MODE_UPLOAD;
        $needToUnlink = $mode == self::IMAGE_STORE_MODE_UPLOAD;

        if (\diCore\Admin\Config::shouldSubmitClearExif()) {
            ImageHelper::clearExifAndFix($F['tmp_name']);
        }

        $I = new \diImage();
        $I->open($F['tmp_name']);

        foreach ($options as $opts) {
            if (
                $mode === self::IMAGE_STORE_MODE_REBUILD &&
                $opts['type'] === $origType
            ) {
                continue;
            }

            $suffix = Submit::getPreviewSuffix($opts['type']);
            $fileExt = StringHelper::fileExtension($obj->getData($field));
            $isSvg = in_array($fileExt, ['svg']);
            $resizeAvailable = !$isSvg;
            $forceReSave = false;

            if (!empty($opts['forceFormat']) && !$isSvg) {
                $I->setDstType($opts['forceFormat']);

                $oldType = \diImage::getTypeByExt($fileExt);
                if ($oldType != $I->getDstType()) {
                    $forceReSave = true;
                }

                $fileExt = \diImage::typeExt($I->getDstType());
                $name = StringHelper::replaceFileExtension(
                    $obj->getData($field),
                    $fileExt
                );

                $obj->setData($field, $name);
            }

            $fn =
                \diPaths::fileSystem($obj->getModel(), true, $field) .
                $opts['folder'] .
                $opts['subfolder'] .
                $obj->getData($field);

            if (is_file($fn)) {
                unlink($fn);

                if (is_file($fn . \diImage::EXT_WEBP)) {
                    unlink($fn . \diImage::EXT_WEBP);
                }
            }

            if (!$forceReSave && (!$opts['resize'] || !$resizeAvailable)) {
                copy($F['tmp_name'], $fn);
            } else {
                if (!empty($opts['quality'])) {
                    $I->set_jpeg_quality($opts['quality']);
                }

                $forceReSave
                    ? $I->make_thumb(
                        $opts['resize'],
                        $fn,
                        $opts['width'],
                        $opts['height'],
                        false,
                        $opts['watermark']['name'],
                        $opts['watermark']['x'],
                        $opts['watermark']['y']
                    )
                    : $I->make_thumb_or_copy(
                        $opts['resize'],
                        $fn,
                        $opts['width'],
                        $opts['height'],
                        false,
                        $opts['watermark']['name'],
                        $opts['watermark']['x'],
                        $opts['watermark']['y']
                    );
            }

            chmod($fn, Submit::FILE_CHMOD);

            [$w, $h, $t] = getimagesize($fn);

            $obj->setData([
                $field . $suffix . '_w' => (int) $w,
                $field . $suffix . '_h' => (int) $h,
                $field . $suffix . '_t' => (int) $t,
            ]);

            if (!empty($opts['afterSave'])) {
                $afterSave = $opts['afterSave'];

                if (is_callable($afterSave)) {
                    $afterSave($field, $fn, $obj->getModel());
                }
            }
        }

        $I->close();
        unset($I);

        if ($needToUnlink) {
            unlink($F['tmp_name']);
        }
    }

    /**
     * @param $obj Submit
     * @param $field string
     * @param $options array
     * @param $F array
     */
    public static function storeFileCallback(&$obj, $field, $options, $F)
    {
        $options = extend(
            [
                'folder' => '',
                'subfolder' => '',
                'filename' => '',
            ],
            $options
        );

        $fn =
            \diPaths::fileSystem($obj->getModel(), true, $field) .
            $options['folder'] .
            $options['subfolder'] .
            ($options['filename'] ?: $obj->getData($field));

        if (is_file($fn)) {
            unlink($fn);
        }

        FileSystemHelper::createTree(
            \diPaths::fileSystem(),
            [$options['folder'] . $options['subfolder']],
            self::DIR_CHMOD
        );

        if (
            !(
                @move_uploaded_file($F['tmp_name'], $fn) ||
                @rename($F['tmp_name'], $fn)
            )
        ) {
            throw new \Exception("Unable to copy file {$F['tmp_name']} to $fn");
        }

        chmod($fn, self::FILE_CHMOD);

        $info = getimagesize($fn);
        if ($info) {
            $obj->setData($field . '_w', $info[0])
                ->setData($field . '_h', $info[1])
                ->setData($field . '_t', $info[2]);
        }
    }

    public static function getResultDimensions($opts)
    {
        $opts = extend(
            [
                'widthParam' => null,
                'heightParam' => null,
                'rule' => null,
                'sourceWidth' => null,
                'sourceHeight' => null,
            ],
            $opts
        );

        if ($opts['widthParam'] || $opts['heightParam']) {
            $resultWidth = Configuration::safeGet($opts['widthParam']);
            $resultHeight = Configuration::safeGet($opts['heightParam']);
        } elseif ($opts['rule']) {
            switch ($opts['rule']) {
                case Submit::IMAGE_RULE_DIVIDE_BY_2:
                    $resultWidth = round($opts['sourceWidth'] / 2);
                    $resultHeight = round($opts['sourceHeight'] / 2);
                    break;

                case Submit::IMAGE_RULE_DIVIDE_BY_3:
                    $resultWidth = round($opts['sourceWidth'] / 3);
                    $resultHeight = round($opts['sourceHeight'] / 3);
                    break;
            }
        }

        return [$resultWidth ?? 0, $resultHeight ?? 0];
    }

    public static function storeDynamicPicCallback(
        $F,
        $tableOrSubmit,
        $options,
        &$ar,
        $folder
    ) {
        if (is_object($tableOrSubmit)) {
            /** @var Submit $Submit */
            $Submit = $tableOrSubmit;
            $table = $Submit->getTable();
        } else {
            $Submit = null;
            $table = $tableOrSubmit;
        }

        if (is_array($options)) {
            $options = extend(
                [
                    'field' => null,
                    'what' => null,
                    'custom' => true,
                    'group_field' => null,
                    'data_table' => null,
                ],
                $options
            );

            $field = $options['field'];
            $groupField = $options['group_field'];
            $dataTable = $options['data_table'];
            $what = $options['what'];
            $isCustom = $options['custom'];
        } else {
            $what = $options;
            $field = null;
            $groupField = null;
            $dataTable = null;
            $options = [];
            $isCustom = true;
        }

        if (empty($options['fileOptions'])) {
            $testModel = \diModel::createForTableNoStrict($dataTable);
            $defaultFileOptions = $testModel::getPicStoreSettings($field) ?: [];

            $options = extend($options, [
                'fileOptions' => $defaultFileOptions,
            ]);
        }

        $getFileOptions = function ($imageType) use ($options) {
            $type = static::parseImageType($imageType);

            if ($options['fileOptions']) {
                foreach ($options['fileOptions'] as $o) {
                    if (isset($o['type']) && $o['type'] == $type) {
                        return $o;
                    }
                }
            }

            return [];
        };

        $getDimensionName = function ($side, $suffix = '') use (
            $table,
            $groupField,
            $field
        ) {
            $suffix2 = $suffix ? "_$suffix" : '';

            return Configuration::exists([
                "{$table}__{$groupField}__$field{$suffix2}__$side",
                "{$table}_{$groupField}_$field{$suffix}_$side",
                "{$table}__$groupField{$suffix2}__$side",
                "{$table}_$groupField{$suffix}_$side",
                "{$groupField}__$field{$suffix2}__$side",
                "{$groupField}_$field{$suffix}_$side",
                "$groupField{$suffix2}__$side",
                "$groupField{$suffix}_$side",
                "$table{$suffix2}__$side",
                "$table{$suffix}_$side",
            ]);
        };

        $getDimensionPair = fn($suffix = '') => [
            $getDimensionName('width', $suffix),
            $getDimensionName('height', $suffix),
        ];

        $fn = $ar[$what];
        $root = \diPaths::fileSystem();
        $full_fn = $root . $folder . $fn;
        $big_fn = $root . $folder . get_big_folder() . $fn;
        $orig_fn = $root . $folder . get_orig_folder() . $fn;
        $mode =
            $F['tmp_name'] == $orig_fn
                ? self::IMAGE_STORE_MODE_REBUILD
                : self::IMAGE_STORE_MODE_UPLOAD;

        if ($mode == self::IMAGE_STORE_MODE_UPLOAD) {
            if (is_file($full_fn)) {
                unlink($full_fn);
            }
            if (is_file($big_fn)) {
                unlink($big_fn);
            }
            if (is_file($orig_fn)) {
                unlink($orig_fn);
            }
        }

        [$sourceWidth, $sourceHeight, $imgType] = getimagesize($F['tmp_name']);

        if (\diImage::isImageType($imgType)) {
            $I = new \diImage();
            $I->open($F['tmp_name']);

            // thumbnail photos
            for ($i = 1; $i < 10; $i++) {
                $fOpts = $getFileOptions($i);
                // this tn needed if it's in list or for dipics
                $needed = $fOpts || !$isCustom;

                if (!$needed) {
                    continue;
                }

                $suffix = $i > 1 ? "$i" : '';
                [$widthParam, $heightParam] = $getDimensionPair("_tn$suffix");

                if ($widthParam || $heightParam || !empty($fOpts['rule'])) {
                    $tn_fn = $root . $folder . get_tn_folder($i) . $fn;

                    if ($mode == self::IMAGE_STORE_MODE_UPLOAD) {
                        if (is_file($tn_fn)) {
                            unlink($tn_fn);
                        }
                    }

                    $fileOptionsTn = extend(
                        [
                            'resize' => DI_THUMB_CROP, //| DI_THUMB_EXPAND_TO_SIZE
                            'rule' => null,
                            'watermark' => [],
                        ],
                        $fOpts
                    );
                    $tnWM = $Submit->getWatermarkOptionsFor(
                        $field,
                        constant('self::IMAGE_TYPE_PREVIEW' . $suffix)
                    );

                    if (!$tnWM['name']) {
                        $tnWM = extend($tnWM, $fileOptionsTn['watermark']);
                    }

                    [$resultWidth, $resultHeight] = self::getResultDimensions([
                        'widthParam' => $widthParam,
                        'heightParam' => $heightParam,
                        'rule' => $fileOptionsTn['rule'],
                        'sourceWidth' => $sourceWidth,
                        'sourceHeight' => $sourceHeight,
                    ]);

                    FileSystemHelper::createTree(
                        $root,
                        [$folder . get_tn_folder($i)],
                        self::DIR_CHMOD
                    );

                    $I->make_thumb_or_copy(
                        $fileOptionsTn['resize'],
                        $tn_fn,
                        $resultWidth,
                        $resultHeight,
                        false,
                        $tnWM['name'],
                        $tnWM['x'],
                        $tnWM['y']
                    );

                    chmod($tn_fn, self::FILE_CHMOD);
                    [
                        $ar['pic_tn' . $suffix . '_w'],
                        $ar['pic_tn' . $suffix . '_h'],
                        $ar['pic_tn' . $suffix . '_t'],
                    ] = getimagesize($tn_fn);
                }
            }

            // main photo
            $fOpts = $getFileOptions(self::IMAGE_TYPE_MAIN);
            // this tn needed if it's in list or for dipics
            $needed = $fOpts || !$isCustom;

            if ($needed) {
                [$widthParam, $heightParam] = $getDimensionPair();
                $fileOptionsMain = extend(
                    [
                        'resize' => DI_THUMB_FIT,
                        'rule' => null,
                        'watermark' => [],
                    ],
                    $fOpts
                );
                $mainWM = $Submit->getWatermarkOptionsFor(
                    $field,
                    self::IMAGE_TYPE_MAIN
                );
                if (!$mainWM['name']) {
                    $mainWM = extend($mainWM, $fileOptionsMain['watermark']);
                }

                [$resultWidth, $resultHeight] = self::getResultDimensions([
                    'widthParam' => $widthParam,
                    'heightParam' => $heightParam,
                    'rule' => $fileOptionsMain['rule'],
                    'sourceWidth' => $sourceWidth,
                    'sourceHeight' => $sourceHeight,
                ]);

                FileSystemHelper::createTree($root, [$folder], self::DIR_CHMOD);

                $I->make_thumb_or_copy(
                    $fileOptionsMain['resize'],
                    $full_fn,
                    $resultWidth,
                    $resultHeight,
                    false,
                    $mainWM['name'],
                    $mainWM['x'],
                    $mainWM['y']
                );
                chmod($full_fn, self::FILE_CHMOD);
            } elseif (!$fOpts) {
                if ($mode == self::IMAGE_STORE_MODE_UPLOAD) {
                    move_uploaded_file($F['tmp_name'], $full_fn) ||
                        rename($F['tmp_name'], $orig_fn);
                }
            }

            // big photo
            $fOpts = $getFileOptions(self::IMAGE_TYPE_BIG);
            // this tn needed if it's in list or for dipics
            $needed = $fOpts || !$isCustom;

            if ($needed) {
                [$widthParam, $heightParam] = $getDimensionPair('_big');
                $fileOptionsBig = extend(
                    [
                        'resize' => DI_THUMB_FIT,
                        'rule' => null,
                        'watermark' => [],
                    ],
                    $fOpts
                );
                $bigWM = $Submit->getWatermarkOptionsFor(
                    $field,
                    self::IMAGE_TYPE_BIG
                );
                if (!$bigWM['name']) {
                    $bigWM = extend($bigWM, $fileOptionsBig['watermark']);
                }

                [$resultWidth, $resultHeight] = self::getResultDimensions([
                    'widthParam' => $widthParam,
                    'heightParam' => $heightParam,
                    'rule' => $fileOptionsBig['rule'],
                    'sourceWidth' => $sourceWidth,
                    'sourceHeight' => $sourceHeight,
                ]);

                FileSystemHelper::createTree(
                    $root,
                    [$folder . get_big_folder()],
                    self::DIR_CHMOD
                );

                $I->make_thumb_or_copy(
                    $fileOptionsBig['resize'],
                    $big_fn,
                    $resultWidth ?: 10000,
                    $resultHeight ?: 10000,
                    false,
                    $bigWM['name'],
                    $bigWM['x'],
                    $bigWM['y']
                );
                chmod($big_fn, self::FILE_CHMOD);
            }

            $I->close();

            // orig photo
            $fOpts = $getFileOptions(self::IMAGE_TYPE_ORIG);
            // this tn needed if it's in list or for dipics
            $needed = $fOpts || !$isCustom;

            if ($needed) {
                if ($mode == self::IMAGE_STORE_MODE_UPLOAD) {
                    FileSystemHelper::createTree(
                        $root,
                        [$folder . get_orig_folder()],
                        self::DIR_CHMOD
                    );

                    move_uploaded_file($F['tmp_name'], $orig_fn) ||
                        rename($F['tmp_name'], $orig_fn);
                    chmod($orig_fn, self::FILE_CHMOD);
                }
            }

            [$ar['pic_w'], $ar['pic_h'], $ar['pic_t']] = getimagesize($full_fn);
        } else {
            [$ar['pic_w'], $ar['pic_h'], $ar['pic_t']] = [0, 0, 0];

            if ($mode == self::IMAGE_STORE_MODE_UPLOAD) {
                move_uploaded_file($F['tmp_name'], $full_fn) ||
                    rename($F['tmp_name'], $orig_fn);
            }
        }
    }

    public static function checkboxesSaver(Submit $Submit, $field)
    {
        $f = self::formatName($field);
        $sep = $Submit->getFieldOption($field, 'separator') ?: ',';
        $value = join($sep, \diRequest::post($f, []));

        if ($value && $Submit->getFieldOption($field, 'externalSeparators')) {
            $value = $sep . $value . $sep;
        }

        $Submit->setData($field, $value);
    }
}

/* @deprecated */
function diasStoreImage(&$obj, $field, $options, $F)
{
    Submit::storeImageCallback($obj, $field, $options, $F);
}

/* @deprecated */
function dias_sharpen_img($img)
{
    return \diImage::sharpMask($img, 80, 0.5, 0);
}

/** @deprecated  */
function dias_save_file($F, $field, $pics_folder, $fn, Submit $obj)
{
    Submit::storeFileCallback(
        $obj,
        $field,
        [
            'folder' => $pics_folder,
            'subfolder' => '',
            'filename' => $fn,
        ],
        $F
    );
}

/** @deprecated */
function dias_save_dynamic_pic($F, $tableOrSubmit, $what, &$ar, $pics_folder)
{
    Submit::storeDynamicPicCallback($F, $tableOrSubmit, $what, $ar, $pics_folder);
}

/** @deprecated */
function diasSaveDynamicPic($F, $tableOrSubmit, $what, &$ar, $pics_folder)
{
    Submit::storeDynamicPicCallback($F, $tableOrSubmit, $what, $ar, $pics_folder);
}
