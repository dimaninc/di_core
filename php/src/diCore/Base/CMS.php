<?php

namespace diCore\Base;

use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Entity\Content\Model;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use diCore\Data\Http\HttpCode;

abstract class CMS
{
	const TPL_DIR = "tpl";
	const TPL_CACHE_PHP = "_cfg/cache/tpl_cache.php";
	const TABLES_CONTENT_CACHE_PHP = "_cfg/cache/table_content.php";
	const TABLES_CONTENT_CLEAN_TITLES_PHP = "_cfg/cache/table_content_ct_ar.php";

	const TEMPLATE_ENGINE_TWIG = 1;
	const TEMPLATE_ENGINE_FASTTEMPLATE = 2;

	const MAIN_TEMPLATE_ENGINE = self::TEMPLATE_ENGINE_FASTTEMPLATE;

	/** @var \FastTemplate */
	public $tpl;

	/**
	 * @var \diTwig
	 */
	private $Twig;
	private $twigBasicsAssigned = false;
	private $indexTemplateName = 'index';

	/** @var \diDB */
	protected $db;

	/** @var \diModule */
	private $module;

	/**
	 * @var \diContentFamily
	 */
	private $ContentFamily;

	/**
	 * @var BreadCrumbs
	 */
	private $BreadCrumbs;

	/**
	 * @var \diCustomAuth|\diAuth
	 */
	private $Auth;

	/**
	 * Redefine as True if Auth needed
	 * @var bool
	 */
	protected $authUsed = false;

	private $fileChmod = 0666;
	private $dirChmod = 0777;

	protected $protocol = 'http';

	/**
	 * @var \diDeviceDetector
	 */
	protected $device;

	/**
	 * Domains to detect 'dev' environment
	 * @var array
	 */
	public static $devDomains = [];

	/**
	 * Domains to detect 'stage' environment
	 * @var array
	 */
	public static $stageDomains = [];

	const ENV_DEV = 1;
	const ENV_STAGE = 2;
	const ENV_PROD = 3;

	protected static $skipGetParams = [
		\diPagesNavy::PAGE_PARAM,
		\diComments::PAGE_PARAM,

		// yandex.direct
		'yclid',

		// facebook
		'_openstat',

		// viber/google
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	];
	protected static $customSkipGetParams = [];

	/**
	 * @var int
	 * Todo: make response object
	 */
	protected $responseCode = HttpCode::OK;

	private $routes = [];
	private $origRoutes = [];
	public $m1, $m2, $m3, $m4, $m5;

	public $tables;
	public $ct_ar = []; // clean titles ar ["type" => "clean_title"]
	protected $default_page_type = "home";
	public $safe_swf_idx = 0;
	public $print_share_block = true;
	public $comments_form_prefix = "";

	private $commentsBlockNeeded = true;
	private $forceCommentsBlockNeeded = false;

	static public $possibleLanguages = [
		"ru",
	];
	public static $defaultLanguage = "ru";
	protected static $forceLanguage = null;
	public $language = "ru";

	public $language_href_prefix = "";

	public $content_table = "content";
	public $news_table = "news";

	protected $fieldsExcludedFromCache = [];

	// language stuff
	static public $field_names_ar = [
		"title",
		"caption",
		"html_title",
		"html_keywords",
		"html_description",
		"short_content",
		"content",
		"content2",
		"description",
		"tag",
		"name",
		"href",
		"client",
		"type",
		"model",
		"position",
		"value",
		"to_user_prefix",
	];

	const META_FIELD_PREFIX = "meta_";
	const META_FIELD_PREFIX_OLD = "html_";
	const OPEN_GRAPH_FIELD_PREFIX = "open_graph_";

	protected $metaFields = [];
	protected $openGraphFields = [];

	/** @var  \diModel */
	protected $mainTarget;

	/** @deprecated */
	public $title_var;
	/** @deprecated */
	public $content_var;
	/** @deprecated */
	public $short_content_var;
	/** @deprecated */
	public $html_title_var;
	/** @deprecated */
	public $html_description_var;
	/** @deprecated */
	public $html_keywords_var;
	/** @deprecated */
	public $tag_var;

	public static $possible_toggle_fields_ar = [
		"active",
		'en_active',
		"activated",
		"recommended",
		"to_show_content",
		"top",
		"visible",
		"visible_left",
		"visible_right",
		"visible_logged_in",
		"visible_top",
		"visible_bottom",
		"visible_2nd_bottom",
		'en_top',
		'en_visible',
		"en_visible_left",
		"en_visible_right",
		"en_visible_logged_in",
		"en_visible_top",
		"en_visible_bottom",
		"en_visible_2nd_bottom",
		"opened",
		"check_before",
		"sold",
		"accepted",
		"moderated",
	];

	// paths
	public $tpl_dir;
	public $tpl_cache_php;
	public $tables_cache_fn_ar = [];
	public $ct_cache_fn_ar = [];

	public function __construct($tpl_dir = false, $tpl_cache_php = false, $tables_cache_fn_ar = false, $ct_cache_fn_ar = false)
	{
		global $db;

		$this->db = $db;

		if ($this->authUsed)
		{
			$this->initAuth();
		}

		$this->ContentFamily = \diContentFamily::create($this);
		$this->BreadCrumbs = BreadCrumbs::create($this);

		$this->tables = [
			$this->content_table => [],
		];

		$this->tpl_dir = $tpl_dir ?: Config::getOldTplFolder() . static::TPL_DIR;
		$this->tpl_cache_php = $tpl_cache_php ?: Config::getOldTplFolder() . static::TPL_CACHE_PHP;

		$this->tables_cache_fn_ar[$this->content_table] = $tables_cache_fn_ar ?: Config::getCacheFolder() . static::TABLES_CONTENT_CACHE_PHP;
		$this->ct_cache_fn_ar[$this->content_table] = $ct_cache_fn_ar ?: Config::getCacheFolder() . static::TABLES_CONTENT_CLEAN_TITLES_PHP;

		$this->protocol = \diRequest::protocol();
	}

	/**
	 * @return $this
	 */
	abstract function define_templates();

	/**
	 * @return $this
	 */
	abstract public function go();

	public static function fast_lite_create($options = [])
	{
		$options = extend([
			'language' => null,
		], $options);

		$class = \diLib::getChildClass(self::class);

		/** @var CMS $Z */
		$Z = new $class();
		$Z->setLanguage($options['language'] ?: static::getBrowserLanguage());
		$Z->load_content_table_cache();
		$Z->init_tpl();
		$Z->populateRoutes();
		$Z->initTplDefines();
		$Z->define_templates();
		$Z->define_language_vars();

		$Z->ct_ar = static::getCleanTitlesAr();
		$Z->assign_ct_ar();

		return $Z;
	}

	public static function getBrowserLanguage($default = "ru")
	{
		$l = \diRequest::cookie("lang", "") ?: mb_strtolower(mb_substr(\diRequest::server("HTTP_ACCEPT_LANGUAGE", ""), 0, 2));

		return in_array($l, static::$possibleLanguages) ? $l : $default;
	}

