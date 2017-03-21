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
	protected static $className = "diCustomSiteMapGenerator";
	protected $folder = null; // null == root
	protected $filename = "sitemap.xml";
	protected $domain;
	protected $protocol;

	public static $skippedContentTypes = [
		//"home",
		"href",
		"sitemap",
		"search",
		"registration",
		"enter_new_password",
		"forgotten_password",
		"payment_callback",
	];
	public static $customSkippedContentTypes = [];

	protected $items = [
		"url" => [],
		"image" => [],
		"video" => [],
	];

	public function __construct()
	{
		$this->domain = $_SERVER["HTTP_HOST"];
		$this->protocol = $_SERVER["SERVER_PORT"] == 443 ? "https://" : "http://";
	}

	/**
	 * @return diSiteMapGenerator
	 */
	public static function create()
	{
		if (!diLib::exists(self::$className))
		{
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
		$this->generateForCollection(diCollection::create(diTypes::content)->orderBy('order_num'));

		return $this;
	}

	public function generateForCollection(diCollection $collection)
	{
		/** @var diModel $model */
		foreach ($collection as $model)
		{
			$this->addUrlItem($model);
		}

		return $this;
	}

	protected function addUrlItem(diModel $model)
	{
		if ($this->isRowSkipped($model))
		{
			return $this;
		}

		switch ($model->getTable())
		{
			default:
				$this->items["url"][] = $this->getUrlItem($model);
				break;
		}

		return $this;
	}

	protected function getUrlItem(diModel $model)
	{
		return [
			[
				"key" => "loc",
				"value" => $this->protocol . $this->domain . $model->getHref(),
			]
		];
	}

	protected function getItemsXml()
	{
		$out = [];

		foreach ($this->items as $type => $ar)
		{
			switch ($type)
			{
				case "url":
					$out[$type] = join("\n", array_map(function($value) use($type) {
						$a = array();

						foreach ($value as $opts)
						{
							$attrs = "";
							$k = $opts["key"];
							$value = isset($opts["value"]) ? $opts["value"] : null;

							if (!empty($opts["attrs"]))
							{
								foreach ($opts["attrs"] as $attrKey => $attrValue)
								{
									$attrs .= " {$attrKey}=\"" . htmlspecialchars($attrValue, ENT_COMPAT, 'UTF-8') . "\"";
								}
							}

							$a[] = "<{$k}{$attrs}" . ($value !== null ? ">" . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . "</{$k}>" : " />");
						}

						return "<$type>" . join("\n", $a) . "</$type>";
					}, $ar));
					break;
			}
		}

		return join("\n", $out) . "\n";
	}

	protected function getXmlAdditionAttributes()
	{
		return "";
	}

	public function getXml()
	{
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
			"<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"{$this->getXmlAdditionAttributes()}>\n" .
			$this->getItemsXml() .
			"</urlset>";
	}

	protected function store()
	{
		$folder = $this->folder === null ? diPaths::fileSystem() : $this->folder;
		$filename = add_ending_slash($folder) . $this->filename;

		$xml = $this->getXml();

		file_put_contents($filename, $xml);

		return $this;
	}

	public static function isContentRowSkipped(Model $model)
	{
		return in_array($model->getType(), static::$skippedContentTypes) ||
			in_array($model->getType(), static::$customSkippedContentTypes) ||
			diContentTypes::getParam($model->getType(), "logged_in");
	}

	protected function isRowSkipped(diModel $model)
	{
		switch ($model->getTable())
		{
			case "content":
				return static::isContentRowSkipped($model);
		}

		return false;
	}
}