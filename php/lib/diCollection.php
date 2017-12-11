<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.07.2015
 * Time: 14:25
 */

use diCore\Helper\FileSystemHelper;

abstract class diCollection implements \Iterator,\Countable,\ArrayAccess
{
	// this should be redefined
	const type = null;
	const connection_name = null;
	protected $table;
	protected $modelType;
	protected $isIdUnique = true;

	const MAIN_TABLE_ALIAS = 'main_table';
	protected $alias = self::MAIN_TABLE_ALIAS;

	// cache
	const CACHE_FOLDER = '_cfg/cache/';
	const CACHE_FILE_EXTENSION = '.php';
	const cacheDirChmod = 0777;
	const cacheFileChmod = 0777;

	const CACHE_ALL = 0;

	protected static $commonCacheFileNames = [
		self::CACHE_ALL => null, // null for auto-generation
	];
	protected static $cacheFileNames = []; // overridden in child classes

	protected static $commonCacheNames = [
		self::CACHE_ALL => 'all',
	];
	protected static $cacheNames = []; // overridden in child classes

	/**
	 * Current iterator position
	 *
	 * @var int
	 */
	protected $position = 0;

	/**
	 * Models of the collection
	 *
	 * @var array
	 */
	protected $items = [];

	/**
	 * Total count of records in current collection
	 *
	 * @var int
	 */
	protected $count = null;

	/**
	 * Total count of records in database
	 *
	 * @var int
	 */
	protected $realCount = null;

	/**
	 * Indicates if all files were loaded from server
	 *
	 * @var bool
	 */
	protected $loaded = false;

	/**
	 * Query for database. This is temporary until query builder is installed
	 *
	 * @var string|null
	 */
	protected $query = null;

	/**
	 * Fields of table to be fetched. This is temporary until query builder is installed
	 *
	 * @var string|null
	 */
	protected $queryFields = null;

	/**
	 * Cached records rows, which were assigned to collection on creation
	 * This is temporary until query builder is installed
	 *
	 * @var resource|null
	 */
	protected $cachedRecords = null;

	/**
	 * Size of records on page
	 *
	 * @var int|null
	 */
	protected $pageSize = null;

	/**
	 * Number of current page
	 *
	 * @var int
	 */
	protected $pageNumber = 1;

	/**
	 * \diPagesNavy object, if initialized
	 *
	 * @var null|\diPagesNavy
	 */
	protected $PN = null;

	/**
	 * How many records to skip. If set, the $pageNumber is not used
	 *
	 * @var int
	 */
	protected $skip = null;

	/**
	 * Size of records per request. If null, $pageSize used (There could be several requests per page)
	 *
	 * @var int|null
	 */
	protected $requestSize = null;

	/**
	 * Number of request
	 *
	 * @var int|null
	 */
	protected $requestNumber = null;

	/**
	 * Collection options, set on creation
	 *
	 * @var array
	 */
	protected $options = [
		"modelAfterCreate" => null, // called after collection loaded from database, callback for each model
									// function(diModel $m) {}
	];

	/**
	 * Parts of SQL query
	 *
	 * @var array
	 */
	private $sqlParts = [
		'select' => [],
		'from' => [],
		'join' => [],
		'set' => [],
		'where' => [],
		'groupBy' => [],
		'having' => [],
		'orderBy' => [],
		'values' => [],
	];

	private $possibleDirections = ["ASC", "DESC"];

	public function __construct($table = null)
	{
		if ($table !== null && empty($this->table))
		{
			$this->table = $table;
		}
	}

	/**
	 * @param $type
	 * @param string $return
	 * @return bool|string
	 * @throws Exception
	 */
	public static function existsFor($type, $return = "class")
	{
		if (isInteger($type))
		{
			$type = \diTypes::getName($type);
		}

		$className = \diLib::getClassNameFor($type, \diLib::COLLECTION);

		if (!\diLib::exists($className))
		{
			return false;
		}

		return $return == "class" ? $className : $type;
	}

