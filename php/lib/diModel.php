<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.06.2015
 * Time: 18:51
 */

use diCore\Base\CMS;
use diCore\Data\Config;

class diModel implements \ArrayAccess
{
	const MAX_PREVIEWS_COUNT = 3;

	const SLUG_FIELD_NAME_LEGACY = 'clean_title';
	const SLUG_FIELD_NAME = 'slug';

	const LOCALIZED_PREFIX = 'localized_';

	// Model type (from diTypes class). Should be redefined
	const type = null;
	const connection_name = null;
	const table = null;
	const id_field_name = 'id';
	const slug_field_name = null; //self::SLUG_FIELD_NAME_LEGACY; get this back when all models are updated
	const order_field_name = 'order_num';
	const use_data_cache = false;

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

	/** @var int|null */
	protected $id;
	/** @var int|null */
	protected $origId;
	/** @deprecated */
	protected $idAutoIncremented = true;
	/** @deprecated */
	protected $slugFieldName = self::SLUG_FIELD_NAME_LEGACY;
	/** @deprecated */
	protected $orderFieldName = 'order_num';
	/** @var string - 'id' or 'slug', auto-detect by default */
	protected $identityFieldName;

	protected $picsFolder = null;
	protected $picsTnFolders = [];

	/** @var array Fields of the model */
	protected $fields = [];

	protected $picFields = ['pic', 'pic2', 'ico'];
	/* redefine this in child class */
	protected $customPicFields = [];

	protected $fileFields = ['pic', 'pic2', 'pic3', 'pic_main', 'ico', 'flv', 'mp3', 'swf', 'final_pic'];
	protected $customFileFields = [];
	protected $customFileFolders = [];

	/* this will be automatically generated on model creation */
	protected $localizedFields = [];
	protected $customLocalizedFields = [];

	protected $dateFields = ['date', 'created_at', 'edited_at', 'updated_at'];
	/* redefine this in child class */
	protected $customDateFields = [];

	protected $ipFields = ['ip'];
	/* redefine this in child class */
	protected $customIpFields = [];

	protected $forceGetRecord = false;

	protected $validationNeeded = true;
	protected $validationErrors = [];

	private $insertOrUpdateAllowed = false;

