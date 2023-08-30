<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.06.2015
 * Time: 18:51
 */

use diCore\Admin\Form;
use diCore\Admin\Submit;
use diCore\Base\CMS;
use diCore\Base\Language;
use diCore\Data\Config;
use diCore\Database\Connection;
use diCore\Database\FieldType;
use diCore\Entity\DynamicPic\Collection as DynamicPics;
use diCore\Helper\Slug;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;

class diModel implements \ArrayAccess
{
    const MAX_PREVIEWS_COUNT = 5;

    const SLUG_FIELD_NAME_LEGACY = 'clean_title';
    const SLUG_FIELD_NAME = 'slug';

    const LOCALIZED_PREFIX = 'localized_';

    // Model type (from diTypes class). Should be redefined
    const type = null;
    const connection_name = null;
    const table = null;
    const camel_case_field_names = false;
    const id_field_name = 'id';
    const mongo_id_field_name = '_id';
    const slug_field_name = null; //self::SLUG_FIELD_NAME_LEGACY; get this back when all models are updated
    const slug_lower_case = true;
    const slug_regenerate_if_duplicate = false;
    const slug_delimiter = '-';
    const order_field_name = 'order_num';
    const use_data_cache = false;
    const open_graph_pic_field = 'pic';
    const validation_error_prefix_needed = false;
    const use_insecure_password_hash = true;

    // this should be redefined
    protected $table;

    /** @var array */
    protected $ar = [];

    /** @var array */
    protected $origData = [];

    /** @var array */
    protected $cachedData = [];

    /** @var array */
    protected $relatedData = [];

    /** @var int|string|null */
    protected $id;
    /** @var int|string|null */
    protected $origId;
    protected $idAutoIncremented = true;
    /** @deprecated */
    protected $slugFieldName = self::SLUG_FIELD_NAME_LEGACY;
    /** @deprecated */
    protected $orderFieldName = 'order_num';
    /** @var string 'id' or 'slug', auto-detect by default */
    protected $identityFieldName;
    /** @var bool if true: no origData stored, save/kill not allowed */
    protected $readOnly = false;

    protected $picsFolder = null;
    protected $picsTnFolders = [];

    /** @var array Fields of the model */
    protected $fields = [];

    protected $picFields = ['pic', 'pic2', 'ico'];
    /* redefine this in child class */
    protected $customPicFields = [];

    /**
     * @var array Settings for Submit, for each pic field, what previews to generate
     */
    protected static $picStoreSettings = [
        'pic' => [
            [
                'type' => Submit::IMAGE_TYPE_MAIN,
                'resize' => \diImage::DI_THUMB_FIT,
                // 'forceFormat' => 'jpeg',
                // 'quality' => 84,
            ],
            [
                'type' => Submit::IMAGE_TYPE_PREVIEW,
                'resize' => \diImage::DI_THUMB_FIT,
                // 'forceFormat' => 'jpeg',
                // 'quality' => 75,
            ],
        ],
    ];

    protected $fileFields = [
        'pic',
        'pic2',
        'pic3',
        'pic_main',
        'ico',
        'flv',
        'mp3',
        'swf',
        'final_pic',
    ];
    protected $customFileFields = [];
    protected $customFileFolders = [];

    /* this will be automatically generated on model creation */
    protected $localizedFields = [];
    protected $customLocalizedFields = [];

    protected $dateFields = [
        'date',
        'created_at',
        'edited_at',
        'updated_at',
        'deleted_at',
    ];
    /* redefine this in child class */
    protected $customDateFields = [];

    protected static $binaryFields = [];
    protected static $customBinaryFields = [];

    /**
     * @var array Fields which should be compared strict in saveToDb() method
     */
    protected $strictChangeOnSaveFields = [];
    protected $customStrictChangeOnSaveFields = [];

    protected $ipFields = ['ip'];
    /* redefine this in child class */
    protected $customIpFields = [];

    protected $forceGetRecord = false;

    protected $validationNeeded = true;
    protected $validationErrors = [];

    private $insertOrUpdateAllowed = false;

    /** @var callable */
    private $fieldsOnSaveCallback;

    /**
     * @var \MongoDB\Collection
     */
    protected $collectionResource;

    /**
     * @var array [field => type]
     */
    protected static $fieldTypes = [];
    /**
     * @var array [field => type]
     * use [field => FieldType::unset] to delete field from $fieldTypes
     */
    protected static $customFieldTypes = [];

    protected $upsertFields = [];

    protected static $publicFields = [];

    /**
     * @param null|array|object $ar
     * @param null|string $table
     */
    public function __construct($ar = null, $table = null)
    {
        if ($table) {
            $this->table = $table;
        }

        $this->initFrom($ar);
    }

    /**
     * @param $type
     * @param string $return could be class or type
     * @return bool|string
     */
    public static function existsFor($type, $return = 'class')
    {
        if (isInteger($type)) {
            $type = \diTypes::getName($type);
        }

        $className = \diLib::getClassNameFor($type, \diLib::MODEL);

        if (!\diLib::exists($className)) {
            return false;
        }

        return $return == 'class' ? $className : $type;
    }

    public static function getCollectionClass()
    {
        return \diLib::getChildClass(static::class, 'Collection');
    }

    public static function getConnection()
    {
        return Connection::get(static::connection_name);
    }

    public static function getConnectionEngine()
    {
        return static::getConnection()::getEngine();
    }

    /**
     * @param $type
     * @param null|array|object $ar
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public static function create($type = null, $ar = null, $options = [])
    {
        if (!$type) {
            $type = static::type;
        }

        if (is_scalar($options)) {
            $options = [
                'identityFieldName' => $options,
            ];
        }

        $options = extend(
            [
                'identityFieldName' => null,
                'readOnly' => false,
            ],
            $options
        );

        $className = static::existsFor($type);

        if (!$className) {
            throw new \Exception(
                "Model class doesn't exist: " . ($className ?: $type)
            );
        }

        /** @var diModel $o */
        $o = new $className();

        if ($options['identityFieldName']) {
            $o->setIdentityFieldName($options['identityFieldName']);
        }

        $o->_setReadOnly($options['readOnly'])->initFrom($ar);