	/**
	 * @param integer|string $type
	 * @param array $options
	 * @param string|null $queryFields
	 * @return diCollection
	 * @throws \Exception
	 */
	public static function create($type, $options = [], $queryFields = null)
	{
		if (\diDB::is_rs($options))
		{
			$options = [
				"cachedRecords" => $options,
			];
		}
		elseif (is_scalar($options))
		{
			$options = [
				"query" => $options,
			];
		}

		$options = extend([
			"query" => null,
			"queryFields" => $queryFields,
			"cachedRecords" => null,
		], $options);

		$className = self::existsFor($type);

		if (!$className)
		{
			throw new \Exception("Collection class doesn't exist: " . ($className ?: $type));
		}

		/** @var diCollection $o */
		$o = new $className();

		if ($options["query"])
		{
			$o->setQuery($options["query"]);
			unset($options["query"]);
		}

		if ($options["queryFields"])
		{
			$o->setQueryFields($options["queryFields"]);
			unset($options["queryFields"]);
		}

		if ($options["cachedRecords"])
		{
			$o->setCachedRecords($options["cachedRecords"]);
			unset($options["cachedRecords"]);
		}

		$o->setOptions($options);

		return $o;
	}

	public static function createForTable($table, $options = [])
	{
		return static::create(\diTypes::getNameByTable($table), $options);
	}

	/**
	 * @param $table
	 * @param array $options
	 * @return diCollection
	 * @throws Exception
	 */
	public static function createForTableNoStrict($table, $options = [])
	{
		$type = \diTypes::getNameByTable($table);
		$typeName = self::existsFor($type, "type");

		if ($typeName)
		{
			return static::create($typeName, $options);
		}

		$c = new static($table);

		if (isset($options["query"]))
		{
			$c->setQuery($options["query"]);
			unset($options["query"]);
		}

		$c->setOptions($options);

		return $c;
	}

	/**
	 * @param integer|string $type
	 * @return diCollection
	 * @throws \Exception
	 */
	public static function createFromCache($type, $options = [], $cacheKind = self::CACHE_ALL)
	{
		$className = self::existsFor($type);

		if (!$className)
		{
			throw new \Exception("Collection class doesn't exist: " . ($className ?: $type));
		}

		$forceRebuild = !empty($options['forceRebuild']);
		unset($options['forceRebuild']);

		/** @var diCollection $o */
		$o = new $className();

		$o
			->setOptions($options)
			->loadCache($cacheKind, $forceRebuild);

		return $o;
	}

	/**
	 * @return bool
	 */
	public function hasUniqueId()
	{
		return $this->isIdUnique;
	}

	/**
	 * @param string|callable $callback Callback or field name of model
	 * @return array
	 */
	public function map($callback)
	{
		$this->load();

		$ar = [];

		$obj = new ArrayObject($this->items);
		$it = $obj->getIterator();

		//foreach ($this as $k => $v)
		while ($it->valid())
		{
			$k = $it->key();
			$v = $it->current();

			if (is_callable($callback))
			{
				$ar[] = $callback($v, $k);
			}
			else
			{
				$ar[] = $v[$callback];
			}

			$it->next();
		}

		return $ar;
	}

	/**
	 * Setting current alias for next query settings
	 * @param $alias
	 * @return $this
	 */
	public function setAlias($alias)
	{
		$this->alias = $alias;

		return $this;
	}

	/**
	 * Resetting current alias for next query settings
	 * @return $this
	 */
	public function resetAlias()
	{
		$this->alias = static::MAIN_TABLE_ALIAS;

		return $this;
	}

	public function addAliasToField($field, $alias = null)
	{
		if ($alias === true)
		{
			$alias = static::MAIN_TABLE_ALIAS;
		}
		elseif ($alias === null)
		{
			$alias = $this->alias;
		}

		if ($alias)
		{
			$alias = $alias . '.';
		}

		return $alias . $field;
	}

	public function addAliasToTable($table, $alias = null)
	{
		if ($alias === true)
		{
			$alias = static::MAIN_TABLE_ALIAS;
		}
		elseif ($alias === null)
		{
			$alias = $this->alias;
		}

		if ($alias)
		{
			$alias = ' ' . $this->getDb()->escapeTable($alias);
		}

		return $this->getDb()->escapeTable($table) . $alias;
	}

