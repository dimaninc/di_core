<?php

use diCore\Data\Config;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Wrapper for Twig template engine
 *
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.04.2016
 * Time: 9:45
 */
class diTwig
{
    const TEMPLATES_FOLDER = 'templates';
    const CACHE_FOLDER = '_cfg/cache/twig';

    const TOKEN_FOR_PAGE = '_page';
    const TOKEN_FOR_INDEX = '_index';

    const FILE_EXTENSION = '.html.twig';

    const customClassName = 'diCustomTwig';

    const NAMESPACE_CORE = 'core';
    const NAMESPACE_MAIN = FilesystemLoader::MAIN_NAMESPACE;

    /**
     * @var FilesystemLoader
     */
    private $loader;

    /**
     * @var Environment
     */
    private $Twig;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string
     * This template will be used in renderIndex by default, if set
     */
    private $templateForIndex;

    /**
     * diTwig constructor.
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->loader = new FilesystemLoader();

        foreach ($this->getAllPaths() as $namespace => $paths) {
            $this->loader->setPaths(static::wrapPaths($paths), $namespace);
        }

        $this->Twig = new Environment(
            $this->loader,
            extend(
                [
                    'cache' => Config::getCacheFolder() . static::CACHE_FOLDER,
                    'auto_reload' => diCurrentCMS::ignoreCaches(),
                ],
                $options
            )
        );

        $this->addFilters()
            ->addExtensions()
            ->assignInitialData();
    }

    /**
     * @param array $options
     * @return diTwig
     */
    public static function create($options = [])
    {
        $className = class_exists(self::customClassName)
            ? self::customClassName
            : get_called_class();

        $t = new $className($options);

        return $t;
    }

    protected function assignInitialData()
    {
        return $this;
    }

    protected function addExtensions()
    {
        /*
        $this->getEngine()->addExtension(new Jasny\Twig\DateExtension());
        $this->getEngine()->addExtension(new Jasny\Twig\PcreExtension());
        $this->getEngine()->addExtension(new Jasny\Twig\TextExtension());
        $this->getEngine()->addExtension(new Jasny\Twig\ArrayExtension());
        */

        return $this;
    }

    protected function addFilters()
    {
        return $this;
    }

    protected function addStrFilesizeFilter()
    {
        $this->getEngine()->addFilter(
            new TwigFilter('str_filesize', function ($size) {
                return str_filesize($size);
            })
        );

        return $this;
    }

    protected function addPregReplaceFilter()
    {
        $this->getEngine()->addFilter(
            new TwigFilter('preg_replace', function (
                $subject,
                $pattern,
                $replacement
            ) {
                return preg_replace($pattern, $replacement, $subject);
            })
        );

        return $this;
    }

    protected function addLead0Filter()
    {
        $this->getEngine()->addFilter(
            new TwigFilter('lead0', function ($num) {
                return lead0($num);
            })
        );

        return $this;
    }

    protected function getAllPaths()
    {
        return extend(
            [
                //self::NAMESPACE_CORE => $this->getCorePaths(),
                self::NAMESPACE_MAIN => array_merge(
                    $this->getMainPaths(),
                    $this->getCorePaths()
                ),
            ],
            $this->getOtherPaths()
        );
    }

    protected function getCorePaths()
    {
        return [Config::getTwigCorePath()];
    }

    protected function getMainPaths()
    {
        return [''];
    }

    protected function getOtherPaths()
    {
        return [
                // 'namespace' => ['paths'],
            ];
    }

