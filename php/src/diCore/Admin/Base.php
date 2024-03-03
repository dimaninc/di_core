<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.01.2018
 * Time: 13:01
 */

namespace diCore\Admin;

use diCore\Admin\Data\Skin;
use diCore\Base\CMS;
use diCore\Base\Exception\HttpException;
use diCore\Data\Config;
use diCore\Data\Http\HttpCode;
use diCore\Data\Http\Response;
use diCore\Database\Connection;
use diCore\Entity\Admin\Level;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;

class Base
{
    const SUBFOLDER = '_admin';
    const DEFAULT_METHOD = 'list';

    const INIT_MODE_STANDARD = 0;
    const INIT_MODE_LITE = 1;

    /** @var \FastTemplate */
    private $tpl;

    /** @var \diTwig */
    private $Twig;

    protected $twigCreateOptions = [];

    /** @var \diAdminUser */
    protected $adminUser;

    /** @var BasePage */
    protected $adminPage;

    private $version = '5.2';

    protected $defaultSuperUsersAr = ['dimaninc'];
    protected $superUsersAr = [];

    protected $superUserModules = [
        'migrations',
        'migrations/log',
        'migrations/form',
        'di_lib_models/form',
        'di_lib_admin_pages/form',
    ];

    protected $localUserModules = [
        'migrations/form',
        'di_lib_models/form',
        'di_lib_admin_pages/form',
    ];

    protected $siteTitle = 'diCMS based';
    protected $language = 'ru';
    protected static $_language;

    protected $wysiwygVendor = Form::wysiwygTinyMCE;

    private static $vocabulary = [
        'ru' => [
            'logo_description' => 'Система управления сайтом',
            'remember_me' => 'Запомнить меня',
            'sign_in' => 'Войти',
            'sign_out' => 'Выход',
            'go_to_site' => 'На сайт',
            'support' => 'Вопросы',
            'add' => 'Добавить',
            'menu.expand_all' => 'Раскрыть всё',
            'menu.collapse_all' => 'Свернуть всё',
            'menu.search' => 'Поиск в меню',
            'menu.list' => 'Управление',
            'menu.add' => 'Добавить',
            'menu.db' => 'База данных',
            'menu.db.dump' => 'Резервное копирование',
            'menu.db.migrations.log' => 'Журнал миграций',
            'menu.db.migrations.list' => 'Миграции',
            'menu.db.migrations.create' => 'Создать миграцию',
            'menu.db.models.create' => 'Создать модель/коллекцию',
            'menu.db.admin_pages.create' => 'Создать админ.страницу',
            'menu.emails' => 'Письма',
            'menu.admins' => 'Админы',
            'menu.settings' => 'Служебное',
            'menu.edit_settings' => 'Настройки',
            'menu.rebuild_cache' => 'Обновить кеш',
            'error.caption' => 'Ошибка',
            'error.page_not_found' => 'Админ.страница не найдена',
        ],
        'en' => [
            'logo_description' => 'Content management system',
            'remember_me' => 'Remember me',
            'sign_in' => 'Sign in',
            'sign_out' => 'Sign out',
            'go_to_site' => 'Open website',
            'support' => 'Support',
            'add' => 'Add',
            'menu.expand_all' => 'Expand all',
            'menu.collapse_all' => 'Collapse all',
            'menu.search' => 'Search menu',
            'menu.list' => 'Manage',
            'menu.add' => 'Add',
            'menu.db' => 'Database',
            'menu.db.dump' => 'Dump/restore',
            'menu.db.migrations.log' => 'Migrations log',
            'menu.db.migrations.list' => 'Migrations',
            'menu.db.migrations.create' => 'Create migration',
            'menu.db.models.create' => 'Create model/collection',
            'menu.db.admin_pages.create' => 'Create admin page',
            'menu.emails' => 'E-mails',
            'menu.admins' => 'Admins',
            'menu.settings' => 'Settings',
            'menu.edit_settings' => 'Edit settings',
            'menu.rebuild_cache' => 'Rebuild cache',
            'error.caption' => 'Error',
            'error.page_not_found' => 'Admin page not found',
        ],
    ];

    /** @var  string */
    protected $table;
    /** @var  int */
    protected $id;
    /** @var  string */
    protected $module;
    /** @var  string */
    protected $method;

    /** @deprecated */
    protected $path;
    /** @deprecated */
    protected $filename;