	protected function detectMethod($fullMethod)
	{
		$possibleMethods = [
			"filter_by_localized",
			"filter_by_expression",
			"filter_by",
			"order_by_localized",
			"order_by",
			"select_localized",
			"select",
		];

		foreach ($possibleMethods as $method)
		{
			if (substr($fullMethod, 0, strlen($method) + 1) == $method . "_")
			{
				return [$method, substr($fullMethod, strlen($method) + 1)];
			}
		}

		return [$fullMethod, null];
	}

	public function __call($method, $arguments)
	{
		$fullMethod = underscore($method);
		$value = isset($arguments[0]) ? $arguments[0] : null;
		$operator = isset($arguments[1]) ? $arguments[1] : null;

		list($method, $field) = $this->detectMethod($fullMethod);

		switch ($method)
		{
			case "filter_by":
				return $operator !== null
					? $this->filterBy($field, $operator, $value)
					: $this->filterBy($field, $value);

			case "filter_by_localized":
				return $operator !== null
					? $this->filterByLocalized($field, $operator, $value)
					: $this->filterByLocalized($field, $value);

			case "filter_by_expression":
				return $operator !== null
					? $this->filterByExpression($field, $operator, $value)
					: $this->filterByExpression($field, $value);

			case "order_by":
				return $this->orderBy($field, $value);

			case "order_by_localized":
				return $this->orderByLocalized($field, $value);

			case "select":
				return $this->select($field, true);

			case "select_localized":
				return $this->selectLocalized($field, true);
		}

		throw new \Exception(
			sprintf("Invalid method %s::%s(%s)", get_class($this), $method, print_r($arguments, 1))
		);
	}

	/**
	 * Set options for collection
	 *
	 * @param array $options
	 * @return $this
	 */
	public function setOptions($options = [])
	{
		$this->options = extend($this->options, $options);

		return $this;
	}

	/**
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * Returns option by name
	 *
	 * @param string $name
	 * @return mixed|null
	 */
	public function getOption($name)
	{
		return isset($this->options[$name])
			? $this->options[$name]
			: null;
	}

	/**
	 * Get the first result of the query or empty model
	 *
	 * @return diModel
	 */
	public function getFirstItem()
	{
		if ($this->count())
		{
			$this
				->setPageSize(1)
				->setPageNumber(1)
				->rewind()
				->valid();

			return $this->current();
		}

		return $this->getNewEmptyItem();
	}