        return $o;
    }

    /**
     * @param $table
     * @param null|array|object $ar
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public static function createForTable($table, $ar = null, $options = [])
    {
        return static::create(\diTypes::getNameByTable($table), $ar, $options);
    }

    /**
     * @param $table
     * @param null|array|object $ar
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public static function createForTableNoStrict(
        $table,
        $ar = null,
        $options = []
    ) {
        $type = \diTypes::getNameByTable($table);
        $typeName = static::existsFor($type, 'type');

        return $typeName
            ? static::create($typeName, $ar, $options)
            : new static($ar, $table);
    }

    /**
     * @param $slug
     * @return $this
     * @throws \Exception
     */
    public static function createBySlug($slug)
    {
        return static::create(static::type, $slug, 'slug');
    }

    /**
     * @param $id
     * @return $this
     * @throws \Exception
     */
    public static function createById($id)
    {
        return static::create(static::type, $id, 'id');
    }

    public static function normalizeFieldName($field)
    {
        if (static::camel_case_field_names) {
            $field = camelize($field);
        }

        return $field;
    }

    public function __call($method, $arguments)
    {
        $fullMethod = underscore($method);
        $value = $arguments[0] ?? null;

        $x = strpos($fullMethod, '_');
        $method = substr($fullMethod, 0, $x);
        $field = substr($fullMethod, $x + 1);
        $field = static::normalizeFieldName($field);

        switch ($method) {
            case 'get':
                return $this->get($field);

            case 'localized':
                return $this->localized($field);

            case 'set':
                return $this->set($field, $value);

            case 'kill':
                return $this->kill($field);

            case 'has':
                return $this->has($field);

            case 'exists':
                return $this->exists($field);
        }

        // for twig empty properties
        if (
            !$arguments &&
            ($value = $this->getExtendedTemplateVar($fullMethod)) !== null
        ) {
            return $value;
        }

        throw new \Exception(
            sprintf(
                'diModel invalid method %s::%s/%s(%s)',
                get_class($this),
                $method,
                $fullMethod,
                print_r($arguments, 1)
            )
        );
    }

    public function initFrom($r)
    {
        if ($r instanceof \diModel) {
            $r = (array) $r->get();
        } elseif ($r instanceof \MongoDB\Model\BSONDocument) {
            $ar = [];

            foreach ($r->getIterator() as $field => $value) {
                $ar[$field] = static::tuneFieldValueByTypeAfterDb(
                    $field,
                    $value
                );
            }

            $r = $ar;
        } elseif (is_object($r) || is_array($r)) {
            $r = (array) $r;
        }

        $this->ar = is_array($r)
            ? $r
            : ($r || $this->forceGetRecord
                ? $this->getRecord($r)
                : []);

        if ($this->ar instanceof \diModel) {
            $m = $this->ar;
            $this->ar = [];

            $this->set($m->get())->setRelated($m->getRelated());
        } else {
            $this->ar = $this->ar ? (array) $this->ar : [];
        }

        $this->checkId()->setOrigData();

        return $this;
    }

    public function initFromRequest($method = 'post', $excludeKeys = [])
    {
        $data = \diRequest::all($method);

        if ($method === 'post' && !$data) {
            $data = \diRequest::rawPostParsed();
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $excludeKeys)) {
                continue;
            }

            if ($method === 'post') {
                $value = $value ?: \diRequest::rawPost($key);
            }

            $this->set($key, $value);
        }

        return $this;
    }

    public function setIdentityFieldName($field)
    {
        $this->identityFieldName = $field;

        return $this;
    }

    public function _setReadOnly($state)
    {
        $this->readOnly = !!$state;

        return $this;
    }

    public function getTable()
    {
        return static::table ?: $this->table;
    }

    public function modelType()
    {
        return static::type;
    }

    public static function modelTypeName()
    {
        return static::type ? \diTypes::getName(static::type) : null;
    }

    public function getPicForOpenGraph($httpPath = true)
    {
        return $this->wrapFileWithPath(
            $this->get(static::open_graph_pic_field),
            null,
            $httpPath
        );
    }

    public function getHref()
    {
        return null;
    }

    public function getFullHref()
    {
        return \diPaths::defaultHttp() . $this->getHref();
    }

    public function getAdminHref()
    {
        return '/_admin/' . $this->getTable() . '/form/' . $this->getId() . '/';
    }

    public function getFullAdminHref()
    {
        return \diPaths::defaultHttp() . $this->getAdminHref();
    }

    public static function __getPrefixForHref($language = null)
    {
        global $Z;

        /*
		if (!$language) {
            $language = static::__getLanguage();
        }
		*/

        if (
            \diCurrentCMS::LANGUAGE_MODE == Language::DOMAIN &&
            $language &&
            $language !== static::__getLanguage()
        ) {
            return \diRequest::protocol() .
                '://' .
                ArrayHelper::get(\diCurrentCMS::$languageDomains, [
                    $language,
                    0,
                ]) .
                \diLib::getSubFolder(true);
        }

        if ($language && $language != \diCurrentCMS::$defaultLanguage) {
            $prefix =
                \diCurrentCMS::LANGUAGE_MODE == Language::URL
                    ? '/' . $language
                    : '';
        } elseif (!empty($Z) && !$language) {
            $prefix = $Z->getLanguageHrefPrefix();
        } else {
            $prefix = '';
        }

        return \diLib::getSubFolder(true) . $prefix;
    }

    protected static function __getLanguage()
    {
        /** @var CMS $Z */
        global $Z;
        /** @var \diCore\Admin\Base $X */
        global $X;

        if (
            !empty($GLOBALS['CURRENT_LANGUAGE']) &&
            in_array(
                $GLOBALS['CURRENT_LANGUAGE'],
                \diCurrentCMS::$possibleLanguages
            ) &&
            $GLOBALS['CURRENT_LANGUAGE'] != \diCurrentCMS::$defaultLanguage
        ) {
            $language = $GLOBALS['CURRENT_LANGUAGE'];
        } elseif (!empty($Z)) {
            $language = $Z->getLanguage();
        } elseif (!empty($X)) {
            $language = $X->getLanguage();
        } else {
            $language = \diCurrentCMS::$defaultLanguage;
        }

        return $language;
    }

    public function getSlugFieldName()
    {
        return static::slug_field_name ?: $this->slugFieldName;
    }

    final public function getRawSlug()
    {
        return $this->get($this->getSlugFieldName());
    }

    public function getSlug()
    {
        return $this->getRawSlug();
    }

    public function setSlug($value)
    {
        return $this->set($this->getSlugFieldName(), $value);
    }

    public function hasSlug()
    {
        return $this->has($this->getSlugFieldName());
    }

    public function killSlug()
    {
        return $this->kill($this->getSlugFieldName());
    }

    public function getSourceForSlug()
    {
        return $this->get('slug_source') ?:
            $this->get('en_title') ?:
            $this->get('title');
    }

    public function generateSlug(
        $source = null,
        $delimiter = null,
        $extraOptions = []
    ) {
        $extraOptions = extend(
            [
                'lowerCase' => static::slug_lower_case,
                'regenerateIfDuplicate' => static::slug_regenerate_if_duplicate,
                'delimiter' => $delimiter ?: static::slug_delimiter,
            ],
            $extraOptions
        );

        if (
            $extraOptions['regenerateIfDuplicate'] &&
            empty($extraOptions['uniqueMaker'])
        ) {
            $extraOptions['uniqueMaker'] = function (
                $origSlug,
                $delimiter,
                $index
            ) {
                return $this->getSourceForSlug();
            };

            unset($extraOptions['regenerateIfDuplicate']);
        }

        $this->setSlug(
            Slug::generate(
                $source ?: $this->getSourceForSlug(),
                $this->getTable(),
                $this->getId(),
                $this->getIdFieldName(),
                $this->getSlugFieldName(),
                $extraOptions['delimiter'],
                extend($extraOptions, [
                    'db' => $this->getDb(),
                ])
            )
        );

        return $this;
    }

    public static function getIdFieldName()
    {
        return static::getConnection()::isMongo()
            ? static::mongo_id_field_name
            : static::id_field_name;
    }

    public static function areFieldsInCamelCase()
    {
        return static::getConnection()::isMongo();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOrigId()
    {
        return $this->origId;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    private function setOrigId($id)
    {
        $this->origId = $id;

        return $this;
    }

    public function hasId()
    {
        return $this->has($this->getIdFieldName());
    }

    public function killId()
    {
        $this->id = null;

        return $this;
    }

    public function killOrigId()
    {
        $this->origId = null;
        $this->killOrig(static::getIdFieldName());

        return $this;
    }

    protected function isPicField($field)
    {
        return in_array($field, $this->getPicFields());
    }

    protected function isFileField($field)
    {
        return in_array($field, $this->getFileFields());
    }

    protected function isDateField($field)
    {
        return in_array($field, $this->getDateFields());
    }

    protected function isIpField($field)
    {
        return in_array($field, $this->getIpFields());
    }

    public function setPicsFolder($folder)
    {
        $this->picsFolder = $folder;

        return $this;
    }

    public function getPicsFolder()
    {
        return $this->picsFolder !== null
            ? $this->picsFolder
            : get_pics_folder($this->getTable(), Config::getUserAssetsFolder());
    }

    public function getFilesFolder()
    {
        return $this->getPicsFolder() . getFilesFolder();
    }

    public function setTnFolder($folder, $index = '')
    {
        if ($index < 2) {
            $index = '';
        }

        $this->picsTnFolders[$index] = $folder;

        return $this;
    }

    public function getTnFolder($index = '')
    {
        if ($index < 2) {
            $index = '';
        }

        return $this->picsTnFolders[$index] ?? get_tn_folder($index);
    }

    public function getFolderForField($field)
    {
        return $this->getPicsFolder();
    }

    public function wrapFileWithPath(
        $filename,
        $previewIdx = null,
        $httpPath = true,
        $field = null
    ) {
        $subFolder = '';

        if ($previewIdx !== null) {
            $subFolder = isInteger($previewIdx)
                ? $this->getTnFolder($previewIdx) # preview folder if numeric idx passed
                : StringHelper::slash($previewIdx); # full subfolder name otherwise
        }

        $pathPrefix = $httpPath
            ? \diPaths::http($this, true, $field)
            : \diPaths::fileSystem($this, true, $field);

        return $filename
            ? $pathPrefix .
                    $this->getFolderForField($field) .
                    $subFolder .
                    $filename
            : '';
    }

    public function getFilesForRotation($field)
    {
        return [$this->getOrigFileForRebuilding($field)];
    }

    public function getOrigFileSettingsForRebuilding($field)
    {
        $types = [
            Submit::IMAGE_TYPE_ORIG,
            Submit::IMAGE_TYPE_BIG,
            Submit::IMAGE_TYPE_MAIN,
        ];

        foreach ($types as $type) {
            $o = static::findPicStoreSettingsByType($field, $type);

            if ($o) {
                return $o;
            }
        }

        throw new \Exception('Orig file not found for field ' . $field);
    }

    public function getOrigFileForRebuilding($field)
    {
        $settings = $this->getOrigFileSettingsForRebuilding($field);

        return $this->wrapFileWithPath(
            $this->get($field),
            Submit::getFolderByImageType($settings['type']),
            false
        );
    }

    public static function findPicStoreSettingsByType($field, $type)
    {
        $settings = static::getPicStoreSettings($field);

        if ($settings) {
            foreach ($settings as $o) {
                if ($o['type'] == $type) {
                    return $o;
                }
            }
        }

        return null;
    }

    public static function getPicStoreSettings($field)
    {
        return static::$picStoreSettings[$field] ?? null;
    }

    public function rebuildPics($field)
    {
        $Submit = new Submit($this->getTable(), $this->getId());
        $callback = [Submit::class, 'storeImageCallback'];

        $opts = $this->getOrigFileSettingsForRebuilding($field);
        $fn = $this->getOrigFileForRebuilding($field);

        $fieldFileOptions = Submit::prepareFileOptions(
            $field,
            $this->getPicStoreSettings($field),
            $this
        );

        $F = [
            'name' => $this->get($field),
            'type' => 'image/jpeg',
            'tmp_name' => $fn,
            'error' => 0,
            'size' => filesize($fn),
            'diOrigType' => $opts['type'],
        ];

        $callback($Submit, $field, $fieldFileOptions, $F);

        return $this;
    }

    public function renameFiles($field, $newFnSource)
    {
        $fileVariants = static::getPicStoreSettings($field);
        $oldFn = $this->get($field);
        $newFn = null;

        if (!$oldFn) {
            throw new \Exception('No file value set to be renamed');
        }

        if (!$fileVariants) {
            throw new \Exception('No pic settings set');
        }

        foreach ($fileVariants as $variant) {
            $pathPrefix = \diPaths::fileSystem($this, true, $field);
            $subFolder = Submit::getFolderByImageType($variant['type']);
            $path = $pathPrefix . $this->getFolderForField($field) . $subFolder;

            if (!$newFn) {
                $newFn = Submit::getGeneratedFilename(
                    $path,
                    $newFnSource,
                    Submit::FILE_NAMING_ORIGINAL
                );
            }

            $oldFullFn = $path . $oldFn;
            $newFullFn = $path . $newFn;

            if (is_file($oldFullFn)) {
                rename($oldFullFn, $newFullFn);
            }

            if (is_file($oldFullFn . \diImage::EXT_WEBP)) {
                rename(
                    $oldFullFn . \diImage::EXT_WEBP,
                    $newFullFn . \diImage::EXT_WEBP
                );
            }
        }

        $this->set($field, basename($newFn));

        return $this;
    }

    public function getFileContents($field, $previewIdx = null)
    {
        if (!$this->has($field)) {
            return null;
        }

        return file_get_contents(
            $this->wrapFileWithPath(
                $this->get($field),
                $previewIdx,
                false,
                $field
            )
        );
    }

    public function getTemplateVars()
    {
        $ar = [];

        if (!$this->exists()) {
            return $ar;
        }

        foreach ($this->ar as $k => $v) {
            $isLocalized = $this->isFieldLocalized($k);

            if ($this->isPicField($k)) {
                for ($i = 1; $i <= static::MAX_PREVIEWS_COUNT; $i++) {
                    $idx = $i > 1 ? $i : '';

                    if (!$this->exists($k . '_tn' . $idx)) {
                        $ar[$k . '_tn' . $idx] = $ar[
                            $k . '_tn' . $idx . '_with_path'
                        ] = $this->wrapFileWithPath($v, $i);

                        if ($isLocalized) {
                            $ar[
                                static::LOCALIZED_PREFIX . $k . '_tn' . $idx
                            ] = $ar[
                                static::LOCALIZED_PREFIX .
                                    $k .
                                    '_tn' .
                                    $idx .
                                    '_with_path'
                            ] = $this->wrapFileWithPath(
                                $this->localized($k),
                                $i
                            );
                        }
                    } else {
                        $ar[
                            $k . '_tn' . $idx . '_with_path'
                        ] = $this->wrapFileWithPath(
                            $this->get($k . '_tn' . $idx),
                            $i
                        );

                        if ($isLocalized) {
                            $ar[
                                static::LOCALIZED_PREFIX .
                                    $k .
                                    '_tn' .
                                    $idx .
                                    '_with_path'
                            ] = $this->wrapFileWithPath(
                                $this->localized($k . '_tn' . $idx),
                                $i
                            );
                        }
                    }
                }

                if ($v) {
                    $v = $this->wrapFileWithPath($v);
                }

                $ar[$k . '_with_path'] = $v;

                if ($isLocalized) {
                    if ($v2 = $this->localized($k)) {
                        $v2 = $this->wrapFileWithPath($v2);
                    }

                    $ar[static::LOCALIZED_PREFIX . $k . '_with_path'] = $v2;
                }
            } elseif ($this->isFileField($k)) {
                if ($v) {
                    $v = $this->wrapFileWithPath($v, null, true, $k);
                }

                $ar[$k . '_with_path'] = $v;

                if ($isLocalized) {
                    if ($v2 = $this->localized($k)) {
                        $v2 = $this->wrapFileWithPath($v2);
                    }

                    $ar[static::LOCALIZED_PREFIX . $k . '_with_path'] = $v2;
                }
            } elseif ($this->isDateField($k)) {
                $v = isInteger($v) ? $v : strtotime($v ?: '');

                if ($v) {
                    $ar = extend(
                        $ar,
                        ArrayHelper::mapAssoc(function ($field, $value) use (
                            $k
                        ) {
                            return [$k . '_' . $field, $value];
                        }, static::getTemplateDateVars($v))
                    );

                    $v = $ar[$k . '_date'];
                }
            } elseif ($this->isIpField($k)) {
                $ar[$k . '_num'] = isInteger($v) ? $v : ip2bin($v);
                $v = isInteger($v) ? bin2ip($v) : $v;
                $ar[$k . '_str'] = $v;
            }

            $ar[$k] = $v;
        }

        foreach ($this->getAllLocalizedFields() as $f) {
            $ar[static::LOCALIZED_PREFIX . $f] = $this->localized($f);
        }

        $ar[$this->getIdFieldName()] = $this->getId();
        $ar['slug'] = $this->getSlug(); // back compatibility for clean_title

        return extend($ar, $this->getBasicTemplateVars());
    }

    public function getBasicTemplateVars()
    {
        $ar = [
            'href' => $this->getHref(),
            'full_href' => $this->getFullHref(),
            'admin_href' => $this->getAdminHref(),
            'full_admin_href' => $this->getFullAdminHref(),
        ];

        return $ar;
    }

    public static function getTemplateDateVars($v)
    {
        return [
            'time' => \diDateTime::format('H:i', $v),
            'date' => \diDateTime::format('d.m.Y', $v),
            'iso' => \diDateTime::isoFormat($v),
            'str' => \diDateTime::format(static::getDateStrFormat(), $v),
            'passed_by' => \diDateTime::passedBy($v),
        ];
    }

    public static function getDateStrFormat()
    {
        switch (static::normalizeLang()) {
            default:
            case 'ru':
                return 'd %месяца% Y';

            case 'en':
                return 'F d, Y';
        }
    }

    /**
     * Custom model template vars
     *
     * @return array
     */
    public function getCustomTemplateVars()
    {
        return [];
    }

    final public function getTemplateVarsExtended()
    {
        if (static::use_data_cache) {
            if (!$this->existsCached()) {
                $this->setCachedData($this->getCustomTemplateVars());
            }

            $customVars = $this->getCachedData();
        } else {
            $customVars = $this->getCustomTemplateVars();
        }

        return extend($this->getTemplateVars(), $customVars);
    }

    public function getExtendedTemplateVar($key)
    {
        $templateVars = $this->getTemplateVarsExtended();

        if (isset($templateVars[$key])) {
            return $templateVars[$key];
        }

        unset($templateVars);

        return null;
    }

    /**
     * @param string $field for `_field` in `dipics` table
     */
    public function getDynamicPics($field = 'pics', $options = [])
    {
        $options = extend(
            [
                'onlyFirstRecord' => false,
                'orderBy' => 'order_num ASC',
                'queryAr' => [
                    "visible = '1'",
                    "_table = '{$this->getTable()}'",
                    "_id = '{$this->getId()}'",
                    "_field = '$field'",
                ],
                'additionalQueryAr' => [],
            ],
            $options
        );

        $queryAr = array_merge(
            $options['queryAr'],
            $options['additionalQueryAr']
        );
        $limit = $options['onlyFirstRecord']
            ? $this->getDb()->limitOffset(1)
            : '';

        $ar = [];

        $rs = $this->getDb()->rs(
            'dipics',
            'WHERE ' .
                join(' AND ', $queryAr) .
                ' ORDER BY ' .
                $options['orderBy'] .
                $limit
        );
        while ($r = $this->getDb()->fetch($rs)) {
            $m = static::create(\diTypes::dynamic_pic, $r);
            $m->setRelated('table', $this->getTable());

            $ar[] = $m;
        }

        return $options['onlyFirstRecord'] ? $ar[0] ?? null : $ar;
    }

    protected function processIdBeforeGetRecord($id, $field)
    {
        return static::getConnection()::isMongo()
            ? new ObjectID($id)
            : (int) $id;
    }

    protected function prepareIdAndFieldForGetRecord($id, $fieldAlias = null)
    {
        $id = $this->getDb()->escape_string($id);

        // identifying wood
        $fieldAlias = $fieldAlias ?: $this->identityFieldName;

        if ($fieldAlias == 'id') {
            $field = $this->getIdFieldName();
            $id = $this->processIdBeforeGetRecord($id, $fieldAlias);
        } elseif ($fieldAlias == 'slug') {
            $field = $this->getSlugFieldName();
        } else {
            $field = static::isProperId($id)
                ? $this->getIdFieldName()
                : $this->getSlugFieldName();
        }
        //

        return [
            'id' => $id,
            'field' => $field,
        ];
    }

    protected static function isProperId($id)
    {
        return static::getConnection()::isMongo()
            ? strlen($id) == 24
            : isInteger($id) && $id > 0;
    }

    protected function getDatabaseRecord($field, $id)
    {
        return static::getConnection()::isMongo()
            ? $this->getCollectionResource()->findOne([
                $field => $id,
            ])
            : $this->getDb()->ar(
                $this->getDb()->escapeTable($this->getTable()),
                "WHERE {$field} = '{$id}'"
            );
    }

    protected function getRecord($id, $fieldAlias = null)
    {
        if (!$this->getTable()) {
            throw new \Exception('Table/collection not defined');
        }

        $a = $this->prepareIdAndFieldForGetRecord($id, $fieldAlias);

        $ar = $this->getDatabaseRecord($a['field'], $a['id']);

        return $this->tuneDataAfterFetch($ar);
    }

    protected function tuneDataAfterFetch($ar)
    {
        if (static::getConnection()::isMongo()) {
            if (!$ar) {
                return $ar;
            }

            foreach ($ar as $field => $value) {
                $ar[$field] = static::tuneFieldValueByTypeAfterDb(
                    $field,
                    $value
                );
            }
        }

        return $ar;
    }

    public function moveFieldToRelated($field)
    {
        if ($this->exists($field)) {
            $this->setRelated($field, $this->get($field))->kill($field);
        }

        return $this;
    }

    public function removeUnnecessaryField($field)
    {
        return $this->moveFieldToRelated($field);
    }

    /**
     * Killing all fields in model which are not in $this->fields array
     *
     * @return $this
     */
    public function removeUnnecessaryFields()
    {
        foreach ($this->ar as $field => $value) {
            if (!in_array($field, $this->fields)) {
                $this->removeUnnecessaryField($field);
            }
        }

        return $this;
    }

    /**
     * Basic validation: all $this->fields treated as necessary
     *
     * @return $this
     */
    protected function simpleValidate()
    {
        foreach ($this->fields as $field) {
            if (!$this->exists($field)) {
                $this->addValidationError(
                    "Field '{$field}' should be defined in " . get_class($this),
                    $field
                );
            }
        }

        return $this;
    }

    public static function href($r)
    {
        $o = new static($r);

        return $o->getHref();
    }

    /**
     * @return \diDB
     */
    protected function getDb()
    {
        return Connection::get(
            static::connection_name ?: Connection::DEFAULT_NAME
        )->getDb();
    }

    protected static function db()
    {
        return Connection::get(
            static::connection_name ?: Connection::DEFAULT_NAME
        )->getDb();
    }

    /**
     * @param null|string $field
     * @return bool
     */
    public function exists($field = null)
    {
        return is_null($field) ? !!$this->ar : isset($this->ar[$field]);
    }

    /**
     * @param null|string $field
     * @return bool
     */
    public function existsOrig($field = null)
    {
        return is_null($field)
            ? !!$this->origData
            : isset($this->origData[$field]);
    }

    /**
     * @param null|string $field
     * @return bool
     */
    public function existsCached($field = null)
    {
        return is_null($field)
            ? !!$this->cachedData
            : isset($this->cachedData[$field]);
    }

    public function has($field)
    {
        if ($field == static::getIdFieldName()) {
            return !!$this->getId();
        }

        return !empty($this->ar[$field]);
    }

    public function hasOrig($field)
    {
        if ($field == static::getIdFieldName()) {
            return !!$this->getOrigId();
        }

        return !empty($this->origData[$field]);
    }

    /**
     * @param string|null $field
     * @return string|int|null|array
     */
    public function get($field = null)
    {
        if (is_null($field)) {
            return $this->getWithId();
        }

        if ($field == static::getIdFieldName()) {
            return $this->getId();
        } elseif (!$this->exists($field)) {
            //throw new Exception("Field '$field' is undefined in ".get_class($this));

            return null;
        }

        return $this->ar[$field];
    }

    public function getOrigData($field = null)
    {
        if (is_null($field)) {
            return $this->getOrigWithId();
        } elseif ($field == static::getIdFieldName()) {
            return $this->getOrigId();
        } elseif (!$this->existsOrig($field)) {
            return null;
        } else {
            return $this->origData[$field];
        }
    }

    public function getCachedData($field = null)
    {
        if (is_null($field)) {
            return $this->cachedData;
        }

        return $this->existsCached($field) ? $this->origData[$field] : null;
    }

    public function getTunedData($field = null)
    {
        if (is_null($field)) {
            return ArrayHelper::mapAssoc(function ($key, $value) {
                return [
                    $key,
                    static::tuneFieldValueByTypeBeforeDb($key, $value),
                ];
            }, $this->get());
        }

        return static::tuneFieldValueByTypeBeforeDb($field, $this->get($field));
    }

    public function localized($field, $lang = null)
    {
        return $this->get(static::getLocalizedFieldName($field, $lang));
    }

    public function getAllLocalizedFields()
    {
        return array_merge(
            $this->localizedFields,
            $this->customLocalizedFields
        );
    }

    public function isFieldLocalized($field)
    {
        return in_array($field, $this->getAllLocalizedFields());
    }

    /**
     * @param null|array|string $field
     * @return bool
     */
    public function changed($field = null, $strict = false)
    {
        if (is_array($field)) {
            $keys = $field;
        } elseif ($field === null) {
            $keys = array_merge(
                [$this->getIdFieldName()],
                array_keys($this->ar) ?: array_keys($this->origData)
            );
        } else {
            $keys = [$field];
        }

        foreach ($keys as $key) {
            $changed = $this->isValueOfFieldChanged($key, $strict);

            if ($changed) {
                return true;
            }
        }

        return false;
    }

    protected function isValueOfFieldChanged($key, $strict = false)
    {
        $old = $this->getOrigData($key);
        $new = $this->get($key);

        return $strict ? $new !== $old : $new != $old;
    }

    public function changedFields($exclude = [], $strict = false)
    {
        $keys = array_merge(
            [$this->getIdFieldName()],
            array_keys($this->ar) ?: array_keys($this->origData)
        );

        $changedKeys = [];

        foreach ($keys as $key) {
            $changed = $strict
                ? $this->get($key) !== $this->getOrigData($key)
                : $this->get($key) != $this->getOrigData($key);

            if ($changed && !in_array($key, $exclude)) {
                $changedKeys[] = $key;
            }
        }

        return $changedKeys;
    }

    public static function normalizeLang($lang = null, $field = null)
    {
        if ($lang === null) {
            $lang = static::__getLanguage();
        } elseif (is_object($lang) && $lang instanceof CMS) {
            $lang = $lang->getLanguage();
        }

        return $lang;
    }

    public static function getLocalizedFieldName($field, $lang = null)
    {
        $lang = static::normalizeLang($lang, $field);

        if ($lang != Config::getMainLanguage()) {
            $field = $lang . '_' . $field;
        }

        return $field;
    }

    public function getWithoutId()
    {
        return $this->ar;
    }

    public function getWithId()
    {
        return $this->ar
            ? extend($this->ar, [
                $this->getIdFieldName() => $this->getId(),
            ])
            : null;
    }

    public function getOrigWithId()
    {
        return $this->origData
            ? extend($this->origData, [
                $this->getIdFieldName() => $this->getOrigId(),
            ])
            : null;
    }

    public function isEqualTo(\diModel $m)
    {
        return $this->modelType() == $m->modelType() &&
            $this->hasId() &&
            $this->getId() == $m->getId();
    }

    public function set($field, $value = null)
    {
        if (is_array($field)) {
            $this->ar = extend($this->ar, $field);
        } else {
            $this->ar[$field] = $value;
        }

        $this->checkId()->killCached();

        return $this;
    }

    /**
     * @param null|array|string $field
     * @param mixed $value
     * @return $this
     */
    public function setOrigData($field = null, $value = null)
    {
        if ($this->readOnly) {
            return $this;
        }

        if (is_null($field)) {
            $this->origData = $this->ar;
            $this->origId = $this->id;
        } else {
            if (is_scalar($field)) {
                $field = strtolower($field);
                $this->origData[$field] = $value;
            } else {
                $this->origData = extend($this->origData, (array) $field);
            }
        }

        $this->checkOrigId();

        return $this;
    }

    /**
     * @param null|array|string $field
     * @param mixed $value
     * @return $this
     */
    public function setCachedData($field, $value = null)
    {
        if (is_scalar($field)) {
            $field = strtolower($field);
            $this->cachedData[$field] = $value;
        } else {
            $this->cachedData = extend($this->cachedData, (array) $field);
        }

        return $this;
    }

    /**
     * @param null|string|array $field
     *
     * @return $this
     */
    public function kill($field = null)
    {
        if (is_null($field)) {
            $this->destroy();
        } elseif (is_string($field)) {
            if ($this->exists($field)) {
                unset($this->ar[$field]);
            }
        } elseif (is_array($field)) {
            foreach ($field as $f) {
                $this->kill($f);
            }
        }

        return $this;
    }

    /**
     * @param null|string|array $field
     *
     * @return $this
     */
    public function killOrig($field = null)
    {
        if (is_null($field)) {
            $this->destroyOrig();
        } elseif (is_string($field)) {
            if ($this->existsOrig($field)) {
                unset($this->origData[$field]);
            }
        } elseif (is_array($field)) {
            foreach ($field as $f) {
                $this->killOrig($f);
            }
        }

        return $this;
    }

    /**
     * @param null|string|array $field
     *
     * @return $this
     */
    public function killCached($field = null)
    {
        if (is_null($field)) {
            $this->destroyCached();
        } elseif (is_string($field)) {
            if ($this->existsCached($field)) {
                unset($this->cachedData[$field]);
            }
        } elseif (is_array($field)) {
            foreach ($field as $f) {
                $this->killCached($f);
            }
        }

        return $this;
    }

    public function getRelated($field = null)
    {
        if (is_null($field)) {
            return $this->relatedData;
        }

        if (!isset($this->relatedData[$field])) {
            //throw new \Exception("Field '$field' is undefined in related data of " . get_class($this));
            return null;
        }

        return $this->relatedData[$field];
    }

    public function setRelated($field, $value = null)
    {
        if (is_null($value)) {
            $this->relatedData = (array) extend($this->relatedData, $field);
        } else {
            $this->relatedData[$field] = $value;
        }

        return $this;
    }

    public function killRelated($field)
    {
        if (isset($this->relatedData[$field])) {
            unset($this->relatedData[$field]);
        }

        return $this;
    }

    /**
     * @return boolean
     */
    public function isValidationNeeded()
    {
        return $this->validationNeeded;
    }

    /**
     * @param boolean $validationNeeded
     */
    public function setValidationNeeded($validationNeeded)
    {
        $this->validationNeeded = $validationNeeded;

        return $this;
    }

    public function clearValidationErrors()
    {
        $this->validationErrors = [];

        return $this;
    }

    public function preparedValidationErrors()
    {
        return $this->validationErrors;
    }

    public static function validationErrorPrefixNeeded()
    {
        return static::validation_error_prefix_needed;
    }

    public function validationErrorPrefix()
    {
        return 'Unable to validate ' . get_class($this) . ': ';
    }

    protected function doValidate()
    {
        if (!$this->isValidationNeeded()) {
            return $this;
        }

        $this->clearValidationErrors()->validate();

        if ($this->validationErrors) {
            $prefix = static::validationErrorPrefixNeeded()
                ? $this->validationErrorPrefix()
                : '';
            $e = new \diValidationException(
                $prefix . join("\n", $this->preparedValidationErrors())
            );
            $e->setErrors($this->validationErrors);

            throw $e;
        }

        return $this;
    }

    protected function addValidationError($text, $field = null)
    {
        if ($field === null) {
            $this->validationErrors[] = $text;
        } else {
            $this->validationErrors[$field] = $text;
        }

        return $this;
    }

    /**
     * This could be overridden
     * @return $this
     */
    public function validate()
    {
        /* example
		if (!$this->hasTitle()) {
			$this->addValidationError('Title required');
		}
		*/

        return $this;
    }

    /**
     * Override this if you need to update error message (e.g. localize it)
     * @var $exception \Exception
     * @return string
     */
    public static function getMessageOfSaveException($exception)
    {
        return $exception->getMessage();
    }

    /**
     * Override this if you need to update error message (e.g. localize it)
     * @var $exception \diValidationException
     * @return array
     */
    public static function getMessagesOfValidationException($exception)
    {
        return $exception->getErrors();
    }

    /**
     * If 'true' returned, all fields are saved on ->save()
     * If 'false' - only changed
     *
     * @return bool
     */
    protected function saveAllFields()
    {
        return false;
    }

    /**
     * Called before validation and storing to database
     * @return $this
     */
    public function prepareForSave()
    {
        return $this;
    }

    /**
     * Called between validation and storing to database
     * @return $this
     */
    public function beforeSave()
    {
        if (method_exists($this, 'generateTimestamps')) {
            $this->generateTimestamps();
        }

        return $this;
    }

    /**
     * Called after storing to database
     * @return $this
     */
    public function afterSave()
    {
        return $this;
    }

    /**
     * Validates model and saves data to database
     *
     * @return $this
     */
    public function save()
    {
        if ($this->readOnly) {
            throw new \diDatabaseException('Unable to save read-only model');
        }

        try {
            $this->prepareForSave()
                ->doValidate()
                ->startTransaction()
                ->beforeSave()
                ->saveToDb()
                ->afterSave()
                ->commitTransaction()
                ->setOrigData();
        } catch (\diRuntimeErrorsException $e) {
            $this->rollbackTransaction();

            throw $e;
        }

        return $this;
    }

    /**
     * Removes model data from memory
     *
     * @return $this
     */
    public function destroy()
    {
        $this->ar = [];
        $this->relatedData = [];
        $this->id = null;

        return $this;
    }

    /**
     * Removes orig model data
     *
     * @return $this
     */
    public function destroyOrig()
    {
        $this->origData = [];
        $this->origId = null;

        return $this;
    }

    /**
     * Removes cached data
     *
     * @return $this
     */
    public function destroyCached()
    {
        $this->cachedData = [];

        return $this;
    }

    /**
     * Removes model data, database record, all related files and related data in other tables
     *
     * @return $this
     */
    public function hardDestroy()
    {
        if ($this->readOnly) {
            throw new \diDatabaseException('Unable to kill read-only model');
        }

        try {
            $this->prepareForKill()
                ->startTransaction()
                ->beforeKill()
                ->killFromDb()
                ->afterKill()
                ->commitTransaction()
                ->killRelatedFilesAndData();
        } catch (\diRuntimeErrorsException $e) {
            $this->rollbackTransaction();

            throw $e;
        }

        $this->destroy();

        return $this;
    }

    /**
     * Called before killing record, before transaction
     * @return $this
     */
    protected function prepareForKill()
    {
        return $this;
    }

    /**
     * Called before killing record, inside transaction
     * @return $this
     */
    protected function beforeKill()
    {
        return $this;
    }

    /**
     * Called after killing record, inside transaction
     * @return $this
     */
    protected function afterKill()
    {
        return $this;
    }

    /**
     * If returned true, the field is not included to the query on ->save()
     * @param $field
     * @return bool
     */
    protected function isFieldExcludedOnSave($field)
    {
        return false;
    }

    protected function getStrictChangeOnSaveFields()
    {
        return array_merge(
            $this->strictChangeOnSaveFields,
            $this->customStrictChangeOnSaveFields
        );
    }

    protected function isStrictChangeOnSave($field)
    {
        return in_array($field, $this->getStrictChangeOnSaveFields());
    }

    /**
     * @return array
     */
    protected function getRawDataForDb()
    {
        $ar = [];

        foreach ($this->ar as $k => $v) {
            if (
                ($this->saveAllFields() ||
                    !$this->hasId() ||
                    $this->changed($k, $this->isStrictChangeOnSave($k))) &&
                !$this->isFieldExcludedOnSave($k)
            ) {
                $ar[$k] = $v;
            }
        }

        if (
            !$this->isIdAutoIncremented() &&
            $this->changed(static::getIdFieldName())
        ) {
            $ar[static::getIdFieldName()] = $this->getId();
        }

        return $ar;
    }

    protected function escapeValue($value, $field)
    {
        $binary = in_array($field, $this->getBinaryFields());

        return $this->getDb()->escape_string($value, $binary);
    }

    /**
     * @return array
     */
    protected function getDataForDb()
    {
        $ar = $this->getRawDataForDb();

        foreach ($ar as $k => &$v) {
            if (is_scalar($v)) {
                if ($this->isIpField($k)) {
                    $v = static::tuneFieldValueByTypeBeforeDb($k, $v);
                }

                $v = $this->escapeValue($v, $k);
            }
        }

        $ar = $this->processFieldsOnSave($ar);

        if (static::getConnection()::isMongo()) {
            foreach ($this->getFieldTypes() as $field => $type) {
                if ($type == FieldType::timestamp) {
                    if (!isset($ar[$field])) {
                        $ar[$field] = 'now';
                    }
                }
            }

            foreach ($ar as $field => &$value) {
                if ($value === null) {
                    continue;
                }

                $value = static::tuneFieldValueByTypeBeforeDb($field, $value);
            }
        }

        return $ar;
    }

    /**
     * Storing model's data to database
     *
     * @return $this
     */
    protected function saveToDb()
    {
        $ar = $this->getDataForDb();

        if (!count($ar)) {
            return $this;
        }

        if ($this->isInsertOrUpdateAllowed()) {
            if (static::getConnection()::isMongo()) {
                $keys = array_combine(
                    $this->upsertFields,
                    array_map(function ($field) {
                        return $this->get($field);
                    }, $this->upsertFields)
                );

                $replaceResult = $this->getCollectionResource()->replaceOne(
                    $keys,
                    $ar,
                    [
                        'upsert' => true,
                    ]
                );
                /** @var ObjectId $id */
                $id = $replaceResult->getUpsertedId();

                if ($id) {
                    $this->setId((string) $id);
                }
            } else {
                $result = $this->getDb()->insert_or_update(
                    $this->getDb()->escapeTable($this->getTable()),
                    $ar
                );

                $this->disallowInsertOrUpdate();

                if ($result) {
                    $this->setId((int) $result);
                } else {
                    $e = new \diDatabaseException(
                        'Unable to insert/update ' .
                            get_class($this) .
                            ' in DB: ' .
                            join("\n", $this->getDb()->getLog())
                    );
                    $e->setErrors($this->getDb()->getLog());

                    throw $e;
                }
            }
        } elseif (
            $this->getId() &&
            ($this->isIdAutoIncremented() ||
                (!$this->isIdAutoIncremented() && $this->getOrigId()))
        ) {
            if (static::getConnection()::isMongo()) {
                $a = $this->prepareIdAndFieldForGetRecord($this->getId(), 'id');

                $this->getCollectionResource()->updateOne(
                    [
                        $a['field'] => $a['id'],
                    ],
                    [
                        '$set' => $ar,
                    ]
                );
            } else {
                $result = $this->getDb()->update(
                    $this->getDb()->escapeTable($this->getTable()),
                    $ar,
                    'WHERE ' .
                        $this->getDb()->escapeFieldValue(
                            $this->getIdFieldName(),
                            $this->getId()
                        ) .
                        $this->getDb()->getUpdateSingleLimit()
                );

                if (!$result) {
                    $e = new \diDatabaseException(
                        'Unable to update ' .
                            get_class($this) .
                            ' in DB: ' .
                            join("\n", $this->getDb()->getLog())
                    );
                    $e->setErrors($this->getDb()->getLog());

                    throw $e;
                }
            }
        } else {
            if (static::getConnection()::isMongo()) {
                $insertResult = $this->getCollectionResource()->insertOne($ar); //['fsync' => true,]
                /** @var ObjectId $id */
                $id = $insertResult->getInsertedId();

                if ($id) {
                    $this->setId((string) $id);
                }
            } else {
                $id = $this->getDb()->insert(
                    $this->getDb()->escapeTable($this->getTable()),
                    $ar
                );

                if ($id === false) {
                    $e = new \diDatabaseException(
                        'Unable to insert ' .
                            get_class($this) .
                            ' into DB: ' .
                            join("\n", $this->getDb()->getLog())
                    );
                    $e->setErrors($this->getDb()->getLog());

                    throw $e;
                }

                if ($id) {
                    $this->setId($id);
                }
            }
        }

        return $this;
    }

    protected function killFromDb()
    {
        if ($this->hasId()) {
            if (static::getConnection()::isMongo()) {
                $a = $this->prepareIdAndFieldForGetRecord($this->getId(), 'id');

                $this->getCollectionResource()->deleteOne([
                    $a['field'] => $a['id'],
                ]);
            } else {
                if (
                    !$this->getDb()->delete(
                        $this->getDb()->escapeTable($this->getTable()),
                        $this->getId()
                    )
                ) {
                    $e = new \diDatabaseException(
                        'Unable to delete ' .
                            get_class($this) .
                            ' in DB: ' .
                            join("\n", $this->getDb()->getLog())
                    );
                    $e->setErrors($this->getDb()->getLog());

                    throw $e;
                }
            }
        }

        return $this;
    }

    public function killRelatedFilesAndData()
    {
        return $this->killRelatedFiles()->killRelatedData();
    }

    /**
     * Override this in child classes: kill records in link tables and other stuff
     *
     * @return $this
     */
    public function killRelatedData()
    {
        return $this;
    }

    /**
     * Returns array of file fields of the model
     *
     * @return array
     */
    public function getFileFields()
    {
        return array_merge(
            $this->fileFields,
            $this->customFileFields,
            $this->getPicFields()
        );
    }

    /**
     * Returns array of pic fields of the model
     *
     * @return array
     */
    public function getPicFields()
    {
        return array_merge($this->picFields, $this->customPicFields);
    }

    /**
     * Returns array of date fields of the model
     *
     * @return array
     */
    public function getDateFields()
    {
        return array_merge($this->dateFields, $this->customDateFields);
    }

    /**
     * Returns array of IP fields of the model
     *
     * @return array
     */
    public function getIpFields()
    {
        return array_merge($this->ipFields, $this->customIpFields);
    }

    /**
     * Returns array of binary fields of the model
     *
     * @return array
     */
    public static function getBinaryFields()
    {
        return array_merge(static::$binaryFields, static::$customBinaryFields);
    }

    /**
     * Returns array of model fields and table prefix
     *
     * @return array
     */
    public static function getFieldsWithTablePrefix(
        $prefix = '',
        $fieldPrefix = ''
    ) {
        $m = new static();

        return array_map(
            function ($field) use ($prefix, $fieldPrefix) {
                return ($prefix ? $prefix . '.' : '') . $fieldPrefix . $field;
            },
            $m->fields,
            static::createCollection()->hasUniqueId() ? ['id'] : []
        );
    }

    /**
     * @return \diCollection
     * @throws \Exception
     */
    public static function createCollection()
    {
        $type = preg_replace('/^di_|_model/', '', underscore(static::class));

        return \diCollection::create($type);
    }

    public static function createTableInDatabase()
    {
        static::getConnection()
            ->getDb()
            ->q(static::getCreateTableQuery());
    }

    // todo: implement this function to create table from model structure
    public static function getCreateTableQuery()
    {
        $db = static::getConnection()->getDb();

        $tableName = $db->escapeTable(static::table);
        $fields = [];

        foreach (static::getFieldTypes() as $field => $type) {
            $null = ' NULL';
            $fields[] =
                $db->escapeField($field) .
                ' ' .
                FieldType::type($type, static::getConnection()) .
                $null;
        }

        $fieldQuery = join(',', $fields);

        return "CREATE TABLE IF NOT EXISTS {$tableName} ({$fieldQuery})
DEFAULT CHARSET = 'utf8'
COLLATE = 'utf8_general_ci'
ENGINE = InnoDB;";
    }

    /**
     * @param string|array|null $field If null, files for all fields returned
     * @return array
     * @throws \Exception
     */
    public function getRelatedFilesList($field = null)
    {
        $killFiles = [];

        $fileFields = $field
            ? (is_array($field)
                ? $field
                : [$field])
            : $this->getFileFields();

        $subFolders = array_merge(
            [
                '',
                get_tn_folder(),
                get_tn_folder(2),
                get_tn_folder(3),
                get_big_folder(),
                get_orig_folder(),
                getFilesFolder(),
            ],
            $this->customFileFolders
        );

        // own pics
        $picsFolder = $this->getPicsFolder();

        foreach ($fileFields as $field) {
            if ($this->has($field)) {
                foreach ($subFolders as $subFolder) {
                    $killFiles[] =
                        $picsFolder . $subFolder . $this->get($field);
                }
            }
        }

        return $killFiles;
    }

    protected function getFileSystemBasePath(
        $endingSlashNeeded = true,
        $field = null
    ) {
        return \diPaths::fileSystem($this, $endingSlashNeeded, $field);
    }

    public function killRelatedFiles($field = null)
    {
        if (!$this->exists()) {
            return $this;
        }

        $filesToKill = $this->getRelatedFilesList($field);
        $basePath = $this->getFileSystemBasePath(true, $field);

        // killing time
        foreach ($filesToKill as $fn) {
            if ($fn && is_file($basePath . $fn)) {
                unlink($basePath . $fn);

                if (is_file($basePath . $fn . \diImage::EXT_WEBP)) {
                    unlink($basePath . $fn . \diImage::EXT_WEBP);
                }
            }
        }

        if ($field === null && $this->getTable() !== 'dipics') {
            $pics = DynamicPics::createByTarget(
                $this->getTable(),
                $this->getId()
            );

            if ($pics->count()) {
                $pics->hardDestroy();
            }
        }

        return $this;
    }

    public function resetFieldsOfRelatedFiles($field = null)
    {
        $fileFields = $field ? [$field] : $this->getFileFields();

        $fieldSuffixes = ['', '_tn', '_tn2', '_tn3'];

        foreach ($fileFields as $field) {
            if ($this->exists($field)) {
                $this->set($field, '');
            }

            foreach ($fieldSuffixes as $suffix) {
                if (
                    $this->exists($field . $suffix . '_w') &&
                    $this->exists($field . $suffix . '_h')
                ) {
                    $this->set($field . $suffix . '_w', 0)->set(
                        $field . $suffix . '_h',
                        0
                    );
                }
            }
        }

        return $this;
    }

    public function generateFileName($field, $origFilename, $options = [])
    {
        $options = extend(
            [
                'force' => false,
                'length' => 10,
                'checkMode' => 'db', // db/fs
            ],
            $options
        );

        $ext = get_file_ext($origFilename);

        if ($options['force'] || !$this->has($field)) {
            do {
                $this->set(
                    $field,
                    get_unique_id($options['length']) . '.' . $ext
                );

                $exists =
                    $options['checkMode'] == 'db'
                        ? \diCollection::create(static::type)
                                ->filterBy($field, $this->get($field))
                                ->count() > 0
                        : is_file(
                            \diPaths::fileSystem($this) .
                                $this->getPicsFolder() .
                                $this->get($field)
                        );
            } while ($exists);
        }

        return $this;
    }

    private function startTransaction()
    {
        $this->getDb()->startTransaction();

        return $this;
    }

    private function commitTransaction()
    {
        $this->getDb()->commitTransaction();

        return $this;
    }

    private function rollbackTransaction()
    {
        $this->getDb()->rollbackTransaction();

        return $this;
    }

    protected function checkId()
    {
        if (isset($this->ar[static::getIdFieldName()])) {
            $this->id = $this->ar[static::getIdFieldName()];

            $this->kill(static::getIdFieldName());
        }

        return $this;
    }

    protected function checkOrigId()
    {
        if (isset($this->origData[static::getIdFieldName()])) {
            $this->origId = $this->origData[static::getIdFieldName()];

            $this->killOrig(static::getIdFieldName());
        }

        return $this;
    }

    /**
     * Returns query conditions array for order_num calculating
     * @todo Add support of assoc array to use in non-relational databases
     * @return array
     */
    public function getQueryArForMove()
    {
        $ar = [];

        if ($this->exists('parent')) {
            $ar[] = $this->getDb()->escapeFieldValue(
                'parent',
                $this->get('parent')
            );
        }

        return $ar;
    }

    /**
     * @param integer $direction    Should be 1 or -1
     * @return $this
     */
    public function calculateAndSetOrderNum($direction = 1)
    {
        $init_value = $direction > 0 ? 1 : 65000;
        $sign = $direction > 0 ? 1 : -1;
        $min_max = $direction > 0 ? 'MAX' : 'MIN';

        $qAr = $this->getQueryArForMove();
        $query = $qAr ? 'WHERE ' . join(' AND ', $qAr) : '';
        $field = static::normalizeFieldName(
            static::order_field_name ?: $this->orderFieldName
        );

        $order_r = $this->getDb()->r(
            $this->getDb()->escapeTable($this->getTable()),
            $query,
            "{$min_max}({$field}) AS num,COUNT(id) AS cc"
        );
        $this->set(
            $field,
            $order_r && $order_r->cc
                ? intval($order_r->num) + $sign
                : $init_value
        );

        return $this;
    }

    public function calculateAndSetOrderAndLevelNum($updateNeighbors = true)
    {
        $h = new \diHierarchyTable($this->getTable());
        $parent = $this->get('parent');
        $skipIdsAr = $parent ? $h->getChildrenIdsAr($parent, [$parent]) : [];

        $r = $this->getDb()->r(
            $this->getTable(),
            $skipIdsAr ?: '',
            'MAX(order_num) AS num'
        );

        $this->set('level_num', $h->getChildLevelNum($parent))->set(
            'order_num',
            (int) $r->num + 1
        );

        if ($updateNeighbors) {
            $this->getDb()->update(
                $this->getTable(),
                [
                    '*order_num' => 'order_num + 1',
                ],
                'WHERE ' .
                    $this->getDb()->escapeFieldValue(
                        'order_num',
                        $this->get('order_num'),
                        '>='
                    )
            );
        }

        return $this;
    }

    /**
     * Implementation of ArrayAccess::offsetSet()
     *
     * @link http://www.php.net/manual/en/arrayaccess.offsetset.php
     * @param string $offset
     * @param mixed $value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset == $this->getIdFieldName()) {
            return $this->setId($value);
        }

        return $this->set($offset, $value);
    }

    /**
     * Implementation of ArrayAccess::offsetExists()
     *
     * @link http://www.php.net/manual/en/arrayaccess.offsetexists.php
     * @param string $offset
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        if ($offset == $this->getIdFieldName()) {
            return !!$this->getId();
        }

        return $this->exists($offset) ||
            !!$this->getExtendedTemplateVar($offset);
    }

    /**
     * Implementation of ArrayAccess::offsetUnset()
     *
     * @link http://www.php.net/manual/en/arrayaccess.offsetunset.php
     * @param string $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        return $this->kill($offset);
    }

    /**
     * Implementation of ArrayAccess::offsetGet()
     *
     * @link http://www.php.net/manual/en/arrayaccess.offsetget.php
     * @param string $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->has($offset)) {
            return $this->get($offset);
        }

        if ($this->has(underscore($offset))) {
            return $this->get(underscore($offset));
        }

        if ($this->has(camelize($offset))) {
            return $this->get(camelize($offset));
        }

        return $this->getExtendedTemplateVar($offset);
    }

    public function asPhp($excludeFields = [])
    {
        $s =
            '\\diModel::create(\\diTypes::' .
            \diTypes::getName(static::type) .
            ', ';
        $s .= $this->asPhpArray($excludeFields);
        $s .= ')';
        $s .= $this->getSuffixForPhpView();

        return $s;
    }

    /**
     * This is used when one needs to add some related fields to cache or execute some method
     * @return string
     */
    protected function getSuffixForPhpView()
    {
        return '';
    }

    public static function escapeValueForFile($value)
    {
        if ($value === null) {
            return 'null';
        } elseif (is_numeric($value)) {
            return $value;
        }

        $value = "$value"; //$value ?: '';

        if (strpos($value, "\n") !== false) {
            $value = "<<<'EOF'\n" . $value . "\nEOF\n";
        } else {
            $value =
                "'" .
                str_replace("'", "\\'", str_replace('\\', '\\\\', $value)) .
                "'";
        }

        return $value;
    }

    public function asPhpArray($excludeFields = [])
    {
        $s = '';

        foreach ($this->get() as $field => $value) {
            if (in_array($field, $excludeFields)) {
                continue;
            }

            $value = static::escapeValueForFile($value);

            $s .= "'$field'=>$value,\n";
        }

        return "[\n" . $s . ']';
    }

    public function allowInsertOrUpdate()
    {
        $this->insertOrUpdateAllowed = true;

        return $this;
    }

    public function disallowInsertOrUpdate()
    {
        $this->insertOrUpdateAllowed = false;

        return $this;
    }

    public function isInsertOrUpdateAllowed()
    {
        return $this->insertOrUpdateAllowed;
    }

    /**
     * @return boolean
     */
    public function isIdAutoIncremented()
    {
        return $this->idAutoIncremented;
    }

    /**
     * @param boolean $idAutoIncremented
     * @return $this
     */
    public function setIdAutoIncremented($idAutoIncremented)
    {
        $this->idAutoIncremented = $idAutoIncremented;

        return $this;
    }

    public function setFieldsOnSaveCallback(callable $fieldsOnSaveCallback)
    {
        $this->fieldsOnSaveCallback = $fieldsOnSaveCallback;

        return $this;
    }

    public function processFieldsOnSave($ar)
    {
        if ($this->fieldsOnSaveCallback) {
            $cb = $this->fieldsOnSaveCallback;

            $ar = $cb($ar);
        }

        return $ar;
    }

    public static function getFieldTypes()
    {
        $ar = extend(static::$fieldTypes, static::$customFieldTypes);
        $ar = array_filter($ar, function ($type) {
            return $type !== FieldType::unset;
        });

        return $ar;
    }

    public static function getFieldType($field)
    {
        $ar = static::getFieldTypes();

        return $ar[$field] ?? null;
    }

    public function setUpsertFields(array $fields)
    {
        $this->upsertFields = $fields;

        return $this;
    }

    public static function getPublicFields()
    {
        return static::$publicFields;
    }

    public function getPublicData()
    {
        return ArrayHelper::filterByKey(
            extend(
                $this->getTemplateVarsExtended(),
                $this->getBasicTemplateVars()
            ),
            static::getPublicFields()
        );
    }

    /**
     * @return \MongoDB\Collection
     */
    protected function getCollectionResource()
    {
        if (!$this->collectionResource) {
            $this->collectionResource = $this->getDb()
                ->getLink()
                ->selectCollection($this->getTable());
        }

        return $this->collectionResource;
    }

    public static function tuneFieldValueByTypeAfterDb($field, $value)
    {
        if ($value instanceof ObjectID) {
            return (string) $value;
        } elseif ($value instanceof UTCDatetime) {
            return \diDateTime::sqlFormat($value->toDateTime()->getTimestamp());
        }

        return $value;
    }

    public static function tuneFieldValueByTypeBeforeDb($field, $value)
    {
        $type = static::getFieldType($field);

        if (is_array($value)) {
            foreach ($value as $k => &$v) {
                $v = static::tuneFieldValueByTypeBeforeDb($field, $v);
            }

            return $value;
        }

        if (
            static::getConnection()::isMongo() &&
            $field == static::getIdFieldName()
        ) {
            if (!$value instanceof ObjectID) {
                return new ObjectID($value);
            }
        }

        switch ($type) {
            case FieldType::mongo_id:
                $value = new ObjectID($value);
                break;

            case FieldType::int:
                $value = (int) $value;
                break;

            case FieldType::float:
                $value = (float) $value;
                break;

            case FieldType::double:
                $value = (float) $value;
                break;

            case FieldType::bool:
                $value = !!$value;
                break;

            case FieldType::timestamp:
            case FieldType::datetime:
                if (
                    static::getConnection()::isMongo() &&
                    !$value instanceof UTCDatetime
                ) {
                    $value = new UTCDatetime(
                        (new \DateTime($value))->getTimestamp() * 1000
                    );
                }
                break;

            case FieldType::ip_string:
                if (isInteger($value)) {
                    $value = bin2ip($value);
                }
                break;

            case FieldType::ip_int:
                if (!isInteger($value)) {
                    $value = ip2bin($value);
                }
                break;
        }

        return $value;
    }

    public function setPasswordExt($password, $field = 'password')
    {
        $this->hashPassword($password, $field)->setRelated($field, $password);

        return $this;
    }

    public function hashPassword($password, $field = 'password')
    {
        if ($password) {
            $this->set($field, static::hash($password, 'db', 'raw', $field));
        }

        return $this;
    }

    /**
     * @param string $password
     * @param string $source (raw|db|cookie)
     *
     * return boolean
     */
    public function isPasswordOk(
        $password,
        $source = 'raw',
        $field = 'password'
    ) {
        $storedPassword = $this->get($field);

        if (!$password || !$storedPassword) {
            return false;
        }

        switch ($source) {
            default:
            case 'raw':
                return $this->verifyPasswordToDb($password, $field);

            case 'db':
                return $password == $storedPassword;

            case 'cookie':
                return $password ==
                    static::hashPasswordFromDbToCookie($storedPassword, $field);
        }
    }

    public static function hashPasswordFromRawToDb($rawPassword, $field = null)
    {
        if (static::use_insecure_password_hash) {
            return md5($rawPassword);
        }

        return password_hash($rawPassword, PASSWORD_BCRYPT);
    }

    public function verifyPasswordToDb($rawPassword, $field = null)
    {
        $hash = $this->get($field ?: 'password');

        if (static::use_insecure_password_hash) {
            return static::hashPasswordFromRawToDb($rawPassword) === $hash;
        }

        return password_verify($rawPassword, $hash);
    }

    /**
     * @deprecated
     * @todo: create sessions table and keep session id in cookies
     * @param $password
     * @param string|null $field
     * @return string
     */
    public static function hashPasswordFromDbToCookie($password, $field = null)
    {
        if (static::use_insecure_password_hash) {
            return md5($password);
        }

        return $password;
    }

    /**
     * @param string $password
     * @param string $destination (raw|db|cookie)
     *
     * @return string
     */
    public static function hash(
        $password,
        $destination = 'raw',
        $source = 'raw',
        $field = null
    ) {
        if ($destination == $source) {
            return $password;
        }

        switch ($destination) {
            case 'raw':
                return $password;

            case 'db':
                return static::hashPasswordFromRawToDb($password, $field);

            case 'cookie':
                if ($source == 'raw') {
                    $password = static::hash($password, 'db', 'raw', $field);
                }

                return static::hashPasswordFromDbToCookie($password, $field);
        }

        return null;
    }

    public function getAppearanceFeedForAdmin()
    {
        return [$this->get('title'), $this->get('name')];
    }

    public function getStringAppearanceForAdmin()
    {
        return join(', ', array_filter($this->getAppearanceFeedForAdmin()));
    }

    public function appearanceForAdmin($options = [])
    {
        $linkWord = Form::L('link', $this->__getLanguage());

        return $this->exists()
            ? $this->getStringAppearanceForAdmin() .
                    sprintf(
                        ' [<a href="%s" target="_blank">%s</a>]',
                        $this->getAdminHref(),
                        $linkWord
                    )
            : '---';
    }

    public function __toString()
    {
        $name = static::type ? \diTypes::getName(static::type) : 'undefined';

        return '[Model:' . $name . '#' . $this->getId() . ']';
    }
}