    /** @var Response */
    protected $response;

    /** @var Caption */
    protected $caption;
    protected $forceShowExpandCollapse = false;

    /** @var callable|null */
    private $headPrinter = null;

    private $uriParams = [];

    protected $staticFiles = [
        'js' => ['_static/js/local.js'],
        'css' => ['_static/css/local.css'],
    ];
    protected $customStaticFiles = [
        'js' => [],
        'css' => [],
    ];

    public function __construct($mode = null)
    {
        static::$_language = $this->language;

        $this->superUsersAr = array_merge(
            $this->defaultSuperUsersAr,
            $this->superUsersAr
        );

        switch ($mode) {
            case self::INIT_MODE_LITE:
                $this->liteInit();
                break;

            case self::INIT_MODE_STANDARD:
            default:
                $this->standardInit();
                break;
        }
    }

    private function standardInit()
    {
        $this->caption = Caption::basicCreate($this);
        $this->adminUser = \diAdminUser::create();

        $this->readUri()
            ->readParams()
            ->checkRights()
            ->initTpl();

        return $this;
    }

    private function liteInit()
    {
        $this->adminUser = \diAdminUser::create();

        return $this;
    }

    public static function getSubFolder()
    {
        $subFolder = static::SUBFOLDER;

        if (\diLib::getSubFolder()) {
            $subFolder = \diLib::getSubFolder() . '/' . $subFolder;
        }

        return $subFolder;
    }

    /**
     * @return \diAdminUser
     */
    public function getAdminUser()
    {
        return $this->adminUser;
    }

    /**
     * @return \diCore\Entity\Admin\Model
     * @deprecated
     */
    public function getAdmin()
    {
        return $this->getAdminModel();
    }

    /**
     * @return \diCore\Entity\Admin\Model
     */
    public function getAdminModel()
    {
        return $this->getAdminUser()->getModel();
    }

    /**
     * @return bool
     */
    public function isAdminSuper()
    {
        return in_array($this->getAdminModel()->getLogin(), $this->superUsersAr);
    }

    public function hasPage()
    {
        return !!$this->adminPage;
    }

    /**
     * @return BasePage
     */
    public function getPage()
    {
        return $this->adminPage;
    }

    /**
     * @param string|null $term
     * @return array|string|null
     */
    public static function getVocabulary($term = null)
    {
        $v = self::$vocabulary[self::currentLanguage()];

        if ($term === null) {
            return $v;
        }

        return $v[$term] ?? null;
    }

    public function work()
    {
        try {
            $this->assignTplBase()->printContentPage();
        } catch (\Exception $e) {
            $this->getTwig()->render(
                'admin/_error/simple',
                \diTwig::TOKEN_FOR_PAGE,
                [
                    'e' => $e,
                ]
            );

            $this->caption->setForceValue(static::getVocabulary('error.caption'));

            if ($this->isResponseCode(HttpCode::OK)) {
                $code =
                    $e instanceof HttpException
                        ? $e->getCode()
                        : HttpCode::INTERNAL_SERVER_ERROR;

                $this->setResponseCode($code);
            }
        }

        $this->printMainMenu()
            ->printCaption()
            ->printHead();

        HttpCode::header($this->getResponseCode());

        echo $this->getFinalHtml();
    }

    protected function getFinalHtml()
    {
        try {
            return $this->getTwig()
                ->assign([
                    '_settings' => [
                        'floating_submit' => $this->getPage()
                            ? json_encode($this->getPage()->getFloatingSubmit())
                            : null,
                    ],
                ])
                ->renderIndex()
                ->getIndex();
        } catch (\Exception $e) {
            echo 'fasttemplate fallback';
            // var_dump($e);
            return $this->getTpl()->parse('index');
        }
    }

    protected function getStaticTimestampEnding()
    {
        return class_exists('\diStaticBuild') ? '?v=' . \diStaticBuild::VERSION : '';
    }

    public function addStaticFiles($type, $files)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $this->staticFiles[$type] = extend($this->staticFiles[$type], $files);