	public function getLanguageHrefPrefix()
	{
		return $this->language_href_prefix;
	}

	public static function getCleanTitlesAr()
	{
		global $Z, $z_ct_ar;

		if (!empty($Z))
		{
			return $Z->ct_ar;
		}
		elseif (!empty($z_ct_ar))
		{
			return $z_ct_ar;
		}
		else
		{
			$z_ct_ar = [];
			include Config::getConfigurationFolder() . '/' . static::TABLES_CONTENT_CLEAN_TITLES_PHP;

			return $z_ct_ar;
		}
	}

	public static function getEnvironment()
	{
		$domain = \diRequest::domain() ?: Config::getMainDomain();

		if (in_array($domain, static::$devDomains))
		{
			return self::ENV_DEV;
		}
		elseif (in_array($domain, static::$stageDomains))
		{
			return self::ENV_STAGE;
		}

		return self::ENV_PROD;
	}

	public static function debugMode()
	{
		return static::getEnvironment() == self::ENV_DEV;
	}

	public static function ignoreCaches()
	{
		return false;
	}

	public static function isHardDebug()
	{
		return !!\diRequest::cookie("test");
	}

	public static function extendFieldsWithAllLanguages($fields)
	{
		if (!is_array($fields))
		{
			$fields = [$fields];
		}

		$intFields = [];

		foreach ($fields as $field)
		{
			foreach (static::$possibleLanguages as $language)
			{
				if ($language == static::$defaultLanguage)
				{
					continue;
				}

				$intFields[] = $language . '_' . $field;
			}
		}

		return array_merge($fields, $intFields);
	}

	public static function getTemplatesCacheModificationDateTime()
	{
		$fn = Config::getCacheFolder() . '/' . static::TPL_CACHE_PHP;
		$ft = is_file($fn) ? filemtime($fn) : null;

		return $ft
			? \diDateTime::format('d.m.Y H:i', $ft)
			: '---';
	}

	/**
	 * @return Model
	 */
	public function getContentModel()
	{
		return $this->ContentFamily->getModel();
	}

	/**
	 * @return \diContentFamily
	 */
	public function getContentFamily()
	{
		return $this->ContentFamily;
	}

	/**
	 * @return BreadCrumbs
	 */
	public function getBreadCrumbs()
	{
		return $this->BreadCrumbs;
	}

	/**
	 * @return array
	 */
	public function getCachedContentCollection()
	{
		return $this->tables["content"];
	}

	public function getCachedContentCollectionByType()
	{
		$contentByType = [];

		/**
		 * @var Model $m
		 */
		foreach ($this->getCachedContentCollection() as $m)
		{
			$contentByType[$m->getType()] = $m;
		}

		return $contentByType;
	}

	/** * @deprecated */
	public static function get_ct_ar()
	{
		return static::getCleanTitlesAr();
	}

	/**
	 * @return \diDB
	 */
	public function getDb()
	{
		return $this->db;
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
		if (!$this->Twig)
		{
			$this
				->initTwig();
		}

		$this
			->assignTwigBasics();

		return $this->Twig;
	}

	/**
	 * @return \diAuth
	 */
	public function getAuth()
	{
		return $this->Auth;
	}

	protected function initAuth()
	{
		$this->Auth = \diAuth::create();

		return $this;
	}

	/**
	 * @return CMS
	 */
	protected function printAuthStuff()
	{
		if ($this->authUsed)
		{
			$this->getAuth()->assignTemplateVariables($this->getTpl());
		}

		return $this;
	}

	public function getLanguage()
	{
		return $this->language;
	}

	public function setLanguage($language)
	{
		$this->language = $language;

		return $this;
	}

	public static function setForceLanguage($language)
	{
		static::$forceLanguage = $language;
	}

	public static function currentLanguage()
	{
		/** @var CMS $Z */
		global $Z;

		if (static::$forceLanguage)
		{
			return static::$forceLanguage;
		}
		elseif (
			!empty($GLOBALS["CURRENT_LANGUAGE"]) &&
			in_array($GLOBALS["CURRENT_LANGUAGE"], \diCurrentCMS::$possibleLanguages) &&
			$GLOBALS["CURRENT_LANGUAGE"] != static::$defaultLanguage
		) {
			$language = $GLOBALS["CURRENT_LANGUAGE"];
		}
		elseif (!empty($Z))
		{
			$language = $Z->getLanguage();
		}
		else
		{
			$language = static::$defaultLanguage;
		}

		return $language;
	}

	public static function isFieldToggle($field)
	{
		return in_array($field, static::$possible_toggle_fields_ar);
	}

	/** @deprecated */
	public function print_ad_block($block_id, $token)
	{
		return \diAds::printBlock($block_id, $token);
	}

	/** @deprecated */
	public function incut_ad_blocks($content)
	{
		return \diAds::incutBlocks($content);
	}

	/** @deprecated */
	public function print_banners()
	{
		\diBanners::printAll();

		return $this;
	}

	/** @deprecated */
	public function parse_block_if_not_empty($block_name, $content_name)
	{
		return $this->getTpl()->parse_if_not_empty($block_name, $content_name);
	}

	public function checkModuleAccessibility($module)
	{
		$n = !\diContentTypes::exists($module) || \diContentTypes::getParam($module, "logged_in");

		if ($n && !$this->getAuth()->authorized())
		{
			$this->errorNotAuthorized();

			return false;
		}

		return true;
	}

	public function getModule()
	{
		return $this->module;
	}

	public function getModuleName()
	{
		return $this->getContentModel()->getType();
	}

	public static function getModuleClassName($module)
	{
		return \diLib::getClassNameFor($module, \diLib::MODULE);
	}

	public static function moduleExists($module)
	{
		return \diLib::exists(self::getModuleClassName($module));
	}