	public function getRandomItemsArray($count)
	{
		if (!$this->isLoaded())
		{
			$this
				->setPageSize($this->count())
				->setPageNumber(1)
				->loadChunk();
		}

		if ($count >= $this->count())
		{
			return $this->items;
		}

		$ar = [];
		$keys = array_keys($this->items);

		while (count($ar) < $count)
		{
			$index = mt_rand(0, count($keys) - 1);
			$ar[] = $this->items[$keys[$index]];

			array_splice($keys, $index, 1);
		}

		return $ar;
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
	 * @return diDB
	 */
	protected static function db()
	{
		global $db;

		return $db;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getModelType()
	{
		return static::type ?: $this->modelType;
	}

	/**
	 * @return int
	 */
	public function getPageSize()
	{
		return $this->pageSize;
	}

	/**
	 * @return int
	 */
	public function getPageNumber()
	{
		return $this->pageNumber;
	}

	/**
	 * @param integer $number   First page number is 1
	 * @return $this
	 */
	public function setPageNumber($number)
	{
		$this->pageNumber = $number;

		return $this;
	}

	public function populatePageNumber($param = \diPagesNavy::PAGE_PARAM)
	{
		return $this->setPageNumber(\diRequest::get($param, 1));
	}

	/**
	 * @param integer|null $size     Records per page, if null automatically gets read from configuration
	 * @return $this
	 */
	public function setPageSize($size = null)
	{
		if ($size === null)
		{
			$size = $this->getStandardPageSize();
		}

		$this->pageSize = $size;

		return $this;
	}

	protected function getStandardPageSize()
	{
		return \diConfiguration::get('per_page[' . $this->getTable() . ']');
	}

	public function initPagesNavy()
	{
		$this
			->setPageSize()
			->populatePageNumber();

		$this->PN = new \diPagesNavy($this);

		return $this;
	}

	public function getPN()
	{
		return $this->PN;
	}

	/**
	 * @param integer $number   How many records to skip
	 * @return $this
	 */
	public function setSkip($number)
	{
		$this->skip = $number;

		return $this;
	}

	/**
	 * @param integer $requestSize Records per request
	 * @return $this
	 */
	public function setRequestSize($requestSize)
	{
		$this->requestSize = $requestSize;

		return $this;
	}

	public function addItem($item)
	{
		if (!$item instanceof diModel)
		{
			$item = $this->getNewItem($item);
		}

		if ($this->options["modelAfterCreate"] && is_callable($this->options["modelAfterCreate"]))
		{
			$this->options["modelAfterCreate"]($item);
		}

		$this->offsetSet($this->isIdUnique ? $this->getId($item) : null, $item);

		return $this;
	}

	/**
	 * @return \diModel
	 * @throws \Exception
	 */
	public function getNewEmptyItem()
	{
		return \diModel::create($this->getModelType());
	}

	public function getNewItem($data)
	{
		return \diModel::create($this->getModelType(), $data);
	}

	/**
	 * @param string|int $query
	 * @return $this
	 */
	public function setQuery($query)
	{
		$this->query = $query;

		return $this;
	}

	/**
	 * @param string $queryFields
	 * @deprecated Use ->select() instead
	 * @return $this
	 */
	public function setQueryFields($queryFields)
	{
		$this->queryFields = is_array($queryFields) ? join(",", $queryFields) : $queryFields;

		return $this;
	}

	/**
	 * @param $records
	 * @return $this
	 */
	public function setCachedRecords($records)
	{
		$this->cachedRecords = $records;

		return $this;
	}

	protected function getLimitQueryEnding()
	{
		$startFrom = $this->skip !== null
			? $this->skip
			: ($this->pageSize ? ($this->pageNumber - 1) * $this->pageSize : 0);
		$requestPageSize = $this->pageSize;

		if ($this->requestSize)
		{
			if ($this->requestNumber === null)
			{
				$this->requestNumber = 0;
			}

			$this->requestNumber++;

			$startFrom += ($this->requestNumber - 1) * $this->requestSize;
			$requestPageSize = $this->requestSize;
		}

		if ($requestPageSize)
		{
			return sprintf(
				"LIMIT %d,%d",
				$startFrom,
				$requestPageSize
			);
		}

		return null;
	}

	/**
	 * @return string
	 */
	protected function getQueryTable()
	{
		return $this->addAliasToTable($this->getTable());
	}

	/**
	 * @return string
	 */
	protected function getQueryFields()
	{
		return $this->queryFields ?: $this->getBuiltQueryFields() ?: $this->addAliasToField("*");
	}

	/**
	 * @return null|string
	 */
	protected function getQueryWhere()
	{
		return $this->query ?: $this->getBuiltQueryWhere();
	}

	/**
	 * @return null|string
	 */
	protected function getQueryOrderBy()
	{
		return $this->getBuiltQueryOrderBy();
	}

	/**
	 * @return null|string
	 */
	protected function getQueryGroupBy()
	{
		return $this->getBuiltQueryGroupBy();
	}

	/**
	 * Collection has any 'group by' conditions
	 *
	 * @return bool
	 */
	public function hasGroupBy()
	{
		return !!count($this->sqlParts['groupBy']);
	}

	/**
	 * @return null|string
	 */
	public function getFullQuery()
	{
		$ar = array_filter([
			$this->getQueryWhere(),
			$this->getQueryGroupBy(),
			$this->getQueryOrderBy(),
			$this->getLimitQueryEnding(),
		]);

		return join(" ", $ar);
	}

	public function rewind()
	{
		reset($this->items);

		return $this;
	}

	public function current()
	{
		return current($this->items) ?: $this->getNewEmptyItem();
	}

	public function key()
	{
		return key($this->items);
	}

	public function next()
	{
		next($this->items);

		return $this;
	}

	public function valid()
	{
		if (!$this->exists() && !$this->isLoaded())
		{
			$this->loadChunk();
		}

		return key($this->items) !== null;
	}

	public function load()
	{
		if ($this->isLoaded())
		{
			return $this;
		}

		$this->loadChunk();

		return $this;
	}

	/**
	 * Checks if element exists. Uses current position if no offset provided
	 *
	 * @param int $offset
	 * @return bool
	 */
	private function exists($offset = null)
	{
		return isset($this->items[$offset !== null ? $offset : $this->position]);
	}

	/**
	 * Checks if all elements are loaded into iterator
	 *
	 * @return bool
	 */
	private function isLoaded()
	{
		return $this->loaded || ($this->pageSize && count($this->items) >= $this->pageSize);
	}

	/**
	 * Override this if joins or something like that needed
	 * todo: make common mechanism for both model and collection of one type
	 */
	protected function getDbRecords()
	{
		return $this->getDb()->rs(
			$this->getQueryTable(),
			$this->getFullQuery(),
			$this->getQueryFields()
		);
	}

	/**
	 * Loads rows chunk from database
	 */
	private function loadChunk()
	{
		if ($this->cachedRecords)
		{
			$rs = $this->cachedRecords;
		}
		else
		{
			$rs = $this->getDbRecords();
		}

		while ($r = $this->getDb()->fetch($rs))
		{
			/** @var diModel $item */
			$item = $this->getNewEmptyItem();
			$item->initFrom($r);

			$this->addItem($item);
		}

		if ($this->cachedRecords)
		{
			$this->count = count($this->items);

			unset($this->cachedRecords);
		}

		if (count($this->items) == $this->count())
		{
			$this->loaded = true;
		}

		return $this;
	}

	public function getRealCount()
	{
		if ($this->realCount === null)
		{
			$this->count();
		}

		return $this->realCount;
	}

	public function count()
	{
		if ($this->count === null)
		{
			if ($this->hasGroupBy())
			{
				$q = $this->getDb()->getQueryForRs(
					$this->getQueryTable(),
					$this->getQueryWhere() . ' ' . $this->getQueryGroupBy(),
					"COUNT(*)"
				);

				$r = $this->getDb()->r(
					'(' . $q . ') counterfeit',
					'',
					"COUNT(*) AS cc"
				);
			}
			else
			{
				$r = $this->getDb()->r(
					$this->getQueryTable(),
					$this->getQueryWhere(),
					"COUNT(*) AS cc"
				);
			}

			$this->realCount = $this->count = $r ? $r->cc : 0;
		}

		if ($this->pageSize && $this->count > $this->pageSize)
		{
			$this->count = $this->pageSize;
		}

		return $this->count;
	}

	public function offsetExists($offset)
	{
		while (!$this->exists($offset) && !$this->isLoaded())
		{
			$this->loadChunk();
		}

		return $this->exists($offset);
	}

	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->items[$offset] : null;
	}

	public function offsetSet($offset, $value)
	{
		if (is_null($offset))
		{
			$this->items[] = $value;
		}
		else
		{
			$this->items[$offset] = $value;
		}

		return $this;
	}

	public function offsetUnset($offset)
	{
		unset($this->items[$offset]);

		return $this;
	}

	/**
	 * Walk through the collection and run model method or external callback
	 * with optional arguments
	 *
	 * @param $callback
	 * @param array $arguments
	 * @return array
	 */
	public function walk($callback, array $arguments = [])
	{
		$results = [];
		$useItemCallback = is_string($callback) && strpos($callback, "::") === false;

		foreach ($this as $id => $item)
		{
			if ($useItemCallback)
			{
				$cb = [$item, $callback];
			}
			else
			{
				$cb = $callback;
				array_unshift($arguments, $item);
			}

			$results[$id] = call_user_func_array($cb, $arguments);
		}

		return $results;
	}

	/**
	 * Returns ID of model
	 *
	 * @param \diModel $model
	 *
	 * @return int|null
	 */
	protected function getId(\diModel $model)
	{
		return $model->getId();
	}

	/**
	 * Returns ids of all non-empty records in collection
	 *
	 * @return array
	 */
	public function getIds()
	{
		return array_filter($this->walk(function(\diModel $m) {
			return $this->getId($m);
		}));
	}

	/**
	 * Removes collection data from memory
	 *
	 * @return $this
	 */
	public function destroy()
	{
		$this->items = [];
		$this->count = null;

		return $this;
	}

	/**
	 * Removes collection data, database records and all related files and data
	 *
	 * @return $this
	 */
	public function hardDestroy()
	{
		/** @var \diModel $model */
		foreach ($this as $model)
		{
			$model->killRelatedFilesAndData();
		}

		$ids = $this->getIds();

		if (count($ids))
		{
			$this->getDb()->delete($this->getTable(), $ids);
		}

		$this->destroy();

		return $this;
	}

	public function update($newData = [])
	{
		if ($newData)
		{
			$ids = $this->getIds();

			if (count($ids))
			{
				$this->getDb()->update($this->getQueryTable(), $newData, $ids);

				/** @var \diModel $m */
				foreach ($this as $m)
				{
					$m->set($newData);
				}
			}
		}

		return $this;
	}

	protected function getIdFieldName()
	{
		return \diModel::create($this->modelType)->getIdFieldName();
	}

	/**
	 * Simple query builder functions
	 */

	public function find($o)
	{
		if (is_scalar($o))
		{
			return $this->filterBy($this->getIdFieldName(), $o);
		}

		return $this;
	}

	/**
	 * Returns first found model
	 *
	 * @param $o
	 *
	 * @return \diModel
	 */
	public function findOne($o)
	{
		return $this->find($o)->getFirstItem();
	}

	/**
	 * Returns model by id
	 *
	 * @param integer $id
	 *
	 * @return \diModel
	 */
	public function getById($id)
	{
		if (!$this->isIdUnique)
		{
			throw new \diRuntimeException(self::class . ' has no unique ID');
		}

		return $this->offsetGet($id) ?: $this->getNewEmptyItem();
	}

	/**
	 * @param $field
	 * @param $operator
	 * @param $value
	 * @param array $options
	 * @return $this
	 */
	protected function extFilterBy($field, $operator, $value, $options = [])
	{
		$field = $this->addAliasToField($field);

		$this->sqlParts['where'][] = compact('field', 'operator', 'value', 'options');

		return $this;
	}

	/**
	 * @param $field
	 * @param bool $append
	 * @param array $options
	 * @return $this
	 */
	protected function extSelect($field, $append = false, $options = [])
	{
		$options = extend([
			'addAlias' => true,
			'raw' => false,
		], $options);

		if ($options['addAlias'])
		{
			$field = $this->addAliasToField($field);
		}

		if (!$append)
		{
			$this->resetSelect();
		}

		$this->sqlParts['select'][] = compact('field', 'options');

		return $this;
	}

	public function filterBy($field, $operator, $value = null)
	{
		if (func_num_args() == 2)
		{
			$value = $operator;
			$operator = is_array($value) ? 'in' : '=';
		}

		return $this->extFilterBy($field, $operator, $value, [
			'rawValue' => false,
		]);
	}

	public function filterByLocalized($field, $operator, $value = null)
	{
		$field = \diModel::getLocalizedFieldName($field);

		if (func_num_args() == 2)
		{
			return $this->filterBy($field, $operator);
		}
		else
		{
			return $this->filterBy($field, $operator, $value);
		}
	}

	public function filterByExpression($field, $operator, $value = null)
	{
		if (func_num_args() == 2)
		{
			$value = $operator;
			$operator = is_array($value) ? 'in' : '=';
		}

		return $this->extFilterBy($field, $operator, $value, [
			'rawValue' => true,
		]);
	}

	public function startsWith($field, $value)
	{
		return $this->filterManual("INSTR(" . $field . ", '" . $value . "') = 1");
		/* for non-MySql
		return $this->extFilterBy($field, 'LIKE', $value . '%', [
			'rawValue' => true,
		]);
		*/
	}

	public function contains($field, $value)
	{
		return $this->filterManual("INSTR(" . $field . ", '" . $value . "') > 0");
		/* for non-MySql
		return $this->extFilterBy($field, 'LIKE', '%' . $value . '%', [
			'rawValue' => true,
		]);
		*/
	}

	/**
	 * Adding manual expression to query
	 * @param $expression
	 * @return $this
	 */
	public function filterManual($expression)
	{
		$this->sqlParts['where'][] = [
			'field' => null,
			'value' => null,
			'expression' => '(' . $expression . ')',
			'options' => [
				'manual' => true,
			],
		];

		return $this;
	}

	public function orderBy($field, $direction = null)
	{
		$direction = strtoupper($direction ?: "ASC");

		if (!in_array($direction, $this->possibleDirections))
		{
			throw new Exception("Unknown direction '{$direction}'");
		}

		$field = $this->addAliasToField($field);

		$options = [
			'rawValue' => false,
		];

		$this->sqlParts['orderBy'][] = compact('field', 'direction', 'options');

		return $this;
	}

	public function orderByLocalized($field, $direction = null)
	{
		return $this->orderBy(\diModel::getLocalizedFieldName($field), $direction);
	}

	public function orderByExpression($field, $direction = null)
	{
		$direction = strtoupper($direction ?: "ASC");

		if (!in_array($direction, $this->possibleDirections))
		{
			throw new Exception("Unknown direction '{$direction}'");
		}

		$options = [
			'rawValue' => true,
		];

		$this->sqlParts['orderBy'][] = compact('field', 'direction', 'options');

		return $this;
	}

	public function randomOrder()
	{
		$this
			->orderByExpression('RAND()');

		return $this;
	}

	/**
	 * @param array|string $fields
	 * @return $this
	 */
	public function groupBy($fields)
	{
		if (!is_array($fields))
		{
			$fields = [$fields];
		}

		foreach ($fields as $field)
		{
			$field = $this->addAliasToField($field);

			$this->sqlParts['groupBy'][] = compact('field');
		}

		return $this;
	}

	/**
	 * @param array|string $fields
	 * @param bool $append
	 * @param bool $raw
	 *
	 * @return $this
	 */
	public function select($fields, $append = false)
	{
		if (!is_array($fields))
		{
			$fields = [$fields];
		}

		if (!$append)
		{
			$this->resetSelect();
		}

		foreach ($fields as $field)
		{
			$this->extSelect($field, true);
		}

		return $this;
	}

	/**
	 * @param array|string $fields
	 * @param bool $append
	 * @param bool $raw
	 *
	 * @return $this
	 */
	public function selectLocalized($fields, $append = false)
	{
		if (!is_array($fields))
		{
			$fields = [$fields];
		}

		foreach ($fields as &$field)
		{
			$field = \diModel::getLocalizedFieldName($field);
		}

		return $this->select($fields, $append);
	}

	public function selectExpression($field, $append = false)
	{
		$this->extSelect($field, $append, [
			'addAlias' => false,
			'raw' => true,
		]);

		return $this;
	}

	public function resetSelect()
	{
		$this->sqlParts['select'] = [];

		return $this;
	}

	protected function getBuiltQueryWhere()
	{
		if ($this->sqlParts['where'])
		{
			return 'WHERE ' . join(' AND ', array_filter(array_map(function($val) {
				$value = $val['value'];

				if (!empty($val['options']['manual']))
				{
					return $val['expression'];
				}
				elseif (!empty($val['options']['rawValue']))
				{
					if (is_array($value))
					{
						$value = '(' . join(',', $value) . ')';
					}
				}
				else
				{
					if (is_array($value))
					{
						if ($val['operator'] == 'between')
						{
							$value = join(' AND ', array_map(function($v) {
								return $this->getDb()->escapeValue($v);
							}, $value));
						}
						else
						{
							$value = count($value)
								? '(' . join(',', array_map(function($v) {
							            return $this->getDb()->escapeValue($v);
						            }, $value)) . ')'
								: null;
						}
					}
					else
					{
						$value = $this->getDb()->escapeValue($val['value']);
					}
				}

				if (is_array($val['value']))
				{
					if (count($val['value']))
					{
						switch ($val['operator'])
						{
							case '=':
								$val['operator'] = 'in';
								break;

							case '!=':
								$val['operator'] = 'not in';
								break;
						}
					}
					else
					{
						switch (trim(strtolower($val['operator'])))
						{
							case '=':
							case 'in':
								return '1 = 0';

							case '!=':
							case 'not in':
								return null;
						}
					}
				}
				elseif (is_null($val['value']))
				{
					switch ($val['operator'])
					{
						case '=':
							$val['operator'] = 'IS';
							$value = 'NULL';
							break;

						case '!=':
							$val['operator'] = 'IS NOT';
							$value = 'NULL';
							break;
					}
				}

				return
					$this->getDb()->escapeField($val['field']) .
					' ' . $val['operator'] . ' ' .
					$value;
			}, $this->sqlParts['where'])));
		}

		return null;
	}

	protected function getBuiltQueryOrderBy()
	{
		if ($this->sqlParts['orderBy'])
		{
			return 'ORDER BY ' . join(',', array_map(function($val) {
				$field = empty($val['options']['rawValue'])
					? $this->getDb()->escapeField($val['field'])
					: $val['field'];
				return $field . ' ' . $val['direction'];
			}, $this->sqlParts['orderBy']));
		}

		return null;
	}

	protected function getBuiltQueryGroupBy()
	{
		if ($this->sqlParts['groupBy'])
		{
			return 'GROUP BY ' . join(',', array_map(function($val) {
				return $this->getDb()->escapeField($val['field']);
			}, $this->sqlParts['groupBy']));
		}

		return null;
	}

	protected function getBuiltQueryFields()
	{
		if ($this->sqlParts['select'])
		{
			return join(',', array_map(function($opt) {
				if (is_scalar($opt['field']))
				{
					return !empty($opt['options']['raw'])
						? $opt['field']
						: $this->getDb()->escapeField($opt['field']);
				}

				throw new \Exception('Not implemented yet');
			}, $this->sqlParts['select']));
		}

		return null;
	}

	/**
	 * Cache methods
	 */

	protected function getCacheContents($cacheKind = self::CACHE_ALL)
	{
		$s = "<?php\n";

		/** @var diModel $model */
		foreach ($this as $model)
		{
			$s .= "\$this->addItem(" . $model->asPhp() . ");\n";
		}

		return $s;
	}

	protected function getBaseCacheSubFolder($cacheKind = self::CACHE_ALL)
	{
		return \diTypes::getName(\diTypes::getId($this->getModelType()));
	}

	protected function getCacheSubFolder($cacheKind = self::CACHE_ALL)
	{
		$subFolder = '';

		if (
			empty(static::$cacheFileNames[$cacheKind]) &&
			empty(static::$commonCacheFileNames[$cacheKind]) &&
			!empty(static::$cacheNames[$cacheKind])
		   )
		{
			$subFolder = $this->getBaseCacheSubFolder($cacheKind) . '/';
		}

		return $subFolder;
	}

	protected function getCachePath($cacheKind = self::CACHE_ALL)
	{
		return \diPaths::fileSystem() . static::CACHE_FOLDER;
	}

	protected function getCacheFilename($cacheKind = self::CACHE_ALL)
	{
		if ($cacheKind == self::CACHE_ALL)
		{
			$fn = $this->getTable();
		}
		elseif (!empty(static::$cacheFileNames[$cacheKind]))
		{
			$fn = static::$cacheFileNames[$cacheKind];
		}
		elseif (!empty(static::$commonCacheFileNames[$cacheKind]))
		{
			$fn = static::$commonCacheFileNames[$cacheKind];
		}
		elseif (!empty(static::$cacheNames[$cacheKind]))
		{
			$fn = static::$cacheNames[$cacheKind];
		}
		else
		{
			throw new \Exception('Undefined cache kind: ' . $cacheKind);
		}

		return $fn . static::CACHE_FILE_EXTENSION;
	}

	protected function getCachePathAndFilename($cacheKind = self::CACHE_ALL)
	{
		return $this->getCachePath($cacheKind) .
			$this->getCacheSubFolder($cacheKind) .
			$this->getCacheFilename($cacheKind);
	}

	public function buildCache($cacheKind = self::CACHE_ALL)
	{
		FileSystemHelper::createTree($this->getCachePath($cacheKind), $this->getCacheSubFolder($cacheKind),
			static::cacheDirChmod);

		file_put_contents($this->getCachePathAndFilename($cacheKind), $this->getCacheContents($cacheKind));
		chmod($this->getCachePathAndFilename($cacheKind), static::cacheFileChmod);

		return $this;
	}

	public function cacheExists($cacheKind = self::CACHE_ALL)
	{
		return is_file($this->getCachePathAndFilename($cacheKind));
	}

	public function loadCache($cacheKind = self::CACHE_ALL, $forceRebuild = false)
	{
		if ($forceRebuild)
		{
			$this->buildCache($cacheKind);
		}

		include $this->getCachePathAndFilename($cacheKind);

		$this->loaded = true;
		$this->count = count($this->items);

		return $this;
	}
}