	/**
	 * @param null|array|object $ar
	 * @param null|string $table
	 */
	public function __construct($ar = null, $table = null)
	{
		if ($table)
		{
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
		if (isInteger($type))
		{
			$type = \diTypes::getName($type);
		}

		$className = \diLib::getClassNameFor($type, \diLib::MODEL);

		if (!\diLib::exists($className))
		{
			return false;
		}

		return $return == 'class' ? $className : $type;
	}

	public static function getCollectionClass()
	{
		return \diLib::getChildClass(static::class, 'Collection');
	}

	/**
	 * @param $type
	 * @param null|array|object $ar
	 * @param array $options
	 * @return diModel
	 * @throws \Exception
	 */
	public static function create($type, $ar = null, $options = [])
	{
		if (is_scalar($options))
		{
			$options = [
				'identityFieldName' => $options,
			];
		}

		$options = extend([
			'identityFieldName' => null,
		], $options);

		$className = self::existsFor($type);

		if (!$className)
		{
			throw new \Exception("Model class doesn't exist: " . ($className ?: $type));
		}

		/** @var diModel $o */
		$o = new $className();

		if ($options['identityFieldName'])
		{
			$o->setIdentityFieldName($options['identityFieldName']);
		}

		$o->initFrom($ar);

		return $o;
	}

	/**
	 * @param $table
	 * @param null|array|object $ar
	 * @param array $options
	 * @return diModel
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
	 * @return diModel
	 * @throws \Exception
	 */
	public static function createForTableNoStrict($table, $ar = null, $options = [])
	{
		$type = \diTypes::getNameByTable($table);
		$typeName = self::existsFor($type, 'type');

		return $typeName
			? static::create($typeName, $ar, $options)
			: new static($ar, $table);
	}

	public function __call($method, $arguments)
	{
		$fullMethod = underscore($method);
		$value = isset($arguments[0]) ? $arguments[0] : null;

		$x = strpos($fullMethod, '_');
		$method = substr($fullMethod, 0, $x);
		$field = substr($fullMethod, $x + 1);

		switch ($method)
		{
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
		if (!$arguments && ($value = $this->getExtendedTemplateVar($fullMethod)) !== null)
		{
			return $value;
		}

		throw new \Exception(
			sprintf('Invalid method %s::%s(%s)', get_class($this), $method, print_r($arguments, 1))
		);
	}

	public function initFrom($r)
	{
		if (is_object($r) && $r instanceof \diModel)
		{
			$r = (array)$r->get();
		}
		elseif (is_object($r) || is_array($r))
		{
			$r = (array)$r;
		}

		$this->ar = is_array($r)
			? $r
			: ($r || $this->forceGetRecord ? $this->getRecord($r) : []);

		if ($this->ar instanceof \diModel)
		{
			$m = $this->ar;
			$this->ar = [];

			$this
				->set($m->get())
				->setRelated($m->getRelated());
		}
		else
		{
			$this->ar = $this->ar ? (array)$this->ar : [];
		}

		$this
			->checkId()
			->setOrigData();

		return $this;
	}

	public function initFromRequest($method = 'post')
	{
		foreach (\diRequest::all($method) as $key => $value)
		{
			$this->set($key, $value);
		}

		return $this;
	}

	public function setIdentityFieldName($field)
	{
		$this->identityFieldName = $field;

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
		return static::type
			? \diTypes::getName(static::type)
			: null;
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

	protected function __getPrefixForHref()
	{
		global $Z;

		if (
			!empty($GLOBALS['CURRENT_LANGUAGE']) &&
			in_array($GLOBALS['CURRENT_LANGUAGE'], \diCurrentCMS::$possibleLanguages) &&
			$GLOBALS['CURRENT_LANGUAGE'] != 'ru'
		) {
			$prefix = '/' . $GLOBALS['CURRENT_LANGUAGE'];
		}
		elseif (!empty($Z))
		{
			$prefix = $Z->language_href_prefix;
		}
		else
		{
			$prefix = '';
		}

		return $prefix;
	}

	protected static function __getLanguage()
	{
		/** @var CMS $Z */
		global $Z;
		/** @var \diCore\Admin\Base $X */
		global $X;

		if (
			!empty($GLOBALS['CURRENT_LANGUAGE']) &&
			in_array($GLOBALS['CURRENT_LANGUAGE'], \diCurrentCMS::$possibleLanguages) &&
			$GLOBALS['CURRENT_LANGUAGE'] != \diCurrentCMS::$defaultLanguage
		) {
			$language = $GLOBALS['CURRENT_LANGUAGE'];
		}
		elseif (!empty($Z))
		{
			$language = $Z->getLanguage();
		}
		elseif (!empty($X))
		{
			$language = $X->getLanguage();
		}
		else
		{
			$language = \diCurrentCMS::$defaultLanguage;
		}

		return $language;
	}

	public function getSlugFieldName()
	{
		return static::slug_field_name ?: $this->slugFieldName;
	}

	public final function getRawSlug()
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
		return $this->exists($this->getSlugFieldName());
	}

	public function killSlug()
	{
		return $this->kill($this->getSlugFieldName());
	}

	public function getSourceForSlug()
	{
		return $this->get('slug_source') ?: $this->get('en_title') ?: $this->get('title');
	}

	public function generateSlug($source = null, $delimiter = '-')
	{
		$this->setSlug(\diSlug::generate($source ?: $this->getSourceForSlug(), $this->getTable(), $this->getId(),
			$this->getIdFieldName(), $this->getSlugFieldName(), $delimiter
		));

		return $this;
	}

	public static function getIdFieldName()
	{
		return static::id_field_name;
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
		if ($index < 2)
		{
			$index = '';
		}

		$this->picsTnFolders[$index] = $folder;

		return $this;
	}

	public function getTnFolder($index = '')
	{
		if ($index < 2)
		{
			$index = '';
		}

		return isset($this->picsTnFolders[$index]) ? $this->picsTnFolders[$index] : get_tn_folder($index);
	}

	public function wrapFileWithPath($filename, $previewIdx = null)
	{
		$tnFolder = $previewIdx === null
			? ''
			: $this->getTnFolder($previewIdx);

		return $filename
			? \diPaths::http($this) . $this->getPicsFolder() . $tnFolder . $filename
			: '';
	}

	public function getFilesForRotation($field)
	{
		return [
			$this[$field . '_with_path'],
		];
	}

	public function getTemplateVars()
	{
		$ar = [];

		if (!$this->exists())
		{
			return $ar;
		}

		foreach ($this->ar as $k => $v)
		{
			$isLocalized = $this->isFieldLocalized($k);

			if ($this->isPicField($k))
			{
				for ($i = 1; $i <= static::MAX_PREVIEWS_COUNT; $i++)
				{
					$idx = $i > 1 ? $i : '';

					if (!$this->exists($k . '_tn' . $idx))
					{
						$ar[$k . '_tn' . $idx] =
						$ar[$k . '_tn' . $idx . '_with_path'] = $this->wrapFileWithPath($v, $i);

						if ($isLocalized)
						{
							$ar[static::LOCALIZED_PREFIX . $k . '_tn' . $idx] =
							$ar[static::LOCALIZED_PREFIX . $k . '_tn' . $idx . '_with_path'] =
								$this->wrapFileWithPath($this->localized($k), $i);
						}
					}
					else
					{
						$ar[$k . '_tn' . $idx . '_with_path'] = $this->wrapFileWithPath($this->get($k . '_tn' . $idx), $i);

						if ($isLocalized)
						{
							$ar[static::LOCALIZED_PREFIX . $k . '_tn' . $idx . '_with_path'] =
								$this->wrapFileWithPath($this->localized($k . '_tn' . $idx), $i);
						}
					}
				}

				if ($v)
				{
					$v = $this->wrapFileWithPath($v);
				}

				$ar[$k . '_with_path'] = $v;

				if ($isLocalized)
				{
					if ($v2 = $this->localized($k))
					{
						$v2 = $this->wrapFileWithPath($v2);
					}

					$ar[static::LOCALIZED_PREFIX . $k . '_with_path'] = $v2;
				}
			}
			elseif ($this->isFileField($k))
			{
				if ($v)
				{
					$v = $this->wrapFileWithPath($v);
				}

				$ar[$k . '_with_path'] = $v;

				if ($isLocalized)
				{
					if ($v2 = $this->localized($k))
					{
						$v2 = $this->wrapFileWithPath($v2);
					}

					$ar[static::LOCALIZED_PREFIX . $k . '_with_path'] = $v2;
				}
			}
			elseif ($this->isDateField($k))
			{
				$v = isInteger($v) ? $v : strtotime($v);

				if ($v)
				{
					$ar[$k . '_time'] = \diDateTime::format('H:i', $v);
					$ar[$k . '_date'] = \diDateTime::format('d.m.Y', $v);
                    $ar[$k . '_iso'] = \diDateTime::isoFormat($v);
					$ar[$k . '_str'] = \diDateTime::format(static::getDateStrFormat(), $v);
					$ar[$k . '_passed_by'] = \diDateTime::passedBy($v);

					$v = $ar[$k . '_date'];
				}
			}
			elseif ($this->isIpField($k))
			{
				$ar[$k . '_num'] = $v;

				$v = isInteger($v) ? bin2ip($v) : $v;

				$ar[$k . '_str'] = $v;
			}

			$ar[$k] = $v;
		}

		foreach ($this->getAllLocalizedFields() as $f)
		{
			$ar[static::LOCALIZED_PREFIX . $f] = $this->localized($f);
		}

		$ar[$this->getIdFieldName()] = $this->getId();
		$ar['slug'] = $this->getSlug(); // back compatibility for clean_title
		$ar['href'] = $this->getHref();
		$ar['full_href'] = $this->getFullHref();
		$ar['admin_href'] = $this->getAdminHref();
		$ar['full_admin_href'] = $this->getFullAdminHref();

		return $ar;
	}

	public static function getDateStrFormat()
	{
		switch (self::normalizeLang())
		{
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
		if (static::use_data_cache)
		{
			if (!$this->existsCached())
			{
				$this
					->setCachedData($this->getCustomTemplateVars());
			}

			$customVars = $this->getCachedData();
		}
		else
		{
			$customVars = $this->getCustomTemplateVars();
		}

		return extend($this->getTemplateVars(), $customVars);
	}

	public function getExtendedTemplateVar($key)
	{
		$templateVars = $this->getTemplateVarsExtended();

		if (isset($templateVars[$key]))
		{
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
		$options = extend([
			'onlyFirstRecord' => false,
			'orderBy' => 'order_num ASC',
			'queryAr' => [
				"visible = '1'",
				"_table = '{$this->getTable()}'",
				"_id = '{$this->getId()}'",
				"_field = '$field'",
			],
			'additionalQueryAr' => [],
		], $options);

		$queryAr = array_merge($options['queryAr'], $options['additionalQueryAr']);
		$limit = $options['onlyFirstRecord'] ? ' LIMIT 1' : '';

		$ar = [];

		$rs = $this->getDb()->rs('dipics', 'WHERE ' . join(' AND ', $queryAr) . ' ORDER BY ' . $options['orderBy'] . $limit);
		while ($r = $this->getDb()->fetch($rs))
		{
			$m = static::create(\diTypes::dynamic_pic, $r);
			$m->setRelated('table', $this->getTable());

			$ar[] = $m;
		}

		return $options['onlyFirstRecord']
			? (isset($ar[0]) ? $ar[0] : null)
			: $ar;
	}

	protected function processIdBeforeGetRecord($id, $field)
	{
		return (int)$id;
	}

	protected function prepareIdAndFieldForGetRecord($id, $fieldAlias = null)
	{
		$id = $this->getDb()->escape_string($id);

		// identifying wood
		$fieldAlias = $fieldAlias ?: $this->identityFieldName;

		if ($fieldAlias == 'id')
		{
			$field = $this->getIdFieldName();
			$id = $this->processIdBeforeGetRecord($id, $fieldAlias);
		}
		elseif ($fieldAlias == 'slug')
		{
			$field = $this->getSlugFieldName();
		}
		else
		{
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
		return isInteger($id) && $id > 0;
	}

	protected function getRecord($id, $fieldAlias = null)
	{
		if (!$this->getTable())
		{
			throw new \Exception('Table not defined');
		}

		$a = $this->prepareIdAndFieldForGetRecord($id, $fieldAlias);
		$ar = $this->getDb()->ar($this->getTable(), "WHERE {$a['field']} = '{$a['id']}'");

		return $this->tuneDataAfterFetch($ar);
	}

	protected function tuneDataAfterFetch($ar)
	{
		return $ar;
	}

	public function moveFieldToRelated($field)
	{
		if ($this->exists($field))
		{
			$this
				->setRelated($field, $this->get($field))
				->kill($field);
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
		foreach ($this->ar as $field => $value)
		{
			if (!in_array($field, $this->fields))
			{
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
		foreach ($this->fields as $field)
		{
			if (!$this->exists($field))
			{
				$this->addValidationError("Field '{$field}' should be defined in " . get_class($this));
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
		return \diCore\Database\Connection::get(static::connection_name ?: \diCore\Database\Connection::DEFAULT_NAME)
			->getDb();
	}

	/**
	 * @param null|string $field
	 * @return bool
	 */
	public function exists($field = null)
	{
		return is_null($field)
			? !!$this->ar
			: isset($this->ar[$field]);
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
		if ($field == static::getIdFieldName())
		{
			return !!$this->getId();
		}

		return !empty($this->ar[$field]);
	}

	public function hasOrig($field)
	{
		if ($field == static::getIdFieldName())
		{
			return !!$this->getOrigId();
		}

		return !empty($this->origData[$field]);
	}

	/**
	 * @param string|null $field
	 * @return string|int|null|object
	 */
	public function get($field = null)
	{
		if (is_null($field))
		{
			return $this->getWithId();
		}

		if ($field == static::getIdFieldName())
		{
			return $this->getId();
		}
		elseif (!$this->exists($field))
		{
			//throw new Exception("Field '$field' is undefined in ".get_class($this));

			return null;
		}

		return $this->ar[$field];
	}

	public function getOrigData($field = null)
	{
		if (is_null($field))
		{
			return $this->getOrigWithId();
		}

		if ($field == static::getIdFieldName())
		{
			return $this->getOrigId();
		}
		elseif (!$this->existsOrig($field))
		{
			return null;
		}

		return $this->origData[$field];
	}

	public function getCachedData($field = null)
	{
		if (is_null($field))
		{
			return $this->cachedData;
		}

		return $this->existsCached($field)
			? $this->origData[$field]
			: null;
	}

	public function localized($field, $lang = null)
	{
		return $this->get(static::getLocalizedFieldName($field, $lang));
	}

	public function getAllLocalizedFields()
	{
		return array_merge($this->localizedFields, $this->customLocalizedFields);
	}

	public function isFieldLocalized($field)
	{
		return in_array($field, $this->getAllLocalizedFields());
	}

	/**
	 * @param null|array|string $field
	 * @return bool
	 */
	public function changed($field = null)
	{
		if (is_array($field))
		{
			$keys = $field;
		}
		elseif ($field === null)
		{
			$keys = array_merge(
				[$this->getIdFieldName()],
				array_keys($this->ar) ?: array_keys($this->origData)
			);
		}
		else
		{
			$keys = [$field];
		}

		foreach ($keys as $key)
		{
			// todo:             !==
			if ($this->get($key) != $this->getOrigData($key))
			{
				return true;
			}
		}

		return false;
	}

	public function changedFields($exclude = [])
	{
		$keys = array_merge(
			[$this->getIdFieldName()],
			array_keys($this->ar) ?: array_keys($this->origData)
		);

		$changedKeys = [];

		foreach ($keys as $key)
		{
			// todo:             !==
			if ($this->get($key) != $this->getOrigData($key) && !in_array($key, $exclude))
			{
				$changedKeys[] = $key;
			}
		}

		return $changedKeys;
	}

	public static function normalizeLang($lang = null, $field = null)
	{
		if ($lang === null)
		{
			$lang = static::__getLanguage();
		}
		elseif (is_object($lang) && $lang instanceof CMS)
		{
			$lang = $lang->getLanguage();
		}

		return $lang;
	}

	public static function getLocalizedFieldName($field, $lang = null)
	{
		$lang = static::normalizeLang($lang, $field);

		if ($lang != \diCore\Data\Config::getMainLanguage())
		{
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
		return $this->hasId() && $this->getId() == $m->getId();
	}

	public function set($field, $value = null)
	{
		if (is_null($value))
		{
			$this->ar = extend($this->ar, $field);
		}
		else
		{
			$this->ar[$field] = $value;
		}

		$this
			->checkId()
			->killCached();

		return $this;
	}

	/**
	 * @param null|array|string $field
	 * @param mixed $value
	 * @return $this
	 */
	public function setOrigData($field = null, $value = null)
	{
		if (is_null($field))
		{
			$this->origData = $this->ar;
			$this->origId = $this->id;
		}
		else
		{
			if (is_scalar($field))
			{
				$field = strtolower($field);
				$this->origData[$field] = $value;
			}
			else
			{
				$this->origData = extend($this->origData, (array)$field);
			}
		}

		return $this;
	}

	/**
	 * @param null|array|string $field
	 * @param mixed $value
	 * @return $this
	 */
	public function setCachedData($field, $value = null)
	{
		if (is_scalar($field))
		{
			$field = strtolower($field);
			$this->cachedData[$field] = $value;
		}
		else
		{
			$this->cachedData = extend($this->cachedData, (array)$field);
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
		if (is_null($field))
		{
			$this->destroy();
		}
		elseif (is_string($field))
		{
			if ($this->exists($field))
			{
				unset($this->ar[$field]);
			}
		}
		elseif (is_array($field))
		{
			foreach ($field as $f)
			{
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
		if (is_null($field))
		{
			$this->destroyOrig();
		}
		elseif (is_string($field))
		{
			if ($this->existsOrig($field))
			{
				unset($this->origData[$field]);
			}
		}
		elseif (is_array($field))
		{
			foreach ($field as $f)
			{
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
		if (is_null($field))
		{
			$this->destroyCached();
		}
		elseif (is_string($field))
		{
			if ($this->existsCached($field))
			{
				unset($this->cachedData[$field]);
			}
		}
		elseif (is_array($field))
		{
			foreach ($field as $f)
			{
				$this->killCached($f);
			}
		}

		return $this;
	}

	public function getRelated($field = null)
	{
		if (is_null($field))
		{
			return $this->relatedData;
		}

		if (!isset($this->relatedData[$field]))
		{
			//throw new \Exception("Field '$field' is undefined in related data of " . get_class($this));
			return null;
		}

		return $this->relatedData[$field];
	}

	public function setRelated($field, $value = null)
	{
		if (is_null($value))
		{
			$this->relatedData = (array)extend($this->relatedData, $field);
		}
		else
		{
			$this->relatedData[$field] = $value;
		}

		return $this;
	}

	public function killRelated($field)
	{
		if (isset($this->relatedData[$field]))
		{
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

	protected function doValidate()
	{
		if (!$this->isValidationNeeded())
		{
			return $this;
		}

		$this
			->clearValidationErrors()
			->validate();

		if ($this->validationErrors)
		{
			$e = new \diValidationException('Unable to validate ' . get_class($this) . ': ' . join("\n", $this->preparedValidationErrors()));
			$e->setErrors($this->validationErrors);

			throw $e;
		}

		return $this;
	}

	protected function addValidationError($text)
	{
		$this->validationErrors[] = $text;

		return $this;
	}

	/**
	 * This could be overridden
	 * @return $this
	 */
	public function validate()
	{
		/* example
		if (!$this->hasTitle())
		{
			$this->addValidationError('Title required');
		}
		*/

		return $this;
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
		try {
			$this
				->prepareForSave()
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
		try {
			$this
				->prepareForKill()
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

	/**
	 * @return array
	 */
	protected function getRawDataForDb()
	{
		$ar = [];

		foreach ($this->ar as $k => $v)
		{
			if (
				($this->saveAllFields() || !$this->hasId() || $this->changed($k)) &&
				!$this->isFieldExcludedOnSave($k)
			   )
			{
				$ar[$k] = $v;
			}
		}

		if (!$this->idAutoIncremented && $this->changed(static::getIdFieldName()))
		{
			$ar[static::getIdFieldName()] = $this->getId();
		}

		return $ar;
	}

	/**
	 * @return array
	 */
	protected function getDataForDb()
	{
		$ar = $this->getRawDataForDb();

		foreach ($ar as $k => &$v)
		{
			$v = $this->getDb()->escape_string($v);
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

		if (!count($ar))
		{
			return $this;
		}

		if ($this->isInsertOrUpdateAllowed())
		{
			$result = $this->getDb()->insert_or_update($this->getTable(), $ar);

			$this->disallowInsertOrUpdate();

			if ($result)
			{
				$this->setId((int)$result);
			}
			else
			{
				$e = new \diDatabaseException('Unable to insert/update ' . get_class($this) . ' in DB: ' .
					join("\n", $this->getDb()->getLog()));
				$e->setErrors($this->getDb()->getLog());

				throw $e;
			}
		}
		elseif ($this->getId() && ($this->idAutoIncremented || (!$this->idAutoIncremented && $this->getOrigId())))
		{
			$result = $this->getDb()->update($this->getTable(), $ar, "WHERE `{$this->getIdFieldName()}` = '{$this->getId()}'");

			if (!$result)
			{
				$e = new \diDatabaseException('Unable to update ' . get_class($this) . ' in DB: ' .
					join("\n", $this->getDb()->getLog()));
				$e->setErrors($this->getDb()->getLog());

				throw $e;
			}
		}
		else
		{
			$id = $this->getDb()->insert($this->getTable(), $ar);

			if ($id === false)
			{
				$e = new \diDatabaseException('Unable to insert ' . get_class($this) . ' into DB: ' .
					join("\n", $this->getDb()->getLog()));
				$e->setErrors($this->getDb()->getLog());

				throw $e;
			}

			if ($id)
			{
				$this->setId($id);
			}
		}

		return $this;
	}

	protected function killFromDb()
	{
		if ($this->hasId())
		{
			$this->getDb()->delete($this->getTable(), $this->getId());
		}

		return $this;
	}

	public function killRelatedFilesAndData()
	{
		return $this
			->killRelatedFiles()
			->killRelatedData();
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
		return array_merge($this->fileFields, $this->customFileFields, $this->getPicFields());
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
	 * Returns array of model fields and table prefix
	 *
	 * @return array
	 */
	public static function getFieldsWithTablePrefix($prefix = '', $fieldPrefix = '')
	{
		$m = new static();

		return array_map(function($field) use($prefix, $fieldPrefix) {
			return ($prefix ? $prefix . '.' : '') . $fieldPrefix . $field;
		}, $m->fields, static::createCollection()->hasUniqueId() ? ['id'] : []);
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

	/**
	 * @param string|array|null $field If null, files for all fields returned
	 * @return array
	 * @throws \Exception
	 */
	public function getRelatedFilesList($field = null)
	{
		$killFiles = [];

		$fileFields = $field
			? (is_array($field) ? $field : [$field])
			: $this->getFileFields();

		$subFolders = array_merge([
			'',
			get_tn_folder(),
			get_tn_folder(2),
			get_tn_folder(3),
			get_big_folder(),
			get_orig_folder(),
			getFilesFolder(),
		], $this->customFileFolders);

		// own pics
		$picsFolder = $this->getPicsFolder();

		foreach ($fileFields as $field)
		{
			if ($this->has($field))
			{
				foreach ($subFolders as $subFolder)
				{
					$killFiles[] = $picsFolder . $subFolder . $this->get($field);
				}
			}
		}

		return $killFiles;
	}

	protected function getFileSystemBasePath($endingSlashNeeded = true, $field = null)
	{
		return \diPaths::fileSystem($this, $endingSlashNeeded, $field);
	}

	public function killRelatedFiles($field = null)
	{
		if (!$this->exists())
		{
			return $this;
		}

		$killFiles = $this->getRelatedFilesList($field);
		$basePath = $this->getFileSystemBasePath(true, $field);

		// killing time
		foreach ($killFiles as $fn)
		{
			if ($fn && is_file($basePath . $fn))
			{
				unlink($basePath . $fn);
			}
		}

		if ($field === null && $this->getTable() != 'dipics')
		{
			\diCore\Entity\DynamicPic\Collection::createByTarget($this->getTable(), $this->getId())
				->hardDestroy();
		}

		return $this;
	}

	public function resetFieldsOfRelatedFiles($field = null)
	{
		$fileFields = $field
			? [$field]
			: $this->getFileFields();

		$fieldSuffixes = [
			'',
			'_tn',
			'_tn2',
			'_tn3',
		];

		foreach ($fileFields as $field)
		{
			if ($this->exists($field))
			{
				$this->set($field, '');
			}

			foreach ($fieldSuffixes as $suffix)
			{
				if ($this->exists($field . $suffix . '_w') && $this->exists($field . $suffix . '_h'))
				{
					$this
						->set($field . $suffix . '_w', 0)
						->set($field . $suffix . '_h', 0);
				}
			}
		}

		return $this;
	}

	public function generateFileName($field, $origFilename, $options = [])
	{
		$options = extend([
			'force' => false,
			'length' => 10,
			'checkMode' => 'db', // db/fs
		], $options);

		$ext = get_file_ext($origFilename);

		if ($options['force'] || !$this->has($field))
		{
			do {
				$this->set($field, get_unique_id($options['length']) . '.' . $ext);

				$exists = $options['checkMode'] == 'db'
					? \diCollection::create(static::type)->filterBy($field, $this->get($field))->count() > 0
					: is_file(\diPaths::fileSystem($this) . $this->getPicsFolder() . $this->get($field));
			} while ($exists);
		}

		return $this;
	}

	/**
	 * @return diModel
	 */
	private function startTransaction()
	{
		$this->getDb()->startTransaction();

		return $this;
	}

	/**
	 * @return diModel
	 */
	private function commitTransaction()
	{
		$this->getDb()->commitTransaction();

		return $this;
	}

	/**
	 * @return diModel
	 */
	private function rollbackTransaction()
	{
		$this->getDb()->rollbackTransaction();

		return $this;
	}

	protected function checkId()
	{
		if (isset($this->ar[static::getIdFieldName()]))
		{
			$this->id = $this->ar[static::getIdFieldName()];

			$this->kill(static::getIdFieldName());
		}

		return $this;
	}

	/**
	 * Returns query conditions array for order_num calculating
	 *
	 * @return array
	 */
	public function getQueryArForMove()
	{
		$ar = [];

		if ($this->exists('parent'))
		{
			$ar[] = "parent = '{$this->get('parent')}'";
		}

		return $ar;
	}

	/**
	 * @param integer $direction    Should be 1 or -1
	 * @return $this
	 */
	public function calculateAndSetOrderNum($direction)
	{
		$init_value = $direction > 0 ? 1 : 65000;
		$sign = $direction > 0 ? 1 : -1;
		$min_max = $direction > 0 ? 'MAX' : 'MIN';

		$qAr = $this->getQueryArForMove();
		$query = $qAr ? 'WHERE ' . join(' AND ', $qAr) : '';
		$field = static::order_field_name ?: $this->orderFieldName;

		$order_r = $this->getDb()->r($this->getTable(), $query,
			"{$min_max}({$field}) AS num,COUNT(id) AS cc");
		$this->set($field, $order_r && $order_r->cc ? intval($order_r->num) + $sign : $init_value);

		return $this;
	}

	/**
	 * Implementation of ArrayAccess::offsetSet()
	 *
	 * @link http://www.php.net/manual/en/arrayaccess.offsetset.php
	 * @param string $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
		if ($offset == $this->getIdFieldName())
		{
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
	public function offsetExists($offset)
	{
		if ($offset == $this->getIdFieldName())
		{
			return !!$this->getId();
		}

		return $this->exists($offset) || !!$this->getExtendedTemplateVar($offset);
	}

	/**
	 * Implementation of ArrayAccess::offsetUnset()
	 *
	 * @link http://www.php.net/manual/en/arrayaccess.offsetunset.php
	 * @param string $offset
	 */
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
	public function offsetGet($offset)
	{
		if ($this->has($offset))
		{
			return $this->get($offset);
		}

		if ($this->has(underscore($offset)))
		{
			return $this->get(underscore($offset));
		}

		if ($this->has(camelize($offset)))
		{
			return $this->get(camelize($offset));
		}

		return $this->getExtendedTemplateVar($offset);
	}

	public function asPhp($excludeFields = [])
	{
		$s = '\\diModel::create(\\diTypes::' . \diTypes::getName(static::type) . ', ';
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
		if (strpos($value, "\n") !== false)
		{
			$value = "<<<'EOF'\n" . $value . "\nEOF\n";
		}
		else
		{
			$value = "'" . str_replace("'", "\\'", str_replace('\\', '\\\\', $value)) . "'";
		}

		return $value;
	}

	public function asPhpArray($excludeFields = [])
	{
		$s = '';

		foreach ($this->get() as $field => $value)
		{
			if (in_array($field, $excludeFields))
			{
				continue;
			}

			$value = static::escapeValueForFile($value);

			$s .= "'$field'=>$value,\n";
		}

		return "[\n" . $s . "]";
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

	public function getAppearanceFeedForAdmin()
	{
		return [
			$this->get('title'),
			$this->get('name'),
		];
	}

	public function getStringAppearanceForAdmin()
	{
		return join(', ', array_filter($this->getAppearanceFeedForAdmin()));
	}

	public function appearanceForAdmin()
	{
		$linkWord = \diCore\Admin\Form::L('link', $this->__getLanguage());

		return $this->exists()
			? $this->getStringAppearanceForAdmin() . sprintf(
				' [<a href="%s" target="_blank">%s</a>]', $this->getAdminHref(), $linkWord)
			: '---';
	}

	public function __toString()
	{
		$name = static::type
			? \diTypes::getName(static::type)
			: 'undefined';

		return '[Model:' . $name . '#' . $this->getId() . ']';
	}
}