    protected static function wrapPaths($paths)
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }

        foreach ($paths as &$path) {
            $path =
                Config::getTemplateFolder() .
                static::TEMPLATES_FOLDER .
                ($path ? '/' . $path : '');
        }

        return $paths;
    }

    protected static function wrapTemplateName($name)
    {
        if (StringHelper::endsWith($name, static::FILE_EXTENSION)) {
            return $name;
        }

        return $name . static::FILE_EXTENSION;
    }

    /**
     * @deprecated
     * @return Environment
     */
    public function getTwig()
    {
        return $this->getEngine();
    }

    /**
     * @return Environment
     */
    public function getEngine()
    {
        return $this->Twig;
    }

    /**
     * Checks if template file exists
     *
     * @param string $templateName
     * @return bool
     */
    public function exists($templateName)
    {
        return $this->loader->exists(static::wrapTemplateName($templateName));
    }

    /**
     * Get whole context data array or an item by key
     *
     * @param string|null $key
     * @return array|string|null
     */
    public function get($key = null)
    {
        if ($key !== null) {
            return $this->data[$key] ?? null;
        }

        return $this->data;
    }

    public function getPage()
    {
        return $this->get(self::TOKEN_FOR_PAGE);
    }

    public function getIndex()
    {
        return $this->get(self::TOKEN_FOR_INDEX);
    }

    /**
     * Check if context data item exists by key
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return !!$this->get($key);
    }

    public function hasPage()
    {
        return $this->has(self::TOKEN_FOR_PAGE);
    }

    public function hasIndex()
    {
        return $this->has(self::TOKEN_FOR_INDEX);
    }

    /**
     * Add context data
     *
     * @param array|object $data
     * @return $this
     */
    public function assign($data, $recursive = false)
    {
        if ($data) {
            $this->data = $recursive
                ? array_replace_recursive($this->data, $data)
                : extend($this->data, $data);
        }

        return $this;
    }

    public function getAssigned($token)
    {
        return $this->data[$token] ?? null;
    }

    public function assigned($token)
    {
        return !!$this->getAssigned($token);
    }

    public function addFunction($name, callable $callable)
    {
        $function = new TwigFunction($name, $callable);
        $this->Twig->addFunction($function);

        return $this;
    }

    /**
     * Parse template from file
     * @param $template
     * @param array $data
     * @return string
     */
    public function parse($template, $data = [])
    {
        return $this->getEngine()->render(
            static::wrapTemplateName($template),
            extend($this->get(), $data)
        );
    }

    /**
     * Parse template from text
     * @param $templateText
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function parseVirtual($templateText, $data = [])
    {
        return $this->getEngine()
            ->createTemplate($templateText)
            ->render(extend($this->get(), $data));
    }

    /**
     * @param $template
     * @param string $token
     * @param array $data
     * @return $this
     */
    public function render($template, $token, $data = [])
    {
        $this->assign([
            $token => $this->parse($template, $data),
        ]);

        return $this;
    }

    /**
     * @param string $template
     * @param array $data
     * @return diTwig
     */
    public function renderPage($template, $data = [])
    {
        global $Z;

        return $this->render(
            $template,
            self::TOKEN_FOR_PAGE,
            extend(
                [
                    'Z' => $Z ?? null,
                ],
                $data
            )
        );
    }

    /**
     * @param null|string $template
     * @param array $data
     * @return diTwig
     */
    public function renderIndex($template = null, $data = [])
    {
        global $Z;

        $template = $template ?: $this->templateForIndex;

        if (!$template) {
            throw new \Exception('Template not defined for diTwig->renderIndex');
        }

        return $this->render(
            $template,
            self::TOKEN_FOR_INDEX,
            extend(
                [
                    'Z' => $Z ?? null,
                ],
                $data
            )
        );
    }

    /**
     * @param string $templateForIndex
     */
    public function setTemplateForIndex($templateForIndex)
    {
        $this->templateForIndex = $templateForIndex;

        return $this;
    }

    public function importFromFastTemplate(
        FastTemplate $tpl,
        $tokens = [],
        $clear = true
    ) {
        foreach ($tokens as $k => $v) {
            if (!is_string($k)) {
                $k = $v;
            }

            $this->assign([
                $k => $tpl->getAssigned($v),
            ]);

            if ($clear) {
                $tpl->clear($v);
            }
        }

        return $this;
    }

    public static function flushCache()
    {
        $dir = Config::getCacheFolder() . self::CACHE_FOLDER;

        try {
            FileSystemHelper::delTree($dir, false);
        } catch (\Exception $e) {
        }
    }
}