        return $this;
    }

    public function getAdminSkinId()
    {
        return Config::getAdminSkin();
    }

    public function isLocalSkin()
    {
        return $this->getAdminSkinId() == Skin::local;
    }

    private function getTemplateVariables()
    {
        return [
            'html_base' =>
                '//' . \diRequest::domain() . '/' . self::getSubFolder() . '/',
            'current_uri' => \diRequest::requestUri(),

            'logout_href' =>
                \diLib::getAdminWorkerPath('admin_auth', 'logout') .
                '?back=' .
                urlencode(\diRequest::requestUri()),

            'admin_skin' => Skin::name($this->getAdminSkinId()),
            'cms_name' => Config::getCmsName(),
            'cms_support_email' => Config::getCmsSupportEmail(),

            'site_subfolder' => \diLib::getSubFolder(),
            'site_path' =>
                (\diLib::getSubFolder() ? '/' . \diLib::getSubFolder() : '') . '/',
            'site_language' => $this->getLanguage(),
            'current_year' => date('Y'),
            'page_title' => $this->getPageTitle(),
            'site_title' => $this->getSiteTitle(),
            'site_logo' => $this->getSiteLogo(),

            'xx_version' => $this->getVersion(),

            'xx_path' => $this->getPath(),
            'xx_module' => $this->getModule(),
            'xx_table' => $this->getTable(),
            'xx_id' => $this->getId(),

            'static_timestamp' => $this->getStaticTimestampEnding(),
            'static_js_files' => extend(
                $this->staticFiles['js'],
                $this->customStaticFiles['js']
            ),
            'static_css_files' => extend(
                $this->staticFiles['css'],
                $this->customStaticFiles['css']
            ),
        ];
    }

    private function assignTplBase()
    {
        $this->getTpl()
            ->assign($this->getTemplateVariables())
            ->assign(\diLib::getAssetLocations(), 'ASSET_LOCATIONS.')
            ->assign(static::getVocabulary(), 'LANG.')
            ->assign($this->getAdminModel()->getTemplateVars(), 'ADMIN_');

        return $this;
    }

    /**
     * @deprecated
     * @return \FastTemplate
     */
    public function getTpl()
    {
        return $this->tpl;
    }

    /**
     * @return \diTwig
     */
    public function getTwig()
    {
        if ($this->Twig === null) {
            $this->Twig = \diTwig::create($this->twigCreateOptions);
            $this->Twig
                ->assign([
                    'X' => $this,
                    'lang' => static::getVocabulary(),
                    'admin' => $this->getAdminModel(),
                    'asset_locations' => \diLib::getAssetLocations(),
                    'caption' => $this->caption,
                ])
                ->setTemplateForIndex('admin/_index/index');
        }

        $this->Twig->assign([
            '_tech' => $this->getTemplateVariables(),
        ]);

        return $this->Twig;
    }

    /**
     * @deprecated
     * @return \diDB
     */
    public function getDb()
    {
        return Connection::get(Config::getMainDatabase())->getDb();
    }

    /**
     * @return \FastTemplate|null
     * @throws \Exception
     */
    public static function getAdminTpl()
    {
        $tpl = new \FastTemplate(
            Config::getOldTplFolder() . '_admin/_tpl',
            Config::getCacheFolder() . '_admin/_inc/cache/tpl_cache.php',
            \FastTemplate::PLACE_ADMIN
        );

        $tpl->strict()
            ->set_default_folder('')
            ->rebuild_cache()
            ->load_cache()
            ->define('`_index', [
                'index',
                'expand_collapse_block',
                'footer',
                'head_includes',
                'navy',
            ]);

        return $tpl;
    }

    private function initTpl()
    {
        $this->tpl = static::getAdminTpl();

        return $this;
    }

    public function setForceShowExpandCollapse($state)
    {
        $this->forceShowExpandCollapse = $state;

        return $this;
    }

    public function getTable()
    {
        if ($this->getPage() && !$this->table && $this->getPage()->hasTable()) {
            return $this->getPage()->getTable();
        }

        return $this->table;
    }

    public function setTable($table)
    {
        $this->table = $table;

        return $this;
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

    public function getModule()
    {
        return $this->module;
    }

    public function getMethod()
    {
        return strtolower($this->method);
    }

    public function getRefinedMethod()
    {
        if ($this->getModule() === 'login') {
            return '';
        }

        $method = $this->getMethod();

        if ($method === 'form') {
            $method = $this->getId() ? 'edit' : 'add';
        }

        return $method;
    }

    /** @deprecated */
    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public static function currentLanguage()
    {
        return static::$_language;
    }

    public function getSiteTitle()
    {
        return Config::getSiteTitle() ?: $this->siteTitle;
    }

    public function getSiteLogo()
    {
        return Config::getSiteLogo();
    }

    public static function getOldSchoolPath($module, $method = 'list')
    {
        return $module . ($method == 'list' ? '' : '_' . $method);
    }

    public static function getPageUri($module, $method = '', $params = [])
    {
        $methodPart = in_array($method, ['', 'list']) ? '' : '/' . $method;

        if ($method == 'form' && isset($params['id'])) {
            $idPart = $params['id'] . '/';

            unset($params['id']);
        } else {
            $idPart = '';
        }

        $queryPart = $params
            ? '?' . (is_array($params) ? http_build_query($params) : $params)
            : '';

        return '/' .
            self::getSubFolder() .
            '/' .
            $module .
            $methodPart .
            '/' .
            $idPart .
            $queryPart;
    }

    public function getCurrentPageUri($method = 'list', $paramsAr = [])
    {
        return self::getPageUri($this->getModule(), $method, $paramsAr);
    }

    public function getPageTitle()
    {
        return strip_tags($this->caption ?? '') .
            ': ' .
            $this->getSiteTitle() .
            ' admin';
    }

    public function isColonNeededInFormTitles()
    {
        return true;
    }

    public function expandCollapseBlockNeeded()
    {
        return (in_array($this->getTable(), ['content', 'categories', 'orders']) &&
            $this->getMethod() === 'list') ||
            $this->forceShowExpandCollapse;
    }

    public function footerNeeded()
    {
        return true;
    }

    protected function printContentPage()
    {
        if ($this->currentMethodExists()) {
            $this->load();
        } else {
            global $db, $tn_folder, $tn2_folder, $files_folder;

            $fn =
                \diPaths::fileSystem() .
                self::getSubFolder() .
                "/$this->path/$this->filename";

            if (!is_file($fn)) {
                throw HttpException::notFound(
                    static::getVocabulary('error.page_not_found')
                );
            }

            ob_start();

            require $fn;

            $this->getTpl()->assign([
                'PAGE' => ob_get_contents(),
            ]);

            $this->getTwig()->assign([
                'PAGE' => ob_get_contents(),
            ]);

            ob_end_clean();
        }

        return $this;
    }

    public function beforeRender()
    {
        return $this;
    }

    public function afterRender()
    {
        return $this;
    }

    public function load()
    {
        /** @var BasePage $class */
        $class = self::getModuleClassName($this->getModule());

        $this->setAdminPage($class::create($this));

        return $this;
    }

    public function setAdminPage(BasePage $page)
    {
        $this->adminPage = $page;

        return $this;
    }

    protected function printCaption()
    {
        $this->getTpl()->assign([
            'CAPTION' => $this->caption,
            'CAPTION_BUTTONS' => $this->caption->getButtons(),
        ]);

        return $this;
    }

    protected function getStartPath()
    {
        if (!$this->adminUser || !$this->adminUser->authorized()) {
            return null;
        }

        return $this->getStartModule();
    }

    public function getAdminLevel()
    {
        return $this->adminUser->authorizedForSetup()
            ? Level::root
            : $this->getAdminModel()->getLevel();
    }

    public function getStartModule()
    {
        return $this->getStartModuleByAdminLevel($this->getAdminLevel());
    }

    protected function getStartModuleByAdminLevel($level)
    {
        switch ($level) {
            case Level::root:
                return 'content';

            default:
                return null;
        }
    }

    public function getStartMethod($module)
    {
        return $this->getStartMethodByAdminLevel($module, $this->getAdminLevel());
    }

    protected function getStartMethodByAdminLevel($module, $level)
    {
        return static::DEFAULT_METHOD;
    }

    private function readParams()
    {
        $this->setId(
            StringHelper::in(\diRequest::request('id', $this->getUriParam(2, '')))
        );

        return $this;
    }

    public function getUriParam($idx, $defaultValue = null, $type = null)
    {
        return ArrayHelper::get($this->uriParams, $idx, $defaultValue, $type);
    }

    private function readUri()
    {
        $m = \diRequest::requestUri();
        $x = mb_strpos($m, '?');
        if ($x !== false) {
            $m = mb_substr($m, 0, $x);
        }

        $this->uriParams = array_map('addslashes', explode('/', trim($m, '/')));

        if (
            \diLib::getSubFolder() &&
            isset($this->uriParams[0]) &&
            $this->uriParams[0] == \diLib::getSubFolder()
        ) {
            array_splice(
                $this->uriParams,
                0,
                count(explode('/', \diLib::getSubFolder()))
            );
        }

        if ($this->uriParams && $this->uriParams[0] == self::SUBFOLDER) {
            array_splice($this->uriParams, 0, 1);
        }

        $this->module = $this->getUriParam(0, $this->getStartPath());
        $this->method = $this->getUriParam(1, $this->getStartMethod($this->module));

        // back compatibility
        if (\diRequest::get('path')) {
            $this->path = \diRequest::get('path', '');
            $this->filename = 'content.php';

            if (substr($this->path, strlen($this->path) - 5) == '_form') {
                $this->path = substr($this->path, 0, strlen($this->path) - 5);
                $this->filename = 'form.php';
            }

            $this->module = $this->path;
            $this->method = $this->filename == 'form.php' ? 'form' : 'list';
        } else {
            $this->path = $this->module;
            $this->filename = $this->method == 'form' ? 'form.php' : 'content.php';
        }
        //

        return $this;
    }

    // todo: refactor!
    // use snowsh system may be
    private function checkRights()
    {
        if ($this->adminUser->reallyAuthorized()) {
            $accessGranted = false;

            // old school style
            $pathForCheck = $this->module;

            if ($this->method != 'list') {
                $m = in_array($this->method, ['form', 'submit'])
                    ? 'form'
                    : $this->method;

                $pathForCheck .= '_' . $m;
            }

            foreach ($this->getAdminMenuFullTree() as $groupTitle => $groupOpts) {
                if (
                    !empty($groupOpts['paths']) &&
                    is_array($groupOpts['paths']) &&
                    in_array($pathForCheck, $groupOpts['paths']) &&
                    $this->hasAccess($groupOpts)
                ) {
                    $accessGranted = true;

                    break;
                }
            }

            if (!$accessGranted) {
                $this->module = $this->path = $this->getStartPath();
                $this->filename = 'content.php';
            }
        }

        if (!$this->module || !$this->adminUser->authorized()) {
            $this->module = $this->path = 'login';
            $this->method = 'form';
        }

        return $this;
    }

    /**
     * @return int|string
     * @throws \Exception
     */
    public function getWysiwygVendor()
    {
        return $this->wysiwygVendor;
    }

    protected function getWysiwygTemplateName($alias)
    {
        return 'admin/wysiwyg/' . $alias;
    }

    private function printWysiwygHeadScript()
    {
        $script = '';

        if ($alias = Form::getWysiwygAlias($this->getWysiwygVendor())) {
            $this->getTwig()->render(
                $this->getWysiwygTemplateName($alias),
                'wysiwyg_head_script',
                [
                    'needed' => [
                        'rfm' => $this->responsiveFileManagerNeeded(),
                    ],
                    'extra_wysiwyg_settings' => $this->getExtraWysiwygSettings(),
                ]
            );

            $script = $this->getTwig()->getAssigned('wysiwyg_head_script');
        }

        $this->getTpl()->assign('wysiwyg_head_script', $script);

        return $this;
    }

    protected function getExtraWysiwygSettings()
    {
        return '';
    }

    protected function responsiveFileManagerNeeded()
    {
        return true;
    }

    public function printHead()
    {
        $head = $this->getPrintedHead();

        if (!$head) {
            $this->printWysiwygHeadScript();

            $head = $this->getTwig()->parse('admin/_index/head');
        }

        $this->getTpl()->assign([
            'head' => $head,
        ]);

        return $this;
    }

    private function isModuleAccessible($moduleName)
    {
        $super = in_array($moduleName, $this->superUserModules);
        $local = in_array($moduleName, $this->localUserModules);
        $isDebugMode = \diCurrentCMS::debugMode();

        return (!$super || $this->isAdminSuper() || $isDebugMode) &&
            (!$local || $isDebugMode);
    }

    protected static function menuAdd()
    {
        $ar = func_get_args();
        $result = [];

        foreach ($ar as $a) {
            $result = array_merge_recursive($result, $a);
        }

        if (is_array($result['isVisible'])) {
            $result['isVisible'] = current($result['isVisible']);
        }

        if (is_array($result['super'])) {
            $result['super'] = current($result['super']);
        }

        return $result;
    }

    protected function getAdminMenuFullTree()
    {
        return extend(
            $this->getAdminMenuMainTree(),
            $this->getAdminMenuTechTree(),
            $this->getAdminMenuDatabaseTree(),
            $this->getAdminMenuSettingsTree()
        );
    }

    protected function getAdminMenuMainTree()
    {
        global $admin_left_menu;

        return $admin_left_menu;
    }

    protected static function getAdminMenuRow($moduleName, $moduleOptions = [])
    {
        $names = is_array($moduleName)
            ? $moduleName
            : [$moduleName => $moduleOptions];

        $ar = [
            'items' => [],
            'permissions' => [],
            'isVisible' => true,
            'paths' => [],
        ];

        foreach ($names as $moduleName => $opts) {
            $options = extend(
                [
                    'permissions' => [Level::root],
                    'isVisible' => true, // boolean or callback
                    'super' => false,
                    'showList' => true,
                    'showForm' => true,
                    'listTitle' => static::getVocabulary('menu.list'),
                    'formTitle' => static::getVocabulary('menu.add'),
                    'listTitleSuffix' => '',
                    'formTitleSuffix' => '',
                    'extraPaths' => [],
                    'prefixRows' => [],
                    'suffixRows' => [],
                ],
                $moduleOptions,
                $opts
            );

            $ar['permissions'] = $options['permissions'];
            $ar['isVisible'] = $options['isVisible'];
            $ar['super'] = $options['super'];
            $ar['paths'] = array_merge(
                $ar['paths'],
                [$moduleName, $moduleName . '_form'],
                $options['extraPaths']
            );

            if ($options['showList']) {
                $ar['items'][
                    $options['listTitle'] . ' ' . $options['listTitleSuffix']
                ] = [
                    'module' => $moduleName,
                ];
            }

            if ($options['showForm']) {
                $ar['items'][
                    $options['formTitle'] . ' ' . $options['formTitleSuffix']
                ] = [
                    'module' => $moduleName . '/form',
                ];
            }

            if ($options['prefixRows']) {
                $ar['items'] = array_merge($options['prefixRows'], $ar['items']);
            }

            if ($options['suffixRows']) {
                $ar['items'] = array_merge($ar['items'], $options['suffixRows']);
            }
        }

        return $ar;
    }

    protected function getAdminMenuTechTree()
    {
        return [
            static::getVocabulary('menu.emails') => $this->getAdminMenuRow(
                'mail_queue'
            ),
            static::getVocabulary('menu.admins') => $this->getAdminMenuRow('admins'),
        ];
    }

    protected function getAdminMenuDatabaseTree()
    {
        return [
            static::getVocabulary('menu.db') => [
                'items' => [
                    static::getVocabulary('menu.db.dump') => [
                        'module' => 'dump',
                    ],
                    static::getVocabulary('menu.db.migrations.log') => [
                        'module' => 'migrations/log',
                    ],
                    static::getVocabulary('menu.db.migrations.list') => [
                        'module' => 'migrations',
                    ],
                    static::getVocabulary('menu.db.migrations.create') => [
                        'module' => 'migrations/form',
                    ],
                    static::getVocabulary('menu.db.models.create') => [
                        'module' => 'di_lib_models/form',
                    ],
                    static::getVocabulary('menu.db.admin_pages.create') => [
                        'module' => 'di_lib_admin_pages/form',
                    ],
                ],
                'permissions' => [Level::root],
                'paths' => [
                    'dump',
                    'migrations',
                    'migrations_form',
                    'migrations_log',
                    'di_lib_models_form',
                    'di_lib_admin_pages_form',
                ],
            ],
        ];
    }

    protected function getAdminMenuSettingsTree()
    {
        return [
            $this->getVocabulary('menu.settings') => [
                'items' => [
                    '<b>' . $this->getVocabulary('menu.edit_settings') . '</b>' => [
                        'module' => 'configuration',
                    ],
                    $this->getVocabulary('menu.rebuild_cache') .
                    '<br />' .
                    CMS::getTemplatesCacheModificationDateTime() => [
                        'link' =>
                            \diLib::getAdminWorkerPath('cache', 'rebuild') .
                            '?back=' .
                            urlencode(\diRequest::requestUri()),
                    ],
                ],
                'permissions' => [Level::root],
                'paths' => ['configuration'],
            ],
        ];
    }

    protected function hasAccess($groupOpts)
    {
        if ($this->adminUser->authorizedForSetup()) {
            return true;
        }

        $hasPermissions = in_array(
            $this->getAdminModel()->getLevel(),
            $groupOpts['permissions']
        );
        $super = empty($groupOpts['super']) || $this->isAdminSuper();
        $visible = isset($groupOpts['isVisible'])
            ? (is_callable($groupOpts['isVisible'])
                ? $groupOpts['isVisible']()
                : $groupOpts['isVisible'])
            : true;

        return $hasPermissions && $super && $visible;
    }

    public function printMainMenu()
    {
        if (!$this->adminUser->authorized()) {
            return $this;
        }

        $rows = [];
        $visibleIds = explode(
            ',',
            \diRequest::cookie('admin_visible_left_menu_ids', '')
        );
        $i = 0;

        foreach ($this->getAdminMenuFullTree() as $groupTitle => $groupOpts) {
            if (!$this->hasAccess($groupOpts)) {
                continue;
            }

            $i++;
            $selected = false;
            $subRows = [];

            foreach ($groupOpts['items'] ?? [] as $itemTitle => $item) {
                if (is_scalar($item)) {
                    $item = [
                        'module' => $item,
                    ];
                }

                $item = extend(
                    [
                        'link' => '',
                        'link_suffix' => '',
                        'module' => null,
                    ],
                    $item
                );

                if (empty($item['link']) && !empty($item['module'])) {
                    if (!$this->isModuleAccessible($item['module'])) {
                        continue;
                    }

                    $item['link'] = self::getPageUri($item['module']);
                }

                if (
                    $this->hasPage() &&
                    in_array($this->getPage()->getBasePath(), [
                        $item['module'],
                        preg_replace('#/.+$#', '', $item['module'] ?? ''),
                    ])
                ) {
                    $selected = true;
                }

                $subRows[] = extend($item, [
                    'title' => $itemTitle,
                    'href' => $item['link'] . $item['link_suffix'],
                ]);
            }

            $rows[] = [
                'id' => $i,
                'title' => $groupTitle,
                'href' => $groupOpts['href'] ?? null,
                'target' => $groupOpts['target'] ?? null,
                'state' => in_array($i, $visibleIds),
                'selected' => $selected,
                'subRows' => $subRows,
            ];
        }

        $this->getTwig()->assign([
            'left_menu_rows' => $rows,
        ]);

        return $this;
    }

    public static function getClassMethodName($method, $prefix = '')
    {
        switch ($method) {
            case 'submit':
                $m = $method . '_form';
                break;

            default:
                $m = 'render_' . $method;
                break;
        }

        if ($prefix) {
            $prefix .= '_';
        }

        return camelize($prefix . $m);
    }

    public static function getModuleClassName($module)
    {
        return \diLib::getClassNameFor($module, \diLib::ADMIN_PAGE);
    }

    public function moduleExists($module)
    {
        return \diLib::exists(self::getModuleClassName($module));
    }

    public function methodExists($module, $method)
    {
        $class = self::getModuleClassName($module);
        $m = self::getClassMethodName($method);

        if ($this->moduleExists($module)) {
            return method_exists($class, $m);
        }

        return false;
    }

    public function currentModuleExists()
    {
        return $this->moduleExists($this->getModule());
    }

    public function currentMethodExists()
    {
        return $this->methodExists($this->getModule(), $this->getMethod());
    }

    /**
     * @return boolean
     */
    public function hasHeadPrinter()
    {
        return $this->headPrinter && is_callable($this->headPrinter);
    }

    /**
     * @return callable|null
     */
    public function getHeadPrinter()
    {
        return $this->headPrinter;
    }

    /**
     * @param null $headPrinter
     * @return $this
     */
    public function setHeadPrinter($headPrinter)
    {
        $this->headPrinter = $headPrinter;

        return $this;
    }

    public function getPrintedHead()
    {
        if ($this->hasHeadPrinter()) {
            $cb = $this->getHeadPrinter();

            return $cb($this);
        }

        return null;
    }
    public function getResponse()
    {
        if (!$this->response) {
            $this->response = new Response();
        }

        return $this->response;
    }

    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->getResponse()->getResponseCode();
    }

    /**
     * @return bool
     */
    public function isResponseCode($code)
    {
        return $this->getResponse()->isResponseCode($code);
    }

    /**
     * @param int $responseCode
     * @return $this
     */
    public function setResponseCode($responseCode)
    {
        $this->getResponse()->setResponseCode($responseCode);

        return $this;
    }
}
