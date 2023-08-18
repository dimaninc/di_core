<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 16.10.15
 * Time: 16:43
 */

use diCore\Entity\Content\Model;

class diSiteMapGenerator
{
    protected static $className = 'diCustomSiteMapGenerator';
    protected $folder = null; // null == root
    protected $filename = 'sitemap.xml';
    protected $domain;
    protected $protocol;

    public static $skippedContentTypes = [
        //'home',
        'href',
        'sitemap',
        'search',
        'registration',
        'enter_new_password',
        'forgotten_password',
        'payment_callback',
    ];
    public static $customSkippedContentTypes = [];

    protected $addedUrls = [];

    protected $items = [
        'url' => [],
        'image' => [],
        'video' => [],
    ];

    public function __construct()
    {
        $this->domain = \diRequest::domain();
        $this->protocol = \diRequest::protocol() . '://';
    }

    /**
     * @return diSiteMapGenerator
     */
    public static function create()
    {
        if (!\diLib::exists(self::$className)) {
            self::$className = get_called_class();
        }

        $g = new self::$className();

        return $g;
    }

    public static function createAndGenerate()
    {
        $g = static::create();

        $g->generate()->store();

        return $g;
    }

    public function generate()
    {
        $this->generateForCollection(
            \diCollection::create(\diTypes::content)->orderBy('order_num')
        );

        return $this;
    }

    public function generateForCollection(\diCollection $collection)
    {
        /** @var \diModel $model */
        foreach ($collection as $model) {
            $this->addUrlItem($model);
        }

        return $this;
    }

    protected function isUrlAdded($url)
    {
        return in_array($url, $this->addedUrls);
    }

    protected function addUrlItem(\diModel $model)
    {
        $url = $this->getUrlOfModel($model);

        if ($this->isRowSkipped($model) || $this->isUrlAdded($url)) {
            return $this;
        }

        $this->items['url'][] = $this->getUrlItem($model);
        $this->addedUrls[] = $url;

        return $this;
    }

    protected function getRelativeUrlOfModel(\diModel $model)
    {
        return $model->getHref();
    }

    protected function getUrlOfModel(\diModel $model)
    {
        return $this->protocol .
            $this->domain .
            $this->getRelativeUrlOfModel($model);
    }

    protected function getChangeFreqOfModel(\diModel $model)
    {
        return 'daily';
    }

    protected function getPriorityOfModel(\diModel $model)
    {
        return 0.5;
    }

    protected function getLastModificationDate(\diModel $model)
    {
        return $model->get('updated_at'); // null or 0 for now
    }

    protected function getUrlItem(\diModel $model)
    {
        return [
            [
                'key' => 'loc',
                'value' => $this->getUrlOfModel($model),
            ],
            [
                'key' => 'changefreq',
                'value' => $this->getChangeFreqOfModel($model),
            ],
            [
                'key' => 'priority',
                'value' => $this->getPriorityOfModel($model),
            ],
            [
                'key' => 'lastmod',
                'value' => \diDateTime::isoFormat(
                    $this->getLastModificationDate($model)
                ),
            ],
        ];
    }

    protected function arToXml($ar)
    {
        $a = [];

        foreach ($ar as $opts) {
            $attrs = '';
            $k = $opts['key'];
            $value = $opts['value'] ?? null;
            $rawValue = false;

            if (is_array($value)) {
                $value = $this->arToXml($value) . "\n";
                $rawValue = true;
            }

            if (!empty($opts['attrs'])) {
                foreach ($opts['attrs'] as $attrKey => $attrValue) {
                    $attrs .=
                        " {$attrKey}=\"" .
                        htmlspecialchars($attrValue, ENT_COMPAT, 'UTF-8') .
                        "\"";
                }
            }

            if ($value !== null) {
                $value = $rawValue
                    ? "\n" . $value
                    : htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
            }

            $a[] =
                "<{$k}{$attrs}" .
                ($value !== null ? '>' . $value . "</{$k}>" : ' />');
        }

        return join("\n", $a);
    }

    protected function getItemsXml()
    {
        $out = [];

        foreach ($this->items as $type => $ar) {
            switch ($type) {
                case 'url':
                    $out[$type] = join(
                        "\n",
                        array_map(function ($value) use ($type) {
                            return "<{$type}>{$this->arToXml(
                                $value
                            )}</{$type}>";
                        }, $ar)
                    );

                    break;
            }
        }

        return join("\n", $out) . "\n";
    }

    protected function getXmlAdditionAttributes()
    {
        return '';
    }

    public function getXml()
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"{$this->getXmlAdditionAttributes()}>\n" .
            $this->getItemsXml() .
            '</urlset>';
    }

    protected function store()
    {
        $folder =
            $this->folder === null ? \diPaths::fileSystem() : $this->folder;
        $filename =
            \diCore\Helper\StringHelper::slash($folder) . $this->filename;

        $xml = $this->getXml();

        file_put_contents($filename, $xml);

        return $this;
    }

    public static function isContentRowSkipped(Model $model)
    {
        return in_array($model->getType(), static::$skippedContentTypes) ||
            in_array($model->getType(), static::$customSkippedContentTypes) ||
            \diContentTypes::getParam($model->getType(), 'logged_in');
    }

    protected function isRowSkipped(\diModel $model)
    {
        switch ($model->getTable()) {
            case 'content':
                return static::isContentRowSkipped($model);
        }

        return false;
    }
}
