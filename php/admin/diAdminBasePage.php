<?php

use diCore\Helper\ArrayHelper;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.06.2015
 * Time: 14:35
 */
abstract class diAdminBasePage
{
    /** @var diAdmin */
	private $X;

	/** @var diAdminList */
	private $List;

	/** @var diAdminGrid */
	private $Grid;

	/** @var diAdminFilters */
	private $Filters;

	/** @var diAdminForm */
	private $Form;

	/** @var diAdminSubmit */
	private $Submit;

	/** @var diPagesNavy */
	private $PagesNavy;

	/** @var string */
	protected $table;

	const basePath = null;

	/** @var integer */
	protected $id;

	/** @var integer */
	protected $originalId;

	/** @var diCollection */
	private $listCollection;

	const LIST_LIST = 1;
	const LIST_GRID = 2;
	/**
	 * How to render list: grid or list
	 *
	 * @var int
	 */
	protected $listMode = self::LIST_LIST;

	protected $methodCaptionsAr = [
		'ru' => [
			"list" => "Управление",
			"add" => "Добавление",
			"edit" => "Редактирование",
		],
		'en' => [
			"list" => "Manage",
			"add" => "Add",
			"edit" => "Edit",
		],
	];

	/*
	 * override this in child classed
	 * possible keys:
	 *      updateSearchIndexOnSubmit
	 *      staticMode
	 *      showControlPanel
	 *      showHeader
	 *      filters
	 *      formBasePath
	 */
	/** @var array */
	protected $options = [
	];

	/** @var array */
	protected $customOptions = [
	];

	public static $listOptions = [
		"showControlPanel",
		"showHeader",
		"formBasePath",
	];

	public function __construct(diAdminBase $X)
	{
		$this->X = $X;

		$this->collectId();
	}

	private function collectId()
	{
		$this->originalId = $this->id = $this->getAdmin()->getId();

		return $this;
	}

	/**
	 * @param diAdminBase $X
	 * @return static
	 * @throws Exception
	 */
	public static function create(diAdminBase $X)
	{
		/** @var diAdminBasePage $o */
		$o = new static($X);

		$o->tryToInitTable();

		$m = diAdminBase::getClassMethodName($o->getMethod());
		$beforeM = diAdminBase::getClassMethodName($o->getMethod(), "before");
		$afterM = diAdminBase::getClassMethodName($o->getMethod(), "after");

		if (!method_exists($o, $m))
		{
			throw new Exception("Class " . get_class($o) . " doesn't have '$m' method");
		}

		if (!method_exists($o, $beforeM))
		{
			throw new Exception("Class " . get_class($o) . " doesn't have '$beforeM' method");
		}

		if (!method_exists($o, $afterM))
		{
			throw new Exception("Class " . get_class($o) . " doesn't have '$afterM' method");
		}

		if ($o->$beforeM())
		{
			$o->$m();
		}

		$o->$afterM();

		if ($o->getTwig()->has(\diTwig::TOKEN_FOR_PAGE))
		{
			$o->getTpl()
				->assign([
					"PAGE" => $o->getTwig()->getPage(),
				]);
		}
		elseif ($o->getTpl()->defined("page"))
		{
			$o->getTpl()->process("page");
		}

		return $o;
	}

	abstract public function renderList();
	/*
	abstract public function renderForm();
	abstract public function submitForm();
	*/

	public function getLanguage()
	{
		return $this->getAdmin()->getLanguage();
	}

	protected function localized($ar)
	{
		return $ar[$this->getLanguage()];
	}

	protected function printList()
	{
		switch ($this->listMode)
		{
			case self::LIST_LIST:
				$this->defaultPrintList();
				break;

			case self::LIST_GRID:
				$this->defaultPrintGrid();
				break;
		}

		return $this;
	}

	public function tryToInitTable()
	{
		if (method_exists($this, "initTable"))
		{
			$this->initTable();
		}

		return $this;
	}

	/**
	 * @return diAdmin
	 */
	public function getAdmin()
	{
		return $this->X;
	}

