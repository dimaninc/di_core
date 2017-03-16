<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.10.15
 * Time: 17:33
 */
class diBreadCrumbs
{
	/**
	 * @var array
	 */
	protected $elements = array();

	protected $skippedContentTypes = array("virtual", "logged_in_menu");

	/**
	 * @var diCMS
	 */
	private $Z;

	private $type;

	private $divider = " / ";

	public function __construct(diCMS $Z)
	{
		$this->Z = $Z;

		$this->type = $this->getZ()->content_table;
	}

	/**
	 * @return diCMS
	 */
	protected function getZ()
	{
		return $this->Z;
	}

	protected function getTpl()
	{
		return $this->getZ()->getTpl();
	}

	public function reset()
	{
		$this->elements = array();

		return $this;
	}

	private function hrefNeeded(diModel $m)
	{
		return
			!$m->exists("to_show_content") ||
			($m->has("to_show_content") && $m->getId() != $this->getZ()->getContentModel()->getId());
	}

	public function init()
	{
		$this->reset();

		if ($this->getTpl()->defined("top_title_divider"))
		{
			$this->setDivider($this->getTpl()->parse("top_title_divider"));
		}

		/** @var diContentModel $m */
		foreach ($this->getZ()->getContentFamily()->get() as $m)
		{
			if (in_array($m->getType(), $this->skippedContentTypes))
			{
				continue;
			}

			$this->add(array(
				"href" => $this->hrefNeeded($m) ? $m->getHref() : null,
				"hrefPrefixNeeded" => false,
				"model" => $m,
			));
		}

		return $this;
	}

	public function setDivider($divider)
	{
		$this->divider = $divider;

		return $this;
	}

	public function addHref($index = -1)
	{
		if (!count($this->elements))
		{
			return $this;
		}

		$m = $this->getZ()->getContentFamily()->getMemberByLevel($index);

		$this->update($index, array(
			"href" => $m->getHref(),
		));

		return $this;
	}

	public function remove($index = -1)
	{
		if ($index < 0)
		{
			$index += count($this->elements);
		}

		array_splice($this->elements, $index, 1);

		return $this;
	}

	public function add($titleOrElement, $href = "", $class = "", $word_wrap = false)
	{
		$element = extend(array(
			"title" => null,
			"href" => null,
			"hrefPrefixNeeded" => true,
			"class" => null,
			"wordWrap" => false,
			"position" => -1,
			"model" => diModel::create($this->type),
		), !is_array($titleOrElement)
			? array(
				"title" => $titleOrElement,
				"href" => $href,
				"class" => $class,
				"wordWrap" => $word_wrap,
			)
			: $titleOrElement
		);

		/** @var diModel $m */
		$m = $element["model"];

		if ($m->exists())
		{
			if (!$element["title"])
			{
				$element["title"] = $m->localized("title");
			}

			if (!$element["href"] && $this->hrefNeeded($m))
			{
				$element["href"] = $m->getHref();
			}
		}

		if ($element["position"] < 0)
		{
			$element["position"] += count($this->elements) + 1;
		}

		if ($element["wordWrap"])
		{
			$element["title"] = trim(word_wrap($element["title"], diConfiguration::get("page_title_word_max_len"), " "));
		}

		array_splice($this->elements, $element["position"], 0, array($element));

		return $this;
	}

	public function update($index, $options = array())
	{
		if ($index < 0)
		{
			$index += count($this->elements);
		}

		if (isset($this->elements[$index]))
		{
			$this->elements[$index] = extend($this->elements[$index], $options);
		}

		return $this;
	}

	public function finish()
	{
		$ar = array();

		foreach ($this->elements as $element)
		{
			$ar[] = $this->getTpl()
				->assign(array(
					"TITLE" => $element["title"],
					"HREF" => ($element["hrefPrefixNeeded"] ? $this->getZ()->getLanguageHrefPrefix() : "") . $element["href"],
					"CLASS" => $element["class"],
				), "TT_")
				->parse("TOP_TITLE_ELEMENT", $element["href"] ? "top_title_href" : "top_title_nohref");
		}

		$this->getTpl()->assign(array(
			"TOP_TITLE" => join($this->divider, $ar),
		));

		if ($this->getZ()->needToPrintBreadCrumbs())
		{
			$this->getTpl()->parse("TOP_TITLE_DIV", "top_title_div");
		}

		return $this;
	}
}