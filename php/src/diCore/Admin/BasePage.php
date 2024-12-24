<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.06.2015
 * Time: 14:35
 */

namespace diCore\Admin;

use diCore\Base\CMS;
use diCore\Data\Configuration;
use diCore\Data\Environment;
use diCore\Entity\Admin\Collection as Admins;
use diCore\Entity\AdminTableEditLog\Collection as TableEditLogs;
use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use diCore\Tool\Logger;
use Twig\Extension\EscaperExtension;

abstract class BasePage
{
    /** @var Base */
    private $X;

    /** @var \diAdminList */
    private $List;

    /** @var Grid */
    private $Grid;

    /** @var \diAdminFilters */
    private $Filters;

    /** @var Form */
    private $Form;

    /** @var Submit */
    private $Submit;

    /** @var \diPagesNavy */
    protected $PagesNavy;

    /** @var string */
    protected $table;

    const basePath = null;

    /** @var integer */
    protected $id;

    /** @var integer */
    protected $originalId;

    /** @var \diCollection */
    private $listCollection;

    /** @var callable|null */
    private $renderCallback;

    private $floatingSubmit = false;

    private $redirectAfterSubmitNeeded = true;

    private $redirectSent = false;

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
            'list' => 'Управление',
            'add' => 'Добавление',
            'edit' => 'Редактирование',
        ],
        'en' => [
            'list' => 'Manage',
            'add' => 'Add',
            'edit' => 'Edit',
        ],
    ];

    const VOCABULARY_MODULE_CAPTION = 'module.caption';

    /**
     * @var array
     * Keys: module.caption
     */
    protected $vocabulary = [
        'ru' => [],
        'en' => [],
    ];
    private $vocabularyAssigned = false;

    public static $customListButtonTitles = [
        /*
	    'en' => [
	        'create' => 'My own create title',
            'visible' => [0 => 'Invisible', 1 => 'Visible'],
        ],
        'ru' => [
            'create' => 'Самосоздание',
            'visible' => [0 => 'Скрыто', 1 => 'Видно'],
        ],
	    */
    ];

    /*
	 * override this in child classed
	 * possible keys:
	 *      updateSearchIndexOnSubmit
	 *      staticMode
	 *      showControlPanel
	 *      showHeader
	 *      filters
				'defaultSorter' => [
					'sortBy' => 'order_num',
					'dir' => 'ASC',
				],
				'sortByAr' => [
					'title' => 'By title',
					'order_num' => 'By order',
				],
	 *      formBasePath
	 */
    /** @var array */
    protected $options = [];

    /** @var array */
    protected $customOptions = [];

    public static $listOptions = ['showControlPanel', 'showHeader', 'formBasePath'];

    protected $staticInjections = [
        'js' => [],
        'css' => [],
    ];

    protected $staticCodeInjections = [
        'js' => [],
        'css' => [],
        'html' => [],
    ];

    public function __construct(Base $X)
    {
        if (Environment::shouldLogSpeed()) {
            Logger::getInstance()->speed('constructor', static::class);
        }

        $this->X = $X;

        $this->collectId();
    }

    protected function collectId()
    {
        $this->setId($this->getAdmin()->getId(), true);

        return $this;
    }

    /**
     * @param Base $X
     * @return static
     * @throws \Exception
     */
    public static function create(Base $X)
    {
        /** @var BasePage $o */
        $o = new static($X);
        $X->setAdminPage($o);

        $o->tryToInitTable();

        try {
            $o->getAdmin()->beforeRender();

            if (Environment::shouldLogSpeed()) {
                Logger::getInstance()->speed(
                    "Method={$o->getMethod()}",
                    static::class
                );
            }

            switch ($o->getMethod()) {
                case 'list':
                    if ($o->beforeRenderList()) {
                        $o->renderList();
                    }
                    $o->afterRenderList();
                    break;

                case 'form':
                    if ($o->beforeRenderForm()) {
                        $o->renderForm();
                    }
                    $o->afterRenderForm();
                    break;

                case 'submit':
                    if ($o->beforeSubmitForm()) {
                        $o->submitForm();
                    }
                    $o->afterSubmitForm();
                    break;

                default:
                    $m = Base::getClassMethodName($o->getMethod());
                    $beforeM = Base::getClassMethodName($o->getMethod(), 'before');
                    $afterM = Base::getClassMethodName($o->getMethod(), 'after');

                    if (!method_exists($o, $m)) {
                        throw new \Exception("Unknown method '{$o->getMethod()}'");
                    }

                    if (!method_exists($o, $beforeM)) {
                        throw new \Exception(
                            "No before-handler fo '{$o->getMethod()}'"
                        );
                    }

                    if (!method_exists($o, $afterM)) {
                        throw new \Exception(
                            "No after-handler fo '{$o->getMethod()}'"
                        );
                    }

                    if ($o->$beforeM()) {
                        $o->$m();
                    }
                    $o->$afterM();

                    break;
            }

            if (Environment::shouldLogSpeed()) {
                Logger::getInstance()->speed('Method worked', static::class);
            }

            $o->getAdmin()->afterRender();
        } catch (\Exception $e) {
            if (CMS::isDev()) {
                throw $e;
            } else {
                die($e->getMessage());
            }
        }

        $o->tryToDieAfterRedirect();

        $o->assignStaticInjections();

        if ($o->hasRenderCallback()) {
            $cb = $o->getRenderCallback();
            $result = $cb();

            if (Environment::shouldLogSpeed()) {
                Logger::getInstance()->speed('hasRenderCallback', static::class);
            }

            if ($result) {
                $o->getTpl()->assign([
                    'PAGE' => $result,
                ]);

                $o->getTwig()->assign([
                    \diTwig::TOKEN_FOR_PAGE => $result,
                ]);
            }
        } elseif ($o->getTwig()->hasPage()) {
            $o->getTpl()->assign([
                'PAGE' => $o->getTwig()->getPage(),
            ]);
        } elseif ($o->getTpl()->defined('page')) {
            $o->getTpl()->process('page');

            $o->getTwig()->assign([
                \diTwig::TOKEN_FOR_PAGE => $o->getTpl()->getAssigned('page'),
            ]);
        }

        if (Environment::shouldLogSpeed()) {
            Logger::getInstance()->speedFinish('finish', static::class);
        }

        return $o;
    }

    public static function liteCreate($module)
    {
        $className = Base::getModuleClassName($module);
        $adminBaseClassName = \diLib::getChildClass(Base::class);

        /** @var Base $X */
        $X = new $adminBaseClassName(Base::INIT_MODE_LITE);
        /** @var self $Page */
        $Page = new $className($X);
        $Page->tryToInitTable();

        return $Page;
    }

    public function liteSubmit()
    {
        if ($this->beforeSubmitForm()) {
            $this->submitForm();
        }
        $this->afterSubmitForm();

        return $this;
    }

    public function setRenderCallback(callable $callback)
    {
        $this->renderCallback = $callback;

        return $this;
    }

    public function hasRenderCallback()
    {
        return !!$this->renderCallback;
    }

    public function getRenderCallback()
    {
        return $this->renderCallback;
    }

    public function renderList()
    {
    }

    public function renderForm()
    {
    }

    public function submitForm()
    {
    }

    public function getLanguage()
    {
        return $this->getAdmin()->getLanguage();
    }

    protected function localized($ar)
    {
        return $ar[$this->getLanguage()];
    }

    public function isColonNeededInFormTitles()
    {
        return $this->getAdmin()->isColonNeededInFormTitles();
    }

    protected function printList()
    {
        switch ($this->listMode) {
            case self::LIST_LIST:
                $this->defaultPrintList();
                break;

            case self::LIST_GRID:
                $this->defaultPrintGrid();
                break;
        }

        return $this;
    }

    public function setFloatingSubmit($state = true)
    {
        $this->floatingSubmit = !!$state;

        return $this;
    }

    public function getFloatingSubmit()
    {
        return $this->floatingSubmit;
    }

    public function isRedirectAfterSubmitNeeded()
    {
        return $this->redirectAfterSubmitNeeded;
    }

    public function setRedirectAfterSubmitNeeded(bool $redirectAfterSubmitNeeded)
    {
        $this->redirectAfterSubmitNeeded = $redirectAfterSubmitNeeded;

        return $this;
    }

    public function tryToInitTable()
    {
        if (method_exists($this, 'initTable')) {
            $this->initTable();
        }

        return $this;
    }

    /**
     * @return Base
     */
    public function getAdmin()
    {
        return $this->X;
    }

    public function getOption($name)
    {
        $x = $this->getOptions();

        for ($i = 0; $i < func_num_args(); $i++) {
            $key = func_get_arg($i);

            if (isset($x[$key])) {
                $x = $x[$key];
            } else {
                return null;
            }
        }

        return $x;
    }

    protected function getPageOptions()
    {
        $opt = extend($this->options, $this->customOptions);

        if (static::basePath) {
            $opt['formBasePath'] = static::basePath;
        }

        return $opt;
    }

    public function getOptions($keys = [])
    {
        $opt = $this->getPageOptions();

        if (empty($keys)) {
            return $opt;
        }

        return ArrayHelper::filterByKey($opt, $keys);
    }

    public function getDb()
    {
        return $this->X->getDb();
    }

    /**
     * @deprecated
     * @return \FastTemplate
     */
    public function getTpl()
    {
        return $this->X->getTpl();
    }

    public function getTwig()
    {
        if (!$this->vocabularyAssigned) {
            $this->X->getTwig()->assign([
                'page_lang' => $this->vocabulary[$this->getLanguage()],
            ]);

            $this->vocabularyAssigned = true;
        }

        return $this->X->getTwig();
    }

    public function getData($field = null)
    {
        if ($this->Submit) {
            return $this->getSubmit()->getData($field);
        } elseif ($this->Form) {
            return $this->getForm()->getData($field);
        }

        return null;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    protected function initPagesNavy()
    {
        if (
            (!$this->PagesNavy && $this->isPagesNavyNeeded()) ||
            // && $this->hasFilters()
            ($this->PagesNavy &&
                !$this->PagesNavy->getWhere() &&
                $this->getFilters()->get_where())
        ) {
            $where = $this->getFilters()->get_where();
            $cbs = $this->getFilters()->getRuleCallbacks();

            $col = \diCollection::createForTable($this->getTable());
            foreach ($cbs as $cb) {
                $cb($col);
            }

            $this->PagesNavy = new \diPagesNavy(
                $this->getTable(),
                $this->getCountPerPage(),
                $where
                    ? \diCollection::mergeStringQueries($where, $col->getFullQuery())
                    : $col->getRealCount()
            );
        }

        return $this;
    }

    protected function isPagesNavyNeeded()
    {
        return !!Configuration::exists('admin_per_page[' . $this->getTable() . ']');
    }

    protected function getCountPerPage()
    {
        return Configuration::get('admin_per_page[' . $this->getTable() . ']');
    }

    protected function shouldPrintPagesNavy()
    {
        return $this->isPagesNavyNeeded();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function hasPagesNavy()
    {
        return !!$this->getPagesNavy(false);
    }

    /**
     * @param bool $strict
     * @return \diPagesNavy
     * @throws \Exception
     */
    public function getPagesNavy($strict = true)
    {
        $this->initPagesNavy();

        if (!$this->PagesNavy && $strict) {
            throw new \Exception('diPagesNavy not initialized');
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
     * @return \diAdminList
     * @throws \Exception
     */
    public function getList()
    {
        if (!$this->hasList()) {
            throw new \Exception('diAdminList not initialized');
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
     * @return Grid
     * @throws \Exception
     */
    public function getGrid()
    {
        if (!$this->hasGrid()) {
            throw new \Exception('Admin Grid not initialized');
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
     * @return Form
     * @throws \Exception
     */
    public function getForm()
    {
        if (!$this->Form) {
            throw new \Exception('diAdminForm not initialized');
        }

        return $this->Form;
    }

    /**
     * @return bool
     */
    public function hasFilters()
    {
        $this->initFilters();

        return !!$this->Filters;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function filtersBlockNeeded()
    {
        return $this->hasFilters() &&
            (!!$this->getOption('filters', 'sortByAr') ||
                !!$this->getFilters()->getFilters());
    }

    /**
     * @return \diAdminFilters
     * @throws \Exception
     */
    public function getFilters()
    {
        $this->initFilters();

        if (!$this->hasFilters()) {
            throw new \Exception('Filters not initialized');
        }

        return $this->Filters;
    }

    /**
     * @return bool
     */
    public function hasSubmit()
    {
        return !!$this->Submit;
    }

    /**
     * @return Submit
     * @throws \Exception
     */
    public function getSubmit()
    {
        if (!$this->Submit) {
            throw new \Exception('Submit not initialized');
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
     * @throws \Exception
     */
    public function getTable()
    {
        if (!$this->hasTable()) {
            throw new \Exception('Table undefined in ' . get_class($this));
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

    public function setId($id, $setOriginal = false)
    {
        $this->id = $id;

        if ($setOriginal) {
            $this->setOriginalId($id);
        }

        return $this;
    }

    public function setOriginalId($id)
    {
        $this->originalId = $id;

        return $this;
    }

    protected function getCurrentModel($strict = true)
    {
        if ($this->hasList()) {
            return $this->getList()->getCurModel();
        } elseif ($this->hasGrid()) {
            return $this->getGrid()->getCurModel();
        } elseif ($this->hasForm()) {
            return $this->getForm()->getModel();
        } elseif ($this->hasSubmit()) {
            return $this->getSubmit()->getModel();
        }

        if ($strict) {
            throw new \Exception('Where the hell are we? No current model detected');
        }

        return new \diModel();
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
        if (method_exists($this, 'submitForm')) {
            return Base::getPageUri($this->getBasePath(), 'submit');
        }

        return $this->getTable() . '/submit.php';
    }

    protected function getListQueryFilters()
    {
        return $this->hasFilters() ? $this->getFilters()->getQuery() : '';
    }

    protected function getListRuleCallbacks()
    {
        return $this->hasFilters() ? $this->getFilters()->getRuleCallbacks() : [];
    }

    protected function getListQueryLimit()
    {
        return $this->hasPagesNavy() ? $this->getPagesNavy()->getSqlLimit() : '';
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
        return extend(
            [
                'query' => '', // deprecated
                'ruleCallbacks' => [],
                'filterBy' => [],
                'limit' => '',
                'pageNumber' => null,
                'pageSize' => null,
                'sortBy' => '',
                'dir' => null,
            ],
            $options
        );
    }

    protected function createListCollection($options = [])
    {
        $this->listCollection = \diCollection::createForTable(
            $this->getTable(),
            $options['query']
        );

        foreach ($options['ruleCallbacks'] as $cb) {
            $cb($this->listCollection);
        }

        return $this;
    }

    protected function getDefaultListRows($options = [])
    {
        $options = $this->extendListQueryOptions($options);

        $this->createListCollection($options);

        if ($options['sortBy']) {
            if (is_array($options['sortBy'])) {
                foreach ($options['sortBy'] as $field => $direction) {
                    $this->listCollection->orderBy($field, $direction);
                }
            } else {
                $this->listCollection->orderBy($options['sortBy'], $options['dir']);
            }
        }

        if ($options['pageSize']) {
            $this->listCollection->setPageSize($options['pageSize']);
        }

        if ($options['pageNumber']) {
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
     * @return \diCollection
     */
    public function getListCollection()
    {
        return $this->listCollection;
    }

    protected function defaultPrintListRow($model, $options = [])
    {
        $this->getList()->addRow($model, $options);

        return $this;
    }

    protected function defaultPrintListRows($rs)
    {
        if (is_array($rs) || $rs instanceof \diCollection) {
            foreach ($rs as $model) {
                $this->defaultPrintListRow($model);
            }
        } else {
            while ($r = $this->getDb()->fetch($rs)) {
                $this->defaultPrintListRow($r);
            }
        }

        return $this;
    }

    protected function extendListOptions($options = [])
    {
        return extend(
            [
                'query' => $this->getListQueryFilters(),
                'ruleCallbacks' => $this->getListRuleCallbacks(),
                'limit' => $this->getListQueryLimit(),
                'pageNumber' => $this->getListPageNumber(),
                'pageSize' => $this->getListPageSize(),
                'sortBy' => $this->hasFilters()
                    ? $this->getFilters()->getSortBy()
                    : 'id',
                'dir' => $this->hasFilters()
                    ? $this->getFilters()->getDir()
                    : 'DESC',
            ],
            $options
        );
    }

    protected function defaultPrintList($options = [])
    {
        $this->defaultPrintListRows(
            $this->getDefaultListRows($this->extendListOptions($options))
        );

        return $this;
    }

    protected function defaultPrintGrid($options = [])
    {
        $this->defaultPrintGridRows(
            $this->getDefaultListRows($this->extendListOptions($options))
        );

        return $this;
    }

    protected function defaultPrintGridRows(\diCollection $collection)
    {
        $this->getGrid()->printElements($collection);

        return $this;
    }

    /**
     * Prefix added before IMG urls. If it is an external URL, this should return empty string
     *
     * @param \diModel $model
     * @return string
     */
    public function getImgUrlPrefix(\diModel $model)
    {
        return class_exists('\diExternalFolders') &&
            $model->exists(\diExternalFolders::FIELD_NAME) &&
            $model->get(\diExternalFolders::FIELD_NAME) != \diExternalFolders::MAIN
            ? ''
            : '/';
    }

    protected function beforeRenderList()
    {
        if ($this->listMode == self::LIST_LIST) {
            $this->getTpl()->define('`_default/list', ['page']);
        }

        $this->getTpl()->assign([
            'FILTERS' => '',
            'NAVY' => '',
            'BEFORE_TABLE' => '',
            'AFTER_TABLE' => '',
            'TABLE_NAME' => $this->hasTable() ? $this->getTable() : '',
            'GRID_ROWS' => '',
        ]);

        if ($this->hasTable()) {
            $this->initFilters();

            switch ($this->listMode) {
                case self::LIST_LIST:
                    $listOptions = $this->getOptions(self::$listOptions);
                    $this->List = new \diAdminList($this, $listOptions);
                    break;

                case self::LIST_GRID:
                    $this->Grid = new Grid($this);
                    break;
            }
        }

        return true;
    }

    protected function initFilters()
    {
        if (!$this->Filters && ($filters = $this->getOption('filters'))) {
            $this->Filters = new \diAdminFilters($this);
            $this->getFilters()->setSortableState(isset($filters['sortByAr']));

            if (isset($filters['defaultSorter'])) {
                $this->getFilters()->set_default_sorter($filters['defaultSorter']);
            }

            if (isset($filters['buttonOptions'])) {
                $this->getFilters()->setButtonOptions($filters['buttonOptions']);
            }

            $this->setupFilters();

            if ($this->getFilters()->getSortableState()) {
                $sortBy = ArrayHelper::mapAssoc(function ($key, $value) {
                    if (is_array($value)) {
                        $value = $value[$this->getLanguage()];
                    }

                    return [$key, $value];
                }, $filters['sortByAr']);

                $this->getFilters()
                    ->setSelectFromArrayInput('sortby', $sortBy)
                    ->setSelectFromArrayInput(
                        'dir',
                        \diAdminFilters::$dirAr[$this->getLanguage()]
                    );
            }
        }

        return $this;
    }

    protected function setupFilters()
    {
        if ($this->filtersBlockNeeded()) {
            $this->getFilters()->buildQuery();
        }

        return $this;
    }

    public function setListControlPanelTemplate($template = null, $data = [])
    {
        if ($template === null) {
            $template = 'admin/' . $this->getTable() . '/list_control_panel';
        }

        $this->getTwig()->render(
            $template,
            'list_control_panel',
            extend(
                [
                    'table' => $this->getTable(),
                    'List' => $this->getList(),
                ],
                $data
            )
        );

        return $this;
    }

    public function setBeforeTableTemplate($template = null, $data = [])
    {
        if (is_array($template) && !$data) {
            $data = $template;
            $template = null;
        }

        if ($template === null) {
            $template = 'admin/' . $this->getTable() . '/before_list';
        }

        $this->getTwig()->render($template, 'before_table', $data);

        return $this;
    }

    public function setAfterTableTemplate($template = null, $data = [])
    {
        if (is_array($template) && !$data) {
            $data = $template;
            $template = null;
        }

        if ($template === null) {
            $template = 'admin/' . $this->getTable() . '/after_list';
        }

        $this->getTwig()->render($template, 'after_table', $data);

        return $this;
    }

    public function setBeforeFormTemplate($template = null, $data = [])
    {
        if (is_array($template) && !$data) {
            $data = $template;
            $template = null;
        }

        if ($template === null) {
            $template = 'admin/' . $this->getTable() . '/before_form';
        }

        $this->getTwig()->render($template, 'before_form', $data);

        return $this;
    }

    public function setAfterFormTemplate($template = null, $data = [])
    {
        if (is_array($template) && !$data) {
            $data = $template;
            $template = null;
        }

        if ($template === null) {
            $template = 'admin/' . $this->getTable() . '/after_form';
        }

        $this->getTwig()->render($template, 'after_form', $data);

        return $this;
    }

    public function injectJs($className)
    {
        $this->staticInjections['js'][] = $className;

        return $this;
    }

    public function injectCss($fileName)
    {
        $this->staticInjections['css'][] = $fileName;

        return $this;
    }

    public function injectJsCode($code, $varName = null)
    {
        $this->staticCodeInjections['js'][] =
            is_string($code) && $varName === null
                ? $code
                : 'var ' . $varName . ' = ' . json_encode($code) . ';';

        return $this;
    }

    public function injectCssCode($code)
    {
        $this->staticCodeInjections['css'][] = $code;

        return $this;
    }

    public function injectHtmlCode($code)
    {
        $this->staticCodeInjections['html'][] = $code;

        return $this;
    }

    public function assignStaticInjections()
    {
        $this->getTwig()->assign([
            'static_injections' => $this->staticInjections,
            'static_code_injections' => $this->staticCodeInjections,
        ]);

        return $this;
    }

    protected function beforeRenderFilters()
    {
        return $this;
    }

    protected function afterRenderFilters()
    {
        return $this;
    }

    protected function afterRenderList()
    {
        if ($this->hasList() || $this->hasGrid()) {
            if ($this->hasPagesNavy() && $this->shouldPrintPagesNavy()) {
                $this->getTpl()
                    ->assign([
                        'PAGES_NAVY' => $this->getPagesNavy()->print_pages(
                            $this->getAdmin()->getCurrentPageUri()
                            //Base::getPageUri($this->getModule())
                        ),
                    ])
                    ->parse('navy');
            }

            $this->printList();

            if ($this->hasList()) {
                $this->getTpl()->assign([
                    'TABLE' => $this->getList()->getHtml(),
                ]);
            }
        }

        $this->beforeRenderFilters();

        if ($this->filtersBlockNeeded()) {
            $this->getTpl()->assign([
                'FILTERS' =>
                    $this->getFilters()->getBlockHtml() .
                    $this->getFilters()->get_js_data(true),
            ]);
        }

        $this->afterRenderFilters();

        if ($this->getTwig()->assigned('before_table')) {
            $this->getTpl()->assign(
                'before_table',
                $this->getTwig()->getAssigned('before_table')
            );
        } elseif ($this->getTpl()->defined('before_table')) {
            $this->getTpl()->parse('before_table');
        }

        if ($this->getTwig()->assigned('after_table')) {
            $this->getTpl()->assign(
                'after_table',
                $this->getTwig()->getAssigned('after_table')
            );
        } elseif ($this->getTpl()->defined('after_table')) {
            $this->getTpl()->parse('after_table');
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function beforeRenderForm()
    {
        $this->getTpl()
            ->define('`_default/form', ['page'])
            ->assign(
                [
                    'ACTION' => $this->getFormWorkerUri(),
                    'TABLE' => $this->getTable(),
                    'ID' => $this->getId(),
                ],
                'ADMIN_FORM_'
            )
            ->assign([
                'BEFORE_FORM' => '',
                'AFTER_FORM' => '',
            ]);

        $this->getTwig()->assign([
            '_form' => [
                'table' => $this->getTable(),
                'id' => $this->getId(),
                'model' => $this->getCurrentModel(false),
            ],
        ]);

        $this->prepareForEditLog();

        $this->Form = Form::basicCreate($this);
        $this->getForm()->afterInit([
            'static_mode' => $this->getOption('staticMode'),
        ]);

        return true;
    }

    protected function getFormSubmitButtonsBlock()
    {
        return $this->getForm()->getSubmitButtons();
    }

    protected function prepareForEditLog()
    {
        $this->getTwig()
            ->getEngine()
            ->getExtension(EscaperExtension::class)
            ->setEscaper('insdel', function ($twig, $string, $charset) {
                $escaped = StringHelper::out($string);
                $semiEscaped = str_replace(
                    ['&lt;ins&gt;', '&lt;/ins&gt;', '&lt;del&gt;', '&lt;/del&gt;'],
                    ['<ins>', '</ins>', '<del>', '</del>'],
                    $escaped
                );

                return $semiEscaped;

                // return '(' . $semiEscaped . ')';
            });

        return $this;
    }

    protected function printEditLog()
    {
        if (!$this->useEditLog() || $this->hideEditLog() || !$this->getId()) {
            return $this;
        }

        $records = TableEditLogs::create()
            ->filterByTargetTable($this->getTable())
            ->filterByTargetId([$this->getId(), (int) $this->getId()])
            ->orderById('DESC');

        $admins = Admins::create();

        /** @var TableEditLog $rec */
        foreach ($records as $rec) {
            $rec->parseData();
        }

        $options = extend(
            [
                'show_only_diff' => false,
                'strip_tags' => false,
            ],
            (array) $this->useEditLog()
        );

        $this->getForm()->setInput(
            TableEditLog::ADMIN_TAB_NAME,
            $this->getTwig()->parse('admin/admin_table_edit_log/form_field', [
                'records' => $records,
                'admins' => $admins,
                'options' => $options,
            ])
        );

        return $this;
    }

    protected function afterRenderForm()
    {
        $this->printEditLog();

        $this->getTpl()->assign([
            'FORM' => $this->getForm()->get_html(),
            'SUBMIT_BLOCK' => $this->getFormSubmitButtonsBlock(),
        ]);

        if ($this->getTwig()->assigned('before_form')) {
            $this->getTpl()->assign(
                'before_form',
                $this->getTwig()->getAssigned('before_form')
            );
        } elseif ($this->getTpl()->defined('before_form')) {
            $this->getTpl()->parse('before_form');
        }

        if ($this->getTwig()->assigned('after_form')) {
            $this->getTpl()->assign(
                'after_form',
                $this->getTwig()->getAssigned('after_form')
            );
        } elseif ($this->getTpl()->defined('after_form')) {
            $this->getTpl()->parse('after_form');
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function beforeSubmitForm()
    {
        $this->Submit = new Submit($this);

        if ($this->getSubmit()->isSubmit() && !$this->getOption('staticMode')) {
            $this->getSubmit()->gatherData();

            return true;
        }

        return false;
    }

    protected function afterSubmitForm()
    {
        if ($this->reallySubmit() && $this->getSubmit()->isSubmit()) {
            $this->getSubmit()->storeData();
        }

        if ($this->getOption('updateSearchIndexOnSubmit')) {
            \diSearch::makeRecordIndex($this->getTable(), $this->getId());
        }

        $this->addEditLogRecord()->redirectAfterSubmit();
    }

    public function tryToDieAfterRedirect()
    {
        if (
            $this->getMethod() === 'submit' &&
            $this->redirectSent &&
            Config::basicCreate()::shouldSubmitUseHtmlRedirect()
        ) {
            die();
        }
    }

    protected function addEditLogRecord()
    {
        if ($this->useEditLog()) {
            try {
                $log = TableEditLog::create()
                    ->setFormFields($this->getFormFieldsFiltered())
                    ->setTargetTable($this->getTable())
                    ->setTargetId($this->getId())
                    ->setAdminId(
                        $this->getAdmin()
                            ->getAdminModel()
                            ->getId()
                    )
                    ->setBothData($this->getSubmit()->getModel());

                if ($log->hasOldData() && $log->hasNewData()) {
                    $log->save();
                }
            } catch (\Exception $e) {
                // validation failed -> no changes
                //throw $e;
            }
        }

        return $this;
    }

    protected function redirectTo($uri)
    {
        $this->redirectSent = true;

        if (Config::basicCreate()::shouldSubmitUseHtmlRedirect()) {
            echo "<html><head><meta http-equiv=\"refresh\" content=\"0; url=$uri\"></head></html><style>*{display:none}</style>";

            return $this;
        }

        header("Location: $uri");

        return $this;
    }

    protected function getQueryParamsForRedirectToFormAfterSubmit()
    {
        $ar = [];

        if ($this->getSubmit()->getData('parent') > 0) {
            $ar['parent'] = $this->getSubmit()->getData('parent');
        }

        return $ar;
    }

    protected function getQueryParamsForRedirectAfterSubmit()
    {
        $ar = [];

        // calculating page
        $this->beforeRenderList();
        $this->renderList();

        $sortBy = $this->hasFilters() ? $this->getFilters()->getSortBy() : 'title';
        $dir = $this->hasFilters() ? $this->getFilters()->getDir() : 'ASC';

        $page = null;

        try {
            if ($this->hasPagesNavy()) {
                $page = $this->getPagesNavy()->get_page_of(
                    $this->getId(),
                    $sortBy,
                    $dir
                );
            }
        } catch (\Exception $e) {
            // skip, no page then
        }

        if ($page) {
            $ar['page'] = $page;
        }

        // to prevent hasList() === true
        $this->List = $this->Grid = null;

        return $ar;
    }

    protected function useAnchorInRedirectAfterSubmitUrl()
    {
        return true;
    }

    protected function getRedirectToValue()
    {
        return \diRequest::post('__redirect_to', 'list');
    }

    protected function getRedirectAfterSubmitUrl()
    {
        $method = $this->getRedirectToValue();
        $anchorNeeded =
            $method === 'list' &&
            !!$this->getId() &&
            $this->useAnchorInRedirectAfterSubmitUrl();
        $anchor = $anchorNeeded
            ? '#' . \diNiceTable::getRowAnchorName($this->getId())
            : '';
        $params =
            $method === 'list'
                ? $this->getQueryParamsForRedirectAfterSubmit()
                : $this->getQueryParamsForRedirectToFormAfterSubmit();

        return Base::getPageUri($this->getBasePath(), $method, $params) . $anchor;
    }

    protected function redirectAfterSubmit()
    {
        if ($this->isRedirectAfterSubmitNeeded()) {
            $this->redirectTo($this->getRedirectAfterSubmitUrl());
        }

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

        if (!isset($ar[$field])) {
            throw new \Exception("No field '$field' in " . get_class($this));
        }

        return $property ? $ar[$field][$property] ?? null : $ar[$field];
    }

    public static function getFieldFlags($fieldsAr, $field)
    {
        $flags = $fieldsAr[$field]['flags'] ?? [];

        if (!is_array($flags)) {
            $flags = [$flags];
        }

        return $flags;
    }

    public static function getFieldProperties($fieldsAr, $field, $property = null)
    {
        return $property ? $fieldsAr[$field][$property] ?? null : $fieldsAr[$field];
    }

    public static function getFieldOptions($fieldsAr, $field)
    {
        return (array) self::getFieldProperties($fieldsAr, $field, 'options');
    }

    /*
     * these three methods could be overridden in child classes
     */
    public function getFormTabs()
    {
        return $GLOBALS['tables_tabs_ar'][$this->getTable()] ?? null;
    }

    public function getFormFields()
    {
        return $GLOBALS[$this->getTable() . '_form_fields'];
    }

    public function getLocalFields()
    {
        return $GLOBALS[$this->getTable() . '_local_fields'];
    }

    public function getFormFieldsFiltered()
    {
        $ar = $this->filterFields($this->getFormFields());

        if ($this->useEditLog() && !$this->hideEditLog() && $this->getId()) {
            $ar[TableEditLog::ADMIN_TAB_NAME] = [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Журнал изменений',
                    'en' => 'Edit log',
                ]),
                'default' => '',
                'flags' => ['virtual', 'static'],
                'tab' => TableEditLog::ADMIN_TAB_NAME,
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
        return array_merge(
            $this->getFormFieldsFiltered(),
            $this->getLocalFieldsFiltered()
        );
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
    public static function setFieldFlag(
        $fieldsAr,
        $field,
        $flag = null,
        $state = true
    ) {
        if ($flag === null && is_array($field)) {
            foreach ($field as $f => $fl) {
                $fieldsAr = self::setFieldFlag($fieldsAr, $f, $fl, $state);
            }
        } else {
            if (!is_array($flag)) {
                $flag = [$flag];
            }

            $fields = is_array($field) ? $field : [$field];

            foreach ($fields as $field) {
                $flags = self::getFieldFlags($fieldsAr, $field);

                if ($state) {
                    $fieldsAr[$field]['flags'] = array_merge($flags, $flag);
                } else {
                    foreach ($flag as $fl) {
                        $fieldsAr[$field]['flags'] = ArrayHelper::removeByValue(
                            $flags,
                            $fl
                        );
                    }
                }
            }
        }

        return $fieldsAr;
    }

    public static function setFieldOption($fieldsAr, $field, $option, $value = null)
    {
        if ($value === null && is_array($option)) {
            foreach ($option as $k => $v) {
                $fieldsAr = self::setFieldOption($fieldsAr, $field, $k, $v);
            }
        } else {
            $fields = is_array($field) ? $field : [$field];

            foreach ($fields as $field) {
                $options = self::getFieldOptions($fieldsAr, $field);
                $options[$option] = $value;

                $fieldsAr = self::setFieldProperty(
                    $fieldsAr,
                    $field,
                    'options',
                    $options
                );
            }
        }

        return $fieldsAr;
    }

    public static function setFieldProperty(
        $fieldsAr,
        $field,
        $property,
        $value = null
    ) {
        if ($value === null && is_array($property)) {
            foreach ($property as $k => $v) {
                $fieldsAr = self::setFieldProperty($fieldsAr, $field, $k, $v);
            }
        } else {
            $fields = is_array($field) ? $field : [$field];

            foreach ($fields as $field) {
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
        return self::addFieldFlag($fieldsAr, $fields, 'hidden');
    }

    public static function setFieldsStatic($fieldsAr, $fields)
    {
        return self::addFieldFlag($fieldsAr, $fields, 'static');
    }

    public static function setFieldsVisible($fieldsAr, $fields)
    {
        return self::removeFieldFlag($fieldsAr, $fields, 'hidden');
    }

    public static function setFieldsEditable($fieldsAr, $fields)
    {
        return self::removeFieldFlag($fieldsAr, $fields, 'static');
    }

    public static function setFieldTitle($fieldsAr, $field, $title)
    {
        $fieldsAr[$field]['title'] = $title;

        return $fieldsAr;
    }

    public function getFieldTitle($name)
    {
        $F = Form::basicCreate($this);
        $props = $this->doesFieldExist($name)
            ? $this->getFieldProperty($name)
            : null;
        $title =
            $props['title'] ??
            $F::getFieldTitle($name, $props, $this->getLanguage()) ?:
            '';

        if (StringHelper::startsWith($title, '#')) {
            return '';
        }

        return $title;
    }

    public function getModuleCaption()
    {
        global $admin_captions_ar;

        if (isset($admin_captions_ar[$this->getLanguage()][$this->getTable()])) {
            $s = $admin_captions_ar[$this->getLanguage()][$this->getTable()];
            if (($x = strpos($s, ' / ')) !== false) {
                $s = substr($s, 0, $x);
            }

            return $s;
        }

        return $this->getVocabularyTerm(self::VOCABULARY_MODULE_CAPTION);
    }

    public function getMethodCaption($action)
    {
        return $this->methodCaptionsAr[$this->getLanguage()][$action] ?? null;
    }

    public function getCurrentMethodCaption()
    {
        return $this->getMethodCaption($this->getRefinedMethod());
    }

    public function linkNeededInCaption($method)
    {
        return $method !== 'list';
    }

    public function addButtonNeededInCaption()
    {
        return true;
    }

    public function getAddButtonUrlQueryParams()
    {
        return [];
    }

    /**
     * @return bool|array
     */
    public function useEditLog()
    {
        return false;

        /*
         * return [
         *   'show_only_diff' => true, // old and new values will be hidden
         *   'strip_tags' => true, // html tags from content will be stripped
         * ]
         */
    }

    /**
     * Write logs on edit (if self::useEditLog() enabled) but don't show it in admin form
     */
    public function hideEditLog()
    {
        return false;
    }

    protected function reallySubmit()
    {
        return true;
    }

    public function getVocabularyTerm($name)
    {
        return $this->vocabulary[$this->getLanguage()][$name] ?? $name;
    }

    public static function getCustomListButtonTitles()
    {
        return static::$customListButtonTitles;
    }

    public function getListHref($params = [])
    {
        return Base::getPageUri($this->getModule(), 'list', $params);
    }

    public function getListFullHref($params = [])
    {
        return \diPaths::defaultHttp() . $this->getListHref($params);
    }
}