	public function getOption($name)
	{
		$x = $this->getOptions();

		for ($i = 0; $i < func_num_args(); $i++)
		{
			$key = func_get_arg($i);

			if (isset($x[$key]))
			{
				$x = $x[$key];
			}
			else
			{
				return null;
			}
		}

		return $x;
	}

	public function getOptions($keys = [])
	{
		$opt = extend($this->options, $this->customOptions);

		if (static::basePath)
		{
			$opt['formBasePath'] = static::basePath;
		}

		if (empty($keys))
		{
			return $opt;
		}

		return ArrayHelper::filterByKey($opt, $keys);
	}

	public function getDb()
	{
		return $this->X->getDb();
	}

	/**
	 * @return FastTemplate
	 */
	public function getTpl()
	{
		return $this->X->getTpl();
	}

	public function getTwig()
	{
		return $this->X->getTwig();
	}

	/**
	 * @return $this
	 * @throws Exception
	 */
	private function initPagesNavy()
	{
		if (
			(!$this->PagesNavy && diConfiguration::exists("admin_per_page[" . $this->getTable() . "]")) ||
			($this->PagesNavy && !$this->PagesNavy->getWhere() && $this->hasFilters() && $this->getFilters()->get_where())
		   )
		{
			$this->PagesNavy = new diPagesNavy(
				$this->getTable(),
				diConfiguration::get("admin_per_page[" . $this->getTable() . "]"),
				$this->hasFilters() ? $this->getFilters()->get_where() : ""
			);
		}

		return $this;
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function hasPagesNavy()
	{
		return !!$this->getPagesNavy(false);
	}

	/**
	 * @param bool $strict
	 * @return diPagesNavy
	 * @throws Exception
	 */
	public function getPagesNavy($strict = true)
	{
		$this->initPagesNavy();

		if (!$this->PagesNavy && $strict)
	    {
	    	throw new Exception("diPagesNavy not initialized");
	    }

		return $this->PagesNavy;
	}

	/**
	 * @return bool
	 */
	public function hasList()
	{
		return !!$this->List;
	}

	/**
	 * @return diAdminList
	 * @throws Exception
	 */
	public function getList()
	{
	    if (!$this->hasList())
	    {
	    	throw new Exception("diAdminList not initialized");
	    }

		return $this->List;
	}

	/**
	 * @return bool
	 */
	public function hasGrid()
	{
		return !!$this->Grid;
	}

	/**
	 * @return diAdminGrid
	 * @throws Exception
	 */
	public function getGrid()
	{
		if (!$this->hasGrid())
		{
			throw new Exception("diAdminGrid not initialized");
		}

		return $this->Grid;
	}

	/**
	 * @return bool
	 */
	public function hasForm()
	{
		return !!$this->Form;
	}

	/**
	 * @return diAdminForm
	 * @throws Exception
	 */
	public function getForm()
	{
	    if (!$this->Form)
	    {
	    	throw new Exception("diAdminForm not initialized");
		}

		return $this->Form;
	}

	/**
	 * @return bool
	 */
	public function hasFilters()
	{
		return !!$this->Filters;
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function filtersBlockNeeded()
	{
		return $this->hasFilters() && (!!$this->getOption("filters", "sortByAr") || !!$this->getFilters()->getFilters());
	}

	/**
	 * @return diAdminFilters
	 * @throws Exception
	 */
	public function getFilters()
	{
		if (!$this->hasFilters())
		{
			throw new Exception("diAdminFilters not initialized");
		}

		return $this->Filters;
	}

	/**
	 * @return diAdminSubmit
	 * @throws Exception
	 */
	public function getSubmit()
	{
	    if (!$this->Submit)
	    {
	    	throw new Exception("diAdminSubmit not initialized");
		}

		return $this->Submit;
	}

	/**
	 * @return bool
	 */
	public function hasTable()
	{
		return !!$this->table;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getTable()
	{
	    if (!$this->hasTable())
		{
			throw new Exception("Table undefined in " . get_class($this));
		}

		return $this->table;
	}

	public function getBasePath()
	{
		return static::basePath ?: $this->getTable();
	}

	public function getOriginalId()
	{
		return $this->originalId;
	}

	public function isNew()
	{
		return !$this->getOriginalId();
	}

	public function getId()
	{
		return $this->id;
	}

	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getModule()
	{
		return $this->X->getModule();
	}

	/**
	 * @return string
	 */
	public function getMethod()
	{
		return $this->X->getMethod();
	}

	/**
	 * @return string
	 */
	public function getRefinedMethod()
	{
		return $this->X->getRefinedMethod();
	}

	protected function setTable($table)
	{
		$this->table = $table;

		return $this;
	}

	protected function getFormWorkerUri()
	{
	    if (method_exists($this, "submitForm"))
	    {
	    	return diAdminBase::getPageUri($this->getBasePath(), "submit");
	    }

		return $this->getTable() . "/submit.php";
	}

	protected function getListQueryFilters()
	{
		return $this->hasFilters() ? $this->getFilters()->getQuery() : "";
	}

	protected function getListQueryLimit()
	{
		return $this->hasPagesNavy() ? $this->getPagesNavy()->getSqlLimit() : "";
	}

	protected function getListPageSize()
	{
		return $this->hasPagesNavy() ? $this->getPagesNavy()->getPerPage() : null;
	}

	protected function getListPageNumber()
	{
		return $this->hasPagesNavy() ? $this->getPagesNavy()->getPage() : null;
	}

	protected function extendListQueryOptions($options = [])
	{
		return extend([
			"query" => "",
			"limit" => "",
			'pageNumber' => null,
			'pageSize' => null,
			"sortBy" => "",
			"dir" => null,
		], $options);
	}

	protected function getDefaultListRows($options = [])
	{
		$options = $this->extendListQueryOptions($options);

		$this->listCollection = \diCollection::createForTable($this->getTable(), $options["query"]);

		if ($options["sortBy"])
		{
			$this->listCollection->orderBy($options["sortBy"], $options["dir"]);
		}

		if ($options['pageSize'])
		{
			$this->listCollection->setPageSize($options['pageSize']);
		}

		if ($options['pageNumber'])
		{
			$this->listCollection->setPageNumber($options['pageNumber']);
		}

		$this->cacheDataForList();

		return $this->listCollection;
	}

	/**
	 * Use $this->getListCollection() inside to get the current list collection
	 * @return $this
	 */
	protected function cacheDataForList()
	{
		return $this;
	}

	/**
	 * @return diCollection
	 */
	public function getListCollection()
	{
		return $this->listCollection;
	}

	protected function defaultPrintListRows($rs)
	{
		if ($rs instanceof diCollection)
		{
			foreach ($rs as $model)
			{
				$this->getList()->addRow($model);
			}
		}
		else
		{
			while ($r = $this->getDb()->fetch($rs))
			{
				$this->getList()->addRow($r);
			}
		}

		return $this;
	}

	protected function extendListOptions($options = [])
	{
		return extend([
			"query" => $this->getListQueryFilters(),
			"limit" => $this->getListQueryLimit(),
			'pageNumber' => $this->getListPageNumber(),
			'pageSize' => $this->getListPageSize(),
			"sortBy" => $this->hasFilters() ? $this->getFilters()->getSortBy() : "id",
			"dir" => $this->hasFilters() ? $this->getFilters()->getDir() : "DESC",
		], $options);
	}

	protected function defaultPrintList($options = [])
	{
		$this->defaultPrintListRows($this->getDefaultListRows($this->extendListOptions($options)));

		return $this;
	}

	protected function defaultPrintGrid($options = [])
	{
		$this->defaultPrintGridRows($this->getDefaultListRows($this->extendListOptions($options)));

		return $this;
	}

	protected function defaultPrintGridRows(diCollection $collection)
	{
		/** @var diModel $model */
		foreach ($collection as $model)
		{
			$this->getGrid()->printElement($model);
		}

		return $this;
	}

	/**
	 * Prefix added before IMG urls. If it is an external URL, this should return empty string
	 *
	 * @param diModel $model
	 * @return string
	 */
	public function getImgUrlPrefix(diModel $model)
	{
		return class_exists("diExternalFolders") &&
			$model->exists(diExternalFolders::FIELD_NAME) &&
			$model->get(diExternalFolders::FIELD_NAME) != diExternalFolders::MAIN
			? ""
			: "/";
	}

	protected function beforeRenderList()
	{
		$this->getTpl()
			->define("`_default/" . ($this->listMode == self::LIST_LIST ? "list" : "grid"), [
				"page",
			])
			->define("`_default/list", [
				"list_control_panel",
			])
			->define("`_default/grid", [
				"grid_row",
			])
			->assign([
				"FILTERS" => "",
				"NAVY" => "",
				"BEFORE_TABLE" => "",
				"AFTER_TABLE" => "",
				"TABLE_NAME" => $this->hasTable() ? $this->getTable() : "",
				"GRID_ROWS" => "",
			]);

		if ($this->hasTable())
	    {
		    switch ($this->listMode)
		    {
			    case self::LIST_LIST:
				    $listOptions = $this->getOptions(self::$listOptions);

				    $this->List = new diAdminList($this, $listOptions);
				    break;

			    case self::LIST_GRID:
					$this->Grid = new diAdminGrid($this);
				    break;
		    }

		    if ($filters = $this->getOption("filters"))
		    {
			    $this->Filters = new diAdminFilters($this);
			    $this->getFilters()->setSortableState(isset($filters["sortByAr"]));

			    if (isset($filters["defaultSorter"]))
			    {
				    $this->getFilters()->set_default_sorter($filters["defaultSorter"]);
			    }

			    if (isset($filters["buttonOptions"]))
			    {
				    $this->getFilters()->setButtonOptions($filters["buttonOptions"]);
			    }

			    $this->setupFilters();

			    if ($this->getFilters()->getSortableState())
			    {
					$this->getFilters()
						->setSelectFromArrayInput("sortby", $filters["sortByAr"])
						->setSelectFromArrayInput("dir", diAdminFilters::$dirAr);
			    }
		    }
	    }

		return true;
	}

	protected function setupFilters()
	{
		if ($this->filtersBlockNeeded())
		{
			$this->getFilters()
				->buildQuery();
		}
	}

	public function setBeforeTableTemplate($template, $data = [])
	{
		$this->getTwig()
			->render($template, 'before_table', $data);

		return $this;
	}

	public function setAfterTableTemplate($template, $data = [])
	{
		$this->getTwig()
			->render($template, 'after_table', $data);

		return $this;
	}

	public function setBeforeFormTemplate($template, $data = [])
	{
		$this->getTwig()
			->render($template, 'before_form', $data);

		return $this;
	}

	public function setAfterFormTemplate($template, $data = [])
	{
		$this->getTwig()
			->render($template, 'after_form', $data);

		return $this;
	}

	protected function afterRenderList()
	{
		if ($this->hasList() || $this->hasGrid())
		{
			if ($this->hasPagesNavy())
			{
				$this->getTpl()
					->assign([
						"PAGES_NAVY" => $this->getPagesNavy()->print_pages(diAdminBase::getPageUri($this->getModule())),
					])
					->parse("navy");
			}

			$this->printList();

			if ($this->hasList())
			{
				$this->getTpl()->assign([
					"TABLE" => $this->getList()->getHtml(),
				]);
			}
		}

		if ($this->filtersBlockNeeded())
		{
			$this->getTpl()->assign([
				"FILTERS" => $this->getFilters()->getBlockHtml() . $this->getFilters()->get_js_data(true),
			]);
		}

		if ($this->getTwig()->assigned('before_table'))
		{
			$this->getTpl()->assign('before_table', $this->getTwig()->getAssigned('before_table'));
		}
		elseif ($this->getTpl()->defined("before_table"))
		{
			$this->getTpl()->parse("before_table");
		}

		if ($this->getTwig()->assigned('after_table'))
		{
			$this->getTpl()->assign('after_table', $this->getTwig()->getAssigned('after_table'));
		}
		elseif ($this->getTpl()->defined("after_table"))
		{
			$this->getTpl()->parse("after_table");
		}
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	protected function beforeRenderForm()
	{
		$this->getTpl()
			->define("`_default/form", [
				"page",
			])
			->assign([
				"ACTION" => $this->getFormWorkerUri(),
				"TABLE" => $this->getTable(),
				"ID" => $this->getId(),
			], "ADMIN_FORM_")
			->assign([
				"BEFORE_FORM" => "",
				"AFTER_FORM" => "",
			]);

		$this->Form = new diAdminForm($this);
		$this->getForm()
			->setStaticMode($this->getOption("staticMode"))
			->read_data();

		return true;
	}

	protected function getFormSubmitButtonsBlock()
	{
		return $this->getForm()->getSubmitButtons();
	}

	protected function printEditLog()
	{
		if ($this->useEditLog() && $this->getId())
		{
			/** @var diAdminTableEditLogCollection $records */
			$records = diCollection::create(diTypes::admin_table_edit_log);
			$records
				->filterByTargetTable($this->getTable())
				->filterByTargetId($this->getId())
				->orderById('DESC');

			/** @var diAdminCollection $admins */
			$admins = diCollection::create(diTypes::admin);

			/** @var diAdminTableEditLogModel $rec */
			foreach ($records as $rec)
			{
				$rec->parseData();
			}

			$this->getForm()
				->setInput(diAdminTableEditLogModel::ADMIN_TAB_NAME,
					$this->getTwig()->parse('admin/admin_table_edit_log/form_field', [
						'records' => $records,
						'admins' => $admins,
					]));
		}

		return $this;
	}

	protected function afterRenderForm()
	{
		$this->printEditLog();

		$this->getTpl()->assign([
			"FORM" => $this->getForm()->get_html(),
			"SUBMIT_BLOCK" => $this->getFormSubmitButtonsBlock(),
		]);

		if ($this->getTwig()->assigned('before_form'))
		{
			$this->getTpl()->assign('before_form', $this->getTwig()->getAssigned('before_form'));
		}
		elseif ($this->getTpl()->defined("before_form"))
		{
			$this->getTpl()->parse("before_form");
		}

		if ($this->getTwig()->assigned('after_form'))
		{
			$this->getTpl()->assign('after_form', $this->getTwig()->getAssigned('after_form'));
		}
		elseif ($this->getTpl()->defined("after_form"))
		{
			$this->getTpl()->parse("after_form");
		}
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	protected function beforeSubmitForm()
	{
		$this->Submit = new diAdminSubmit($this);

		if ($this->getSubmit()->isSubmit() && !$this->getOption("staticMode"))
		{
			$this->getSubmit()->gatherData();

			return true;
		}

		return false;
	}

	protected function afterSubmitForm()
	{
		if ($this->getSubmit()->isSubmit())
		{
			$this->getSubmit()->storeData();
		}

		if ($this->getOption("updateSearchIndexOnSubmit"))
		{
			diSearch::makeRecordIndex($this->getTable(), $this->getId());
		}

		$this
			->addEditLogRecord()
			->redirectAfterSubmit();
	}

	protected function addEditLogRecord()
	{
		if ($this->useEditLog())
		{
			try
			{
				/** @var diAdminTableEditLogModel $log */
				$log = diModel::create(diTypes::admin_table_edit_log);

				$log->setRelated('formFields', $this->getFormFieldsFiltered());

				$log
					->setTargetTable($this->getTable())
					->setTargetId($this->getId())
					->setAdminId($this->getAdmin()->getAdminModel()->getId())
					->setBothData($this->getSubmit()->getSubmittedModel(), $this->getSubmit()->getCurModel())
					->save();
			}
			catch (Exception $e)
			{
				// validation failed -> no changes
				//throw $e;
			}
		}

		return $this;
	}

	protected function redirectTo($uri)
	{
		header("Location: $uri");

		return $this;
	}

	protected function getQueryParamsForRedirectAfterSubmit()
	{
		$ar = [];

		// calculating page
		$this->beforeRenderList();
		$this->renderList();

		$sortBy = $this->hasFilters() ? $this->getFilters()->getSortBy() : "title";
		$dir = $this->hasFilters() ? $this->getFilters()->getDir() : "ASC";

		$page = $this->hasPagesNavy()
			? $this->getPagesNavy()->get_page_of($this->getId(), $sortBy, $dir)
			: null;

		if ($page)
		{
			$ar["page"] = $page;
		}

		return $ar;
	}

	protected function useAnchorInRedirectAfterSubmitUrl()
	{
		return true;
	}

	protected function getRedirectAfterSubmitUrl()
	{
		$anchor = $this->useAnchorInRedirectAfterSubmitUrl()
			? '#' . \diNiceTable::getRowAnchorName($this->getId())
			: '';

		return diAdminBase::getPageUri(
			$this->getBasePath(),
			'list',
			$this->getQueryParamsForRedirectAfterSubmit()
		) . $anchor;
	}

	protected function redirectAfterSubmit()
	{
		$this->redirectTo($this->getRedirectAfterSubmitUrl());

		return $this;
	}

	public function doesFieldExist($field)
	{
		$ar = $this->getAllFields();

		return isset($ar[$field]);
	}

	public function getFieldProperty($field, $property = null)
	{
		$ar = $this->getAllFields();

		if (!isset($ar[$field]))
		{
			throw new Exception("No field '$field' in " . get_class($this));
		}

		return $property
			? (isset($ar[$field][$property]) ? $ar[$field][$property] : null)
			: $ar[$field];
	}

	public static function getFieldFlags($fieldsAr, $field)
	{
		$flags = isset($fieldsAr[$field]["flags"]) ? $fieldsAr[$field]["flags"] : [];

		if (!is_array($flags))
		{
			$flags = [$flags];
		}

		return $flags;
	}

	public static function getFieldProperties($fieldsAr, $field, $property = null)
	{
		return $property
			? (isset($fieldsAr[$field][$property]) ? $fieldsAr[$field][$property] : null)
			: $fieldsAr[$field];

	}

	public static function getFieldOptions($fieldsAr, $field)
	{
		return (array)self::getFieldProperties($fieldsAr, $field, 'options');
	}

	/*
	 * these three methods could be overridden in child classes
	 */
	public function getFormTabs()
	{
		return isset($GLOBALS["tables_tabs_ar"][$this->getTable()])
			? $GLOBALS["tables_tabs_ar"][$this->getTable()]
			: null;
	}

	public function getFormFields()
	{
		return $GLOBALS[$this->getTable()."_form_fields"];
	}

	public function getLocalFields()
	{
		return $GLOBALS[$this->getTable()."_local_fields"];
	}

	public function getFormFieldsFiltered()
	{
		$ar = $this->filterFields($this->getFormFields());

		if ($this->useEditLog() && $this->getId())
		{
			$ar[diAdminTableEditLogModel::ADMIN_TAB_NAME] = [
				"type" => "string",
				"title" => "Журнал изменений",
				"default" => "",
				"flags" => ["virtual", "static"],
				"tab" => diAdminTableEditLogModel::ADMIN_TAB_NAME,
			];
		}

		return $ar;
	}

	public function getLocalFieldsFiltered()
	{
		return $this->filterFields($this->getLocalFields());
	}

	/**
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function filterFields($fields = [])
	{
		return $fields;
	}

	public function getAllFields()
	{
		return array_merge($this->getFormFieldsFiltered(), $this->getLocalFieldsFiltered());
	}

	public function getFormFieldNames()
	{
		return array_keys($this->getFormFieldsFiltered());
	}

	public function getLocalFieldNames()
	{
		return array_keys($this->getLocalFieldsFiltered());
	}

	public function getAllFieldNames()
	{
		return array_keys($this->getAllFields());
	}

	/**
	 * @param array $fieldsAr       Array with fields data
	 * @param array|string $fields  Field name(s) or assoc array ($field => $flag)
	 * @param string|null $flag     Flag name (hidden, static, etc.)
	 * @param boolean $state        Add or remove flag
	 * @return array
	 */
	public static function setFieldFlag($fieldsAr, $field, $flag = null, $state = true)
	{
		if ($flag === null && is_array($field))
		{
			foreach ($field as $f => $fl)
			{
				$fieldsAr = self::setFieldFlag($fieldsAr, $f, $fl, $state);
			}
		}
		else
		{
			if (!is_array($flag))
			{
				$flag = [$flag];
			}

			$fields = is_array($field) ? $field : [$field];

			foreach ($fields as $field)
			{
				$flags = self::getFieldFlags($fieldsAr, $field);

				if ($state)
				{
					$fieldsAr[$field]["flags"] = array_merge($flags, $flag);
				}
				else
				{
					foreach ($flag as $fl)
					{
						$fieldsAr[$field]["flags"] = ArrayHelper::removeByValue($flags, $fl);
					}
				}
			}
		}

		return $fieldsAr;
	}

	public static function setFieldOption($fieldsAr, $field, $option, $value = null)
	{
		if ($value === null && is_array($option))
		{
			foreach ($option as $k => $v)
			{
				$fieldsAr = self::setFieldOption($fieldsAr, $field, $k, $v);
			}
		}
		else
		{
			$fields = is_array($field) ? $field : [$field];

			foreach ($fields as $field)
			{
				$options = self::getFieldOptions($fieldsAr, $field);
				$options[$option] = $value;

				$fieldsAr = self::setFieldProperty($fieldsAr, $field, 'options', $options);
			}
		}

		return $fieldsAr;
	}

	public static function setFieldProperty($fieldsAr, $field, $property, $value = null)
	{
		if ($value === null && is_array($property))
		{
			foreach ($property as $k => $v)
			{
				$fieldsAr = self::setFieldProperty($fieldsAr, $field, $k, $v);
			}
		}
		else
		{
			$fields = is_array($field) ? $field : [$field];

			foreach ($fields as $field)
			{
				$fieldsAr[$field][$property] = $value;
			}
		}

		return $fieldsAr;
	}

	public static function addFieldFlag($fieldsAr, $field, $flag = null)
	{
		return self::setFieldFlag($fieldsAr, $field, $flag, true);
	}

	public static function removeFieldFlag($fieldsAr, $field, $flag = null)
	{
		return self::setFieldFlag($fieldsAr, $field, $flag, false);
	}

	public static function setFieldsHidden($fieldsAr, $fields)
	{
		return self::addFieldFlag($fieldsAr, $fields, "hidden");
	}

	public static function setFieldsStatic($fieldsAr, $fields)
	{
		return self::addFieldFlag($fieldsAr, $fields, "static");
	}

	public static function setFieldsVisible($fieldsAr, $fields)
	{
		return self::removeFieldFlag($fieldsAr, $fields, "hidden");
	}

	public static function setFieldsEditable($fieldsAr, $fields)
	{
		return self::removeFieldFlag($fieldsAr, $fields, "static");
	}

	public static function setFieldTitle($fieldsAr, $field, $title)
	{
		$fieldsAr[$field]["title"] = $title;

		return $fieldsAr;
	}

	public function getModuleCaption()
	{
		global $admin_captions_ar;

		if (!isset($admin_captions_ar[$this->getLanguage()][$this->getTable()]))
		{
			return null;
		}

		$s = $admin_captions_ar[$this->getLanguage()][$this->getTable()];
		if (($x = strpos($s, " / ")) !== false)
		{
			$s = substr($s, 0, $x);
		}

		return $s;
	}

	public function getMethodCaption($action)
	{
		return isset($this->methodCaptionsAr[$this->getLanguage()][$action])
			? $this->methodCaptionsAr[$this->getLanguage()][$action]
			: null;
	}

	public function getCurrentMethodCaption()
	{
		return $this->getMethodCaption($this->getRefinedMethod());
	}

	public function addButtonNeededInCaption()
	{
		return true;
	}

	public function useEditLog()
	{
		return false;
	}
}