	private function loadModule($module = null)
	{
		$module = $module ?: $this->getModuleName();

		/** @var \diModule $class */
		$class = static::getModuleClassName($module);

		$this->module = $class::create($this);

		return $this;
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function renderPage()
	{
		if (!$this->beforeRenderPage())
		{
			return $this;
		}

		$moduleName = $this->getModuleName();

		$this->checkModuleAccessibility($moduleName);

		if (static::moduleExists($moduleName))
		{
			$this->loadModule();
		}
		// back compatibility
		elseif (!is_file("include/{$moduleName}.php"))
		{
			$this->loadModule("user");
		}
		else
		{
			$Z = $this;
			$db = $this->getDb();
			$tpl = $this->getTpl();

			include("include/{$moduleName}.php");

			$this->beforeParsePage();

			$this->getTpl()->parse("page");
		}

		$this->afterRenderPage();

		return $this;
	}

	protected function assignMetaVariables()
	{
		if (!$this->getMeta('title'))
		{
			$this->setMeta($this->getContentModel()->localized('title'));
		}

		if (!$this->getMeta('description'))
		{
			$this->setMeta($this->getContentModel()->localized('description') ?: $this->getMeta('title'), 'description');
		}

		if (!$this->hasHeaderImage())
		{
			if ($this->getContentModel()->localized('pic'))
			{
				$this
					->setHeaderImage(
						$this->getContentModel()->getPicsFolder() . $this->getContentModel()->localized('pic'), null,
						$this->getContentModel()->localized('pic_w'),
						$this->getContentModel()->localized('pic_h')
					);
			}
			elseif (\diConfiguration::exists('smm_logo'))
			{
				$this->setHeaderImage(\diConfiguration::getFilename('smm_logo'), null,
					\diConfiguration::get('smm_logo', 'img_width'),
					\diConfiguration::get('smm_logo', 'img_height')
				);
			}
		}

		return $this;
	}

	/**
	 * @param \diModel|int $targetType
	 * @param int|string|null $targetId
	 * @param string|null $token
	 * @return $this
	 */
	public function printCommentsBlock($targetType, $targetId = null, $token = "COMMENTS_BLOCK")
	{
		if ($targetType instanceof \diModel)
		{
			$token = $targetId ?: $token;
			$Comments = \diComments::create($targetType);
		}
		else
		{
			$Comments = \diComments::create($targetType, $targetId);
		}

		$Comments
			->setTpl($this->getTpl())
			->setTwig($this->getTwig());

		$this->getTpl()->assign([
			$token => $Comments->getBlockHtml(),
		]);

		return $this;
	}

	/**
	 * @param boolean $commentsBlockNeeded
	 */
	public function setCommentsBlockNeeded($commentsBlockNeeded)
	{
		$this->commentsBlockNeeded = $commentsBlockNeeded;

		return $this;
	}

	/**
	 * @param boolean $forceCommentsBlockNeeded
	 */
	public function setForceCommentsBlockNeeded($forceCommentsBlockNeeded)
	{
		$this->forceCommentsBlockNeeded = $forceCommentsBlockNeeded;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isCommentsBlockNeeded()
	{
		return $this->commentsBlockNeeded;
	}

	/**
	 * @return boolean
	 */
	public function isForceCommentsBlockNeeded()
	{
		return $this->forceCommentsBlockNeeded;
	}

	protected function isCommentsBlockPrintNeeded()
	{
		return $this->isForceCommentsBlockNeeded() ||
			($this->getContentModel()->hasCommentsEnabled() && $this->isCommentsBlockNeeded());
	}

	protected function printCommentsForPage()
	{
		if ($this->isCommentsBlockPrintNeeded())
		{
			$this->printCommentsBlock($this->getMainTarget(), 'PAGE_COMMENTS_BLOCK');
		}

		return $this;
	}

	protected function openGraphNeeded()
	{
		return $this->responseCode != HttpCode::NOT_FOUND;
	}

	protected function languageAlternatesNeeded()
	{
		return false;
	}

	protected function countersNeeded()
	{
		return !static::debugMode();
	}

	protected function shareBlockNeeded()
	{
		return
			$this->responseCode == HttpCode::OK &&
			$this->print_share_block &&
			!\diContentTypes::getParam($this->getContentModel()->getType(), "logged_in");
	}

	protected function htmlBaseNeeded()
	{
		return true;
	}

	protected function printSearchStuff()
	{
		$query = trim(StringHelper::out(\diRequest::get('q', '')));

		$this->getTpl()->assign([
			"SEARCH_Q" => $query,
		]);

		$this->getTwig()
			->assign([
				'search' => [
					'query' => $query,
				],
			]);

		return $this;
	}

	protected function processShareBlockHref($href)
	{
		return $href;
	}

	public static function templateEngineIsTwig()
	{
		return static::MAIN_TEMPLATE_ENGINE === self::TEMPLATE_ENGINE_TWIG;
	}

	public static function templateEngineIsFastTemplate()
	{
		return static::MAIN_TEMPLATE_ENGINE === self::TEMPLATE_ENGINE_FASTTEMPLATE;
	}

	protected function printShareBlock()
	{
		if ($this->shareBlockNeeded()) // && static::templateEngineIsFastTemplate()
		{
			$title0 = $this->getMeta("title");
			$content0 = $this->getMeta("description");

			$title = StringHelper::out($title0);
			$content = StringHelper::out($content0);
			$href = "$this->protocol://{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}";
			$href = $this->processShareBlockHref($href);

			if (!$content)
			{
				$content = $title;
				$content0 = $title0;
			}

			$img = $this->getHeaderImage();
			$content_ending_for_lj = "<div><img src=\"$img\" /></div>";

			$this->getTpl()
				->assign([
					"TITLE" => $title,
					"TITLE_ESCAPED" => urlencode($title),
					"TITLE0_ESCAPED" => urlencode($title0),

					"CONTENT" => $content,
					"CONTENT_ESCAPED" => urlencode($content),
					"CONTENT0_ESCAPED" => urlencode($content0),
					//"HREF_FOR_LJ" => urlencode("<a href=$href>").$content.$content_ending_for_lj.urlencode("</a>"), // str_out(iconv('cp1251','utf-8',$this->tpl->get_assigned("PAGE_DESCRIPTION")))
					"HREF_FOR_LJ" => urlencode("<a href=$href>$content0$content_ending_for_lj</a>"),
					"HREF" => $href,
					"HREF_ESCAPED" => urlencode($href),
					"HREF_ESCAPED2" => urlencode($href . (preg_match('/[&?]/', $href) ? "&" : "")),

					//"HREF_TINY" => urlencode(get_tiny_url("http://{$_SERVER["SERVER_NAME"]}{$_SERVER["REQUEST_URI"]}")),
				], "SHARE_")
				->process("PAGE_SHARE_BLOCK", "share_block");
		}

		return $this;
	}

	protected function printRelatedStuff()
	{
		$this
			->printCommentsForPage()
			->printSearchStuff()
			->printShareBlock();

		return $this;
	}

	protected function beforeRenderPage()
	{
		return true;
	}

	public function beforeParsePage()
	{
		return $this->printBreadCrumbs();
	}

	protected function afterRenderPage()
	{
		$this
			->printRelatedStuff()
			->assignMetaVariables()
			->checkOpenGraphVariables();

		return $this;
	}

	function set_content_table($table, $tables_cache_fn_ar = false)
	{
		$this->content_table = $table;

		$this->tables[$this->content_table] = [];
		$this->tables_cache_fn_ar[$this->content_table] = $tables_cache_fn_ar ? $tables_cache_fn_ar : $this->tables_cache_fn_ar["content"];

		return $this;
	}

	/**
	 * @param null $default_page_type
	 * @param null $defaultLanguage
	 * @return \diCurrentCMS
	 */
	public function start($default_page_type = null, $defaultLanguage = null)
	{
		if ($default_page_type)
		{
			$this->default_page_type = $default_page_type;
		}

		if ($defaultLanguage)
		{
			static::$defaultLanguage = $defaultLanguage;
		}

		$this->language = static::$defaultLanguage;

		$this
			->load_content_table_cache(false)
			->init_tpl()
			->populateRoutes()
			->initTplDefines();

		return $this;
	}

	/**
	 * Override this. Parse head, counters and other stuff here
	 *
	 * @return $this
	 */
	protected function finalParse()
	{
		return $this;
	}

	/** @deprecated */
	protected function getFastTemplateFinalPage()
	{
		return $this->getTpl()
			->parse("index");
	}

	protected function getNeededSwitches()
	{
		return [
			'open_graph' => $this->openGraphNeeded(),
			'counters' => $this->countersNeeded(),
			'share_block' => $this->shareBlockNeeded(),
			'comments' => $this->isCommentsBlockPrintNeeded(),
			'html_base' => $this->htmlBaseNeeded(),
			'language_alternates' => $this->languageAlternatesNeeded(),
		];
	}

	protected function getIndexTemplateName()
	{
		return $this->indexTemplateName;
	}

	public function setIndexTemplateName($name)
	{
		$this->indexTemplateName = $name;

		return $this;
	}

	protected function getWholeFinalPage()
	{
		if (static::templateEngineIsFastTemplate())
		{
			return $this->getFastTemplateFinalPage();
		}

		$this->getTwig()->assign([
			'_tech' => [
				'html_base' => $this->getBaseAddress() . '/',
				'html_base_wo_slash' => $this->getBaseAddress(),
			]
		], true);

		return $this->getTwig()->parse($this->getIndexTemplateName(), [
			'needed' => $this->getNeededSwitches(),
			'meta' => $this->metaFields,
			'open_graph' => $this->openGraphFields,
		]);
	}

	protected function finish()
	{
		if (static::templateEngineIsFastTemplate())
		{
			$this->getTpl()
				->assign($this->metaFields, 'META_');
		}

		echo $this
			->finalParse()
			->getWholeFinalPage();

		return $this;
	}

	public static function ct($type)
	{
		$ct_ar = static::getCleanTitlesAr();

		return isset($ct_ar[$type]) ? $ct_ar[$type] : null;
	}

	/** @deprecated */
	public function m($idx = null)
	{
		return $this->getRoute($idx);
	}

	/**
	 * @param int|null $idx
	 * @return array|string|null
	 */
	public function getRoute($idx = null)
	{
		if ($idx < 0)
		{
			$idx += count($this->routes);
		}

		return $idx === null
			? $this->routes
			: (isset($this->routes[$idx]) ? $this->routes[$idx] : null);
	}

	/**
	 * @param array|string $route
	 * @param integer|null $idx
	 * @return $this
	 */
	public function setRoute($route, $idx = null)
	{
		if ($idx === null)
		{
			if (!is_array($route))
			{
				$route = [$route];
			}

			$this->routes = $route;
		}
		else
		{
			$this->routes[$idx] = $route;
		}

		$this->cleanupEmptyRoutes();

		return $this;
	}

	/**
	 * @param integer $idx
	 * @return $this
	 */
	public function removeRoute($idx)
	{
		if ($idx < 0)
		{
			$idx += count($this->routes);
		}

		if (isset($this->routes[$idx]))
		{
			array_splice($this->routes, $idx, 1);
		}

		return $this;
	}

	/**
	 * @param int|null $idx
	 * @return array|string|null
	 */
	public function getOrigRoute($idx = null)
	{
		if ($idx < 0)
		{
			$idx += count($this->origRoutes);
		}

		return $idx === null
			? $this->origRoutes
			: (isset($this->origRoutes[$idx]) ? $this->origRoutes[$idx] : null);
	}

	public function initTwig()
	{
		$host = \diRequest::domain();

		$this->Twig = \diTwig::create()->assign([
			'content_slugs' => $this->getCleanTitlesAr(),
			'logged_in' => $this->authUsed && \diAuth::i()->authorized() ? true : false,

			'_tech' => [
				'uri' => \diRequest::requestUri(),
				'url' => \diRequest::requestUri(),
				'year' => date('Y'),
				'timestamp' => time(),
				'http_host' => $host,
				'http_protocol' => $this->protocol,
				'html_base' => $this->getBaseAddress() . '/',
				'html_base_wo_slash' => $this->getBaseAddress(),
			],
		]);

		return $this;
	}

	protected function assignTwigBasics()
	{
		if ($this->twigBasicsAssigned)
		{
			return $this;
		}

		$contentReady = $this->responseCode == HttpCode::NOT_FOUND
			? !!count($this->getCachedContentCollection())
			: $this->getContentModel()->exists();

		if ($contentReady && !$this->Twig->getAssigned('content_page'))
		{
			$this->Twig->assign([
				'content_page' => $this->getContentModel(),
				'content_pages' => $this->getCachedContentCollection(),
				'content_by_type' => $this->getCachedContentCollectionByType(),
			]);

			$this->twigBasicsAssigned = true;
		}

		return $this;
	}

	public function init_tpl()
	{
		$this->tpl = new \FastTemplate(
			remove_ending_slash($this->tpl_dir), //.$this->language
			str_replace("%LANGUAGE%", $this->language, $this->tpl_cache_php)
		);

		if (static::ignoreCaches())
		{
			$this->getTpl()->rebuild_cache();
		}

		$this->getTpl()
			->no_strict()
			->load_cache();

		if (isset($_GET["404"]))
		{
			$this
				->define_templates()
				->initTplDefines()
				->error_404();
		}

		return $this;
	}

	/**
	 * @deprecated
	 * @return CMS
	 */
	public function init_tpl_defines()
	{
		return $this->initTplDefines();
	}

	/**
	 * @return CMS
	 */
	public function initTplDefines()
	{
		$this
			->defineIndexTemplates();

		$uri = \diRequest::requestUri();
		$host = \diRequest::domain();

		$this->getTpl()
			->define([
				"error_404" => "index/errors/404/page.htm",
				"error_login" => "index/errors/login/page.htm",
				"vm_error_message_div" => "index/errors/login/vm_error_message_div.htm",
			])
			->define("~banners", [
				"left_banner_row",
				"left_banners_block",
				"right_banner_row",
				"right_banners_block",
				"top_banner_row",
				"top_banners_block",
				"bottom_banner_row",
				"bottom_banners_block",
			])
			->define("~errors", [
				"error_line",
			])
			->define("~login", [
				"auth_block_with_tabs",
				"auth_form_base",
				"auth_panel",
				"auth_popup",
				"logged_in_menu_row",
				"user_panel",
			])
			->define("~login/oauth2", [
				"oauth2_block",
				"oauth2_vendor_row",
			])
			->define("~navy", [
				"bottom_navy_block",
				"navy_sortby_block",
				"navy_block",
				"next_active",
				"next_inactive",
				"prev_active",
				"prev_inactive",
			])
			->define("~pic", [
				"pic_img",
				"pic_swf",
				"pic_swf_safe",
				"pic_a_img",
				"pic_a_swf",
			])
			->define("~title", [
				"top_title_divider",
				"top_title_href",
				"top_title_nohref",
				"top_title_div",
			])->assign([
				"CURRENT_URI" => $uri,
				"CURRENT_URI2" => urlencode($uri),

				"CURRENT_TIMESTAMP" => time(),

				"HEADER_HTML_BASE" => \diRequest::urlBase(true),
				"HTML_BASE" => $this->getBaseAddress() . '/',
				"HTML_BASE2" => $this->getBaseAddress(),
				"HTTP_HOST" => $host,
				"HTTP_PROTOCOL" => $this->protocol,

				"LOGOUT_HREF" => \diLib::getAdminWorkerPath("auth", "logout") . "?back=" . urlencode($uri),
			]);

		$this->initTplAssigns();

		return $this;
	}

	/** @deprecated  */
	protected function defineIndexTemplates()
	{
		if (!static::templateEngineIsFastTemplate())
		{
			return $this;
		}

		$this->getTpl()
			->define("~", [
				"index",
				"head",
				"counters",
			])
			->define("~share", [
				"share_block",
			]);

		return $this;
	}

	protected function getCanonicalAddress($fullAddress = null)
	{
		if ($fullAddress === null)
		{
			$fullAddress = \diRequest::requestUri();
		}

		$fullAddress = StringHelper::removeQueryStringParameter($fullAddress, [], [\diPagesNavy::PAGE_PARAM, \diComments::PAGE_PARAM]);

		return $fullAddress;
	}

	protected function getBaseAddress()
	{
		return $this->protocol . '://' . \diRequest::domain();
	}

	public function initTplAssigns()
	{
		$this->getTpl()
			->assign([
				"CURRENT_YEAR" => date("Y"),
			]);

		return $this;
	}

	/**
	 * @return \diDeviceDetector
	 */
	public function getDeviceDetector()
	{
		if (!$this->device)
		{
			$this->device = \diDeviceDetector::create();
		}

		return $this->device;
	}

	/**
	 * @return \diCurrentCMS
	 */
	protected function setupDeviceDetector()
	{
		$device = [
			'os' => $this->getDeviceDetector()->getOsStr(),
			'type' => $this->getDeviceDetector()->getTypeStr(),
		];

		$this->getTwig()
			->assign([
				'device' => $device,
			]);

		$this->getTpl()
			->assign($device, "DEVICE_");

		return $this;
	}

	/** @deprecated */
	function get_modes()
	{
		return $this->populateRoutes();
	}

	protected function getFullRoute()
	{
		return addslashes(trim(\diRequest::requestUri(), '/'));
	}

	public function populateRoutes()
	{
		$url = $this->getFullRoute();

		$x = strpos($url, "?");
		if ($x !== false)
		{
			$url = rtrim(substr($url, 0, $x), "/");
		}

		$this->origRoutes = $this->routes = array_filter(explode('/', $url));

		$this
			->define_language($this->getRoute(0))
			->define_language_vars();

		if ($this->routes && in_array($this->routes[0], static::$possibleLanguages))
		{
			array_splice($this->routes, 0, 1);
		}

		if ($this->routes && !$this->routes[0])
		{
			array_splice($this->routes, 0, 1);
		}

		if (!$this->routes)
		{
			$this->routes[] = $this->ct($this->default_page_type);
		}

		if (!$this->routes || !$this->routes[0])
		{
			$this->errorNotFound();
		}

		$this->cleanupEmptyRoutes();

		return $this;
	}

	private function cleanupEmptyRoutes()
	{
		for ($i = 0; $i < count($this->routes); $i++)
		{
			if ($this->routes[$i] === "")
			{
				array_splice($this->routes, $i, 1);
				$i--;
			}
			else
			{
				$this->{"m" . $i} = $this->routes[$i];
			}
		}
	}

	public function define_language($language)
	{
		if (!$language)
		{
			return $this;
		}

		$this->language = $language;

		if (!in_array($language, static::$possibleLanguages))
		{
			$this->language = static::$defaultLanguage;
		}

		$this->language_href_prefix = static::languageHrefPrefix($this->language);

		return $this;
	}

	public static function languageHrefPrefix($language = null)
	{
		return !$language || $language == static::$defaultLanguage ? "" : "/" . $language;
	}

	public static function makeUrl($routes = [], $queryParams = [])
	{
		return static::languageHrefPrefix() . '/' . join('/', $routes) . ($routes ? '/' : '') .
			($queryParams ? '?' . http_build_query($queryParams) : '');
	}

  function define_language_vars()
  {
    $prefix = $this->language != "rus" && $this->language != "ru" ? $this->language."_" : "";

    foreach (static::$field_names_ar as $field)
    {
      $variable = "{$field}_var";
      $GLOBALS[$variable] = $prefix.$field;
      $this->$variable = $prefix.$field;
    }

	  return $this;
  }

	protected function getRoutesForLanguageLinks($language)
	{
		return $this->routes;
	}

	/**
	 * @return \diCurrentCMS
	 */
	protected function assign_top_language_links()
	{
		$links = [];

		if (count(static::$possibleLanguages) > 1)
		{
			$q = $_SERVER["QUERY_STRING"] ? "?" . $_SERVER["QUERY_STRING"] : "";

			$currentModesStr = null;

			$this->getTpl()
				->clear('LANGUAGE_LINK_ROWS');

			foreach (static::$possibleLanguages as $lng)
			{
				$isCurrentLanguage = $this->language == $lng;

				$modesStr = $this->getRoutesForLanguageLinks($lng);

				if (is_array($modesStr))
				{
					$modesStr = join("/", $modesStr);
				}

				if ($modesStr == $this->ct("home"))
				{
					$modesStr = '';
				}
				elseif ($modesStr && substr($modesStr, -1) != '/')
				{
					$modesStr .= '/';
				}

				$lngUp = strtoupper($lng);

				$lngLink = ($lng == static::$defaultLanguage ? "/" : "/$lng/") . $modesStr . $q;
				$lngActive = $isCurrentLanguage ? " active" : "";

				$this->getTpl()->assign([
					"PAGE_{$lngUp}_LINK" => $lngLink,
					"{$lngUp}_LINK_ACTIVE" => $lngActive,
					"LANG_PAGE_LINK" => $lngLink,
					"LANG_LINK_ACTIVE" => $lngActive,
					"LANG_NAME" => $lng,
				]);

				$links[$lng] = $lngLink;

				if ($this->getTpl()->defined('language_link_row'))
				{
					$this->getTpl()
						->parse('LANGUAGE_LINK_ROWS', '.language_link_row');
				}

				if ($isCurrentLanguage)
				{
					$currentModesStr = $modesStr;
				}
			}

			$this->getTpl()->assign([
				"SITE_LANGUAGE" => $this->language,
				"HTML_LANGUAGE" => $this->language,
				"LANGUAGE_HREF_PREFIX" => $this->language_href_prefix,
				"SITE_LNG_LINK" => $currentModesStr,
			]);
		}

		$this->getTwig()
			->assign([
				'_lang' => [
					'name' => $this->language,
					'href_prefix' => $this->language_href_prefix,
					'links' => $links,
				],
			]);

		return $this;
	}

	/**
	 * @return \diCurrentCMS
	 */
  public function load_content_rec()
  {
	  $this->ContentFamily->init();

	  $this->check_redundant_get_params();

	  return $this;
  }

	private function check_redundant_get_params()
	{
		\diCore\Tool\Embed\App::getInstance()
			->detect()
			->killGetParams();

		$fb_ar = [
			"fb_action_ids",
			"fb_action_types",
			"fb_source",
			"action_object_map",
			"action_type_map",
			"action_ref_map",
		];
		$from_fb = false;

		foreach ($_GET as $k => $v)
		{
			if (in_array($k, $fb_ar))
			{
				$from_fb = true;

				unset($_GET[$k]);
			}
		}

		if ($from_fb)
		{
			$uri = substr($_SERVER["REQUEST_URI"], 0, strpos($_SERVER["REQUEST_URI"], "?"));
			$get_ar = [];

			foreach ($_GET as $k => $v)
			{
				$get_ar[] = "$k=$v";
			}

			if ($get_ar)
			{
				$uri .= "?" . join("&", $get_ar);
			}

			$this->redirect_301($uri);
		}

		$ar = (array)\diContentTypes::getParam($this->getContentModel()->getType(), "possible_get_params");

		if (in_array("*", $ar))
		{
			return $this;
		}

		foreach ($_GET as $k => $v)
		{
			if (!in_array($k, $ar) && !in_array($k, array_merge(static::$skipGetParams, static::$customSkipGetParams)))
			{
				if (static::debugMode())
				{
					simple_debug('Query param not allowed: ' . $k);
				}

				$this->errorExtraQueryParams();
			}
		}

		return $this;
	}

	/** @deprecated */
	function get_content_of($id)
	{
		return $this->get_field_by_field("content", $this->content_var, "id", $id);
	}

	/**
	 * @return CMS
	 */
	protected function assignHtmlHead()
	{
		$pageData = [
			'title' => $this->getContentModel()->localized('title'),
			'caption' => $this->getContentModel()->localized('caption'),
			'short_content' => $this->getContentModel()->localized('short_content'),
			'content' => $this->getContentModel()->localized('content'),
			'output_mode' => \diCore\Tool\Embed\App::getInstance()->getModeName(),
		];

		$this->metaFields = [
			'title' => $this->getContentModel()->localized('meta_title') ?: $this->getContentModel()->localized('html_title'),
			'description' => $this->getContentModel()->localized('meta_description') ?: $this->getContentModel()->localized('html_description'),
			'keywords' => $this->getContentModel()->localized('meta_keywords') ?: $this->getContentModel()->localized('html_keywords'),
		];

		$canonical = [
			'url' => $this->getCanonicalAddress(),
			'full_url' => \diRequest::urlBase() . $this->getCanonicalAddress(),
		];

		$this->getTpl()
			->assign($this->getContentModel()->getTemplateVars(), 'PAGE_')
			->assign($canonical, 'CANONICAL_')
			->assign($pageData, 'PAGE_')
			->assign($this->metaFields, 'META_');

		$this->getTwig()
			->assign([
				//'page_data' => $pageData,
				'canonical' => $canonical,
			]);

		$this->assign_top_language_links();

		return $this;
	}

	protected function getFullMetaField($field, $oldPrefix = false)
	{
		$prefix = $oldPrefix ? static::META_FIELD_PREFIX_OLD : static::META_FIELD_PREFIX;

		return $prefix . strtolower($field);
	}

	protected function getFullOpenGraphField($field)
	{
		return static::OPEN_GRAPH_FIELD_PREFIX . strtolower($field);
	}

	public function getMeta($field)
	{
		return isset($this->metaFields[$field])
			? $this->metaFields[$field]
			: null;
	}

	/**
	 * @param string|array $text
	 * @param string $field
	 *
	 * @return \diCurrentCMS
	 */
	public function setMeta($text, $field = 'title')
	{
		if (is_array($text))
		{
			$text = ArrayHelper::recursiveJoin($text, ' ');
		}

		$this->metaFields[$field] = $text;

		return $this;
	}

	public function appendMeta($text, $field = 'title')
	{
		$this->metaFields[$field] = $this->getMeta($field) . $text;

		return $this;
	}

	public function getOpenGraph($field)
	{
		return isset($this->openGraphFields[$field])
			? $this->openGraphFields[$field]
			: null;
	}

	public function setOpenGraph($text, $field = 'title')
	{
		if (is_array($text))
		{
			$text = ArrayHelper::recursiveJoin($text, ' ');
		}

		$this->openGraphFields[$field] = $text;

		return $this;
	}

	public function appendOpenGraph($text, $field = 'title')
	{
		$this->openGraphFields[$field] = $this->getOpenGraph($field) . $text;

		return $this;
	}

	public function setHeaderImage($imagePath, $imageHtmlBase = null, $width = '', $height = '')
	{
		$this->getTpl()
			->assign([
				"HEADER_IMAGE_URI" => $imagePath,
				"HEADER_IMAGE_W" => $width,
				"HEADER_IMAGE_H" => $height,
				"HEADER_HTML_BASE" => $imageHtmlBase ?: \diRequest::urlBase(true),
			]);

		$this
			->setOpenGraph(($imageHtmlBase ?: \diRequest::urlBase(true)) . $imagePath, 'image')
			->setOpenGraph($width, 'image_width')
			->setOpenGraph($height, 'image_height');

		return $this;
	}

	public function getHeaderImage()
	{
		return $this->getOpenGraph('image');
	}

	public function hasHeaderImage()
	{
		return !!$this->getHeaderImage();
	}

	/** @deprecated  */
	protected function checkOpenGraphVariables()
	{
		if (!static::templateEngineIsFastTemplate())
		{
			return $this;
		}

		if (!$this->getOpenGraph('title'))
		{
			$this->setOpenGraph($this->getMeta('title'));
		}

		if (!$this->getOpenGraph('description'))
		{
			$this->setOpenGraph($this->getMeta('description'), 'description');
		}

		return $this;
	}

	/**
	 * Could be overridden if different languages used
	 * @return string
	 */
	public function getPaginationTitleSuffixTemplate()
	{
		return ' Страница №%d';
	}

	/**
	 * Should we add "Page N" to the page title
	 * @return bool
	 */
	protected function isPageTitleSuffixNeeded()
	{
		return true;
	}

	/**
	 * Automatically sets meta tags based on $model
	 *
	 * todo: add pics support
	 *
	 * @param \diModel|null $model
	 * @param array $defaults
	 * @return $this
	 */
	public function assignMeta(\diModel $model = null, $defaults = [])
	{
		$defaults = extend([
			"title" => null,
			"description" => null,
			"keywords" => null,
		], $defaults);

		if (!$model)
		{
			$model = $this->getContentModel();
		}

		foreach ($defaults as $field => $defaultValue)
		{
			$value = $model->localized($this->getFullMetaField($field), $this->getLanguage())
				?: $model->localized($this->getFullMetaField($field, true), $this->getLanguage())
				?: $defaultValue;

			if ($field == "title" && !$value)
			{
				$value = $model->localized("title", $this->getLanguage());
			}

			if ($value)
			{
				$this->setMeta($value, $field);
			}
		}

		// adding page to the end
		$page = \diRequest::get(\diPagesNavy::PAGE_PARAM, \diRequest::get(\diComments::PAGE_PARAM, 0));

		if ($page > 1 && $this->isPageTitleSuffixNeeded())
		{
			$this->appendMeta(sprintf($this->getPaginationTitleSuffixTemplate(), $page), "title");
		}

		return $this;
	}

	/** @deprecated */
	function get_pic_tag($r, $field, $folder = false)
	{
	    global $content_pics_folder;

	    if (!$folder)
	    {
		    $folder = $content_pics_folder;
	    }

		$pic_tag_tpl = strtolower(get_file_ext($r->$field)) == "swf" ? "pic_swf_safe" : "pic_img";

		$this->tpl->assign([
			"PIC_IDX" => ++$this->safe_swf_idx,
			"PIC_PIC" => $folder . $r->$field,
			//"PIC_PIC_SAFE" => "/pic/default/default_pic145.png",
			"PIC_PIC_W" => $r->{$field . "_w"},
			"PIC_PIC_H" => $r->{$field . "_h"},
			"PIC_MODE" => "transparent",
		]);

		return $this->tpl->parse("_", $pic_tag_tpl);
	}

	/**
	 * todo: remake
	 * @link https://css-tricks.com/css-content/
	 */
	public function strCutEnd($s, $max_len, $trailer = "...")
	{
		$s2 = str_cut_end($s, $max_len, $trailer);

		if ($s != $s2)
		{
			return "<span title=\"".str_out($s)."\">".str_out($s2)."</span>";
		}
		else
		{
			return str_out($s);
		}
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function assign_ct_ar()
	{
		foreach ($this->getCleanTitlesAr() as $t => $ct)
		{
			$this->getTpl()->assign([
				strtoupper($t) . "_CLEAN_TITLE" => $ct,
				strtoupper($t) . "_SLUG" => $ct,
			]);
		}

		return $this;
	}

	/** @deprecated */
	function tpl_define($filelist)
	{
		$this->tpl->define($filelist);

		return $this;
	}

	/** @deprecated */
	function tpl_define2($subdir, $filelist)
	{
		$this->tpl->define2($subdir, $filelist); //, $this->language."/"

		return $this;
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function initBreadCrumbs()
	{
		$this->BreadCrumbs->init();

		return $this;
	}

	public function needToPrintBreadCrumbs()
	{
		return !in_array($this->getContentModel()->getType(), ['home']);
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function printBreadCrumbs()
	{
		$this->getBreadCrumbs()->finish();

		return $this;
	}

	/**
	 * @return \diCurrentCMS
	 */
	public function load_content_table_cache($forceRebuild = false)
	{
		if ($forceRebuild)
		{
			$this->build_content_table_cache();
		}

		include($this->tables_cache_fn_ar[$this->content_table]);

		return $this;
	}

	public function build_content_table_cache()
	{
		$this->ct_ar = [];
		$cache_file = "<?php\n";
		$ct_rows = "";

		$col = \diCollection::createForTable($this->content_table)->orderBy('order_num');
		/** @var Model $model */
		foreach ($col as $model)
		{
			$cache_file .= "\$this->tables['{$this->content_table}']['{$model->getId()}'] = " .
				$model->asPhp($this->fieldsExcludedFromCache) . ";\n\n";

			if ($model->getType() != "user")
			{
				$this->ct_ar[$model->getType()] = $model->getRawSlug();
			}
		}

		foreach ($this->ct_ar as $t => $ct)
		{
			$ct_rows .= "\$this->ct_ar['$t'] = '$ct';\n";
		}

		$cache_file .= $ct_rows;

		// main table cache file
		file_put_contents($this->tables_cache_fn_ar[$this->content_table], $cache_file);
		chmod($this->tables_cache_fn_ar[$this->content_table], $this->fileChmod);
		//

		$ct_rows = "<?php\n" . str_replace('$this->', '$z_', $ct_rows);

		// clean titles cache file
		file_put_contents($this->ct_cache_fn_ar[$this->content_table], $ct_rows);
		chmod($this->ct_cache_fn_ar[$this->content_table], $this->fileChmod);
		//

		return $this;
	}

	/**
	 * Such page exists, but query string has redundant params
	 *
	 * @return $this
	 */
	public function errorExtraQueryParams()
	{
		$this->error_404();

		return $this;
	}

	/**
	 * Such page doesn't exist
	 *
	 * @return $this
	 */
	public function errorNotFound()
	{
		$this->error_404();

		return $this;
	}

	/**
	 * Such page exist, user will be able to access when authorized
	 *
	 * @return $this
	 */
	public function errorNotAuthorized()
	{
		$this->responseCode = HttpCode::UNAUTHORIZED;

		HttpCode::header($this->responseCode);

		$this->printBreadCrumbs();

		echo $this->getTpl()
			->process("PAGE", ".error_login")
			->process("head")
			->parse("FINAL", "index");

		die(0);
	}

	/**
	 * Such page exists, user is authorized, but he has no access to it
	 * Todo: remake to 401 code
	 *
	 * @return $this
	 */
	public function errorNoAccess()
	{
		$this->responseCode = HttpCode::FORBIDDEN;

		$this->error_404();

		return $this;
	}

	public function error_404()
	{
		$this->responseCode = HttpCode::NOT_FOUND;

		HttpCode::header($this->responseCode);

		if (static::debugMode())
		{
			echo "<p><b>Debug back trace:</b></p><pre>";
			debug_print_backtrace();
			echo "</pre>";
		}

		echo $this->getTpl()->parse("FINAL", "error_404");
		die(0);
	}

	public function redirect301ToPage($pageType, $die = true)
	{
		static::redirect_301($this->getModelByType($pageType)->getHref(), $die);
	}

	public function redirectToPage($pageType, $die = false)
	{
		static::redirect($this->getModelByType($pageType)->getHref(), $die);
	}

	public static function redirect_301($href, $die = true)
	{
		HttpCode::header(HttpCode::MOVED_PERMANENTLY);

		static::redirect($href, $die);
	}

	public static function redirect($href, $die = false)
	{
		header("Location: " . $href);

		if ($die)
		{
			die();
		}
	}

	public static function reload($die = true)
	{
		static::redirect(\diRequest::requestUri(), $die);
	}

	/** @deprecated  */
	public function error_not_logged_in()
	{
		return $this->errorNotAuthorized();
	}

	protected function getEmptyModel()
	{
		return \diModel::create(Types::content);
	}

	/**
	 * Returns content model by id
	 *
	 * @param $id
	 * @return Model
	 */
	public function getModelById($id)
	{
		return isset($this->tables["content"][$id])
			? $this->tables["content"][$id]
			: $this->getEmptyModel();
	}

	/**
	 * Returns content model by type
	 *
	 * @param $type
	 * @return Model
	 */
	public function getModelByType($type)
	{
		/**
		 * @var Model $m
		 */
		foreach ($this->getCachedContentCollection() as $m)
		{
			if ($m->getType() == $type)
			{
				return $m;
			}
		}

		return $this->getEmptyModel();
	}

	public function getChildren($parent)
	{
		if (!$parent instanceof Model)
		{
			$parent = $this->getModelById($parent);
		}

		$ar = [];

		if (!$parent->exists())
		{
			return $ar;
		}

		/**
		 * @var Model $m
		 */
		foreach ($this->getCachedContentCollection() as $m)
		{
			if ($m->getParent() == $parent->getId())
			{
				$ar[] = $m;
			}
		}

		return $ar;
	}

	/**
	 * @param $parent
	 * @return Model
	 */
	public function getFirstChild($parent)
	{
		$ar = $this->getChildren($parent);

		if (!count($ar))
		{
			return $this->getEmptyModel();
		}

		return $ar[0];
	}

	/**
	 * @param $parent
	 * @return Model
	 */
	public function getFirstVisibleChild($parent, $field = "visible")
	{
		$ar = $this->getChildren($parent);

		/**
		 * @var int $id
		 * @var Model $m
		 */
		foreach ($ar as $m)
		{
			if ($m->has($field))
			{
				return $m;
			}
		}

		return $this->getEmptyModel();
	}

  function get_first_child($table, $parent_id)
  {
    foreach ($this->tables[$table] as $id => $ar)
    {
      if ($ar["parent"] == $parent_id)
      {
        return $ar;
      }
    }

    return false;
  }

  function get_children_ids_ar($table = "content", $parent_id = -1, $ids_ar = array())
  {
    foreach ($this->tables[$table] as $id => $ar)
    {
      if ($ar["parent"] == $parent_id)
      {
        $ids_ar[] = $ar["id"];
        $ids_ar = $this->get_children_ids_ar($table, $ar["id"], $ids_ar);
      }
    }

    return $ids_ar;
  }

  function get_field_by_field($table = "content", $field_needed = "", $field_known = "", $value_known = "")
  {
    foreach ($this->tables[$table] as $id => $ar)
    {
      if ($ar[$field_known] == $value_known) return $ar[$field_needed];
    }

    return null;
  }

	public static function contentIdByType($type)
	{
		/** @var CMS $Z */
		global $Z;

		if (!isset($Z))
		{
			$Z = new \diTemporaryCMS();
			$Z->load_content_table_cache();
		}

		return $Z->get_id_by_type('content', $type);
	}

  function get_id_by_type($table, $type)
  {
    return $this->get_field_by_field($table, "id", "type", $type);
  }

  function get_title_by_type($table, $type)
  {
    return $this->get_field_by_field($table, $this->title_var, "type", $type);
  }

  function get_r_by_type($table, $type)
  {
    return (object)$this->tables[$table][$this->get_field_by_field($table, "id", "type", $type)];
  }

  function get_level0_id($id)
  {
    if ($this->tables[$this->content_table]["$id"]["level_num"] == 0)
      return $id;

    do
    {
      $id = $this->tables[$this->content_table]["$id"]["parent"];
    } while ($this->tables[$this->content_table]["$id"]["level_num"] != 0);

    return $id;
  }

  function get_level0_rec($id)
  {
    $id = $this->get_level0_id($id);

    return $this->tables[$this->content_table]["$id"];
  }

  function get_next_sibling($r, $visible = -1)
  {
    $content_ids_ar = array_keys($this->tables["content"]);
    $content_ids_ar_count = count($content_ids_ar);

    for ($i = 0; $i < $content_ids_ar_count; $i++)
    {
      $id = $content_ids_ar[$i];
      $ar = $this->tables[$this->content_table][$id];

      if (isset($r["parent"]) && $ar["parent"] == $r["parent"] && $ar["order_num"] > $r["order_num"] && ($visible == -1 || $ar["visible"] == $visible))
        return $ar;
    }

    return false;
  }

  function get_previous_sibling($r, $visible = -1)
  {
    $content_ids_ar = array_keys($this->tables["content"]);
    $content_ids_ar_count = count($content_ids_ar);

    for ($i = $content_ids_ar_count - 1; $i >= 0; $i--)
    {
      $id = $content_ids_ar[$i];
      $ar = $this->tables[$this->content_table][$id];

      if (isset($r["parent"]) && $ar["parent"] == $r["parent"] && $ar["order_num"] < $r["order_num"] && ($visible == -1 || $ar["visible"] == $visible))
        return $ar;
    }

    return false;
  }

	/**
	 * @param Model $model
	 * @return bool
	 */
	protected function isContentPageSelected(Model $model)
	{
		return $model->getId() == $this->getContentModel()->getId();
	}

	/**
	 * Override this if to_show_content is used
	 *
	 * @param Model $model
	 * @return Model
	 */
	protected function getRealContentModel(Model $model)
	{
		/*
		if ($model->getLevelNum() == 0 && !$model->hasToShowContent())
		{
			$child = $this->getFirstVisibleChild($model);

			if ($child->exists())
			{
				return $child;
			}
		}
		*/

		return $model;
	}

	/**
	 * @deprecated
	 * @param Model $model
	 * @return CMS
	 */
	public function assignMenuData(Model $model)
	{
		$this->getTpl()
			->assign($model->getTemplateVarsExtended(), "MENU_")
			->assign([
				"TITLE" => $model->localized("title"),
				"CAPTION" => $model->localized("caption"),
				"SHORT_CONTENT" => $model->localized("short_content"),
				"CONTENT" => $model->localized("content"),
				"CLASS_SELECTED" => $this->isContentPageSelected($model) ? $this->getSelectedMenuClassName() : '',
				"HREF" => $this->getRealContentModel($model)->getHref(),
			], "MENU_");

		return $this;
	}

	protected function getSelectedMenuClassName()
	{
		return 'selected';
	}

	/**
	 * @param \diModel $m
	 */
	public function setMainTarget(\diModel $m)
	{
		$this->mainTarget = $m;

		return $this;
	}

	/**
	 * @return \diModel
	 */
	public function getMainTarget()
	{
		return $this->mainTarget ?: $this->getContentModel();
	}
}