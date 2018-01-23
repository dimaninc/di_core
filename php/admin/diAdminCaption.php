<?php
class diAdminCaption
{
    /** @var \diCore\Admin\Base */
	private $X;

	private $delimiter = " / ";

	public function __construct($X)
	{
		$this->X = $X;
	}

	protected function getX()
	{
		return $this->X;
	}

	protected function getModuleCaptionHtml()
	{
		$method = $this->getX()->getRefinedMethod();
		$caption = $this->getX()->getPage()->getModuleCaption();
		$href = $this->getX()->getCurrentPageUri("list");

		if (is_array($caption))
		{
			$caption = $caption[$this->getX()->getLanguage()];
		}

		return $method != "list"
			? sprintf('<a href="%s">%s</a>', $href, $caption)
			: $caption;
	}

	public function get()
	{
		if ($this->getX()->getPage())
		{
			$ar = [];
			$ar[] = $this->getModuleCaptionHtml();
			$ar[] = $this->getX()->getPage()->getCurrentMethodCaption();

			return join($this->delimiter, array_filter($ar));
		}
		else
		{
			return $this->oldGet();
		}
	}

	/** @deprecated */
	public function oldGet()
	{
		global $admin_captions_ar;

		$no_caption = [
			"en" => "This module title is not defined. Please contact administrator.",
			"ru" => "Заголовок для этого раздела не определен. Свяжитесь с администратором.",
		];

		$path = $this->getX()->getOldSchoolPath($this->getX()->getModule(), $this->getX()->getMethod());

		if (isset($admin_captions_ar[$this->getX()->getLanguage()][$path]))
		{
			$s = $admin_captions_ar[$this->getX()->getLanguage()][$path];

			if (is_array($s))
			{
				$action = (int)$this->getX()->getId() ? "edit" : "add";

				$s = $s[$action];

				$x = strpos($s, " / ");
				if ($x !== false)
				{
					$href = $this->getX()->getCurrentPageUri("list");

					$s = "<a href=\"$href\">".substr($s, 0, $x)."</a>".substr($s, $x);
				}
			}

			return $s;
		}

		return $no_caption[$this->getX()->getLanguage()];
	}

	private function addButtonNeeded()
	{
		if (!$this->getX()->getPage())
		{
			return false;
		}

		return $this->getX()->getPage()->addButtonNeededInCaption() &&
			$this->getX()->getPage()->getMethodCaption("add") &&
			$this->getX()->getRefinedMethod() == "list";
	}

	public function getButtons()
	{
		global $admin_captions_ar;

		if (
			$this->addButtonNeeded() ||
			isset($admin_captions_ar[$this->getX()->getLanguage()][$this->getX()->getPath()."_form"]["add"]) // back compatibility
		   )
		{
		    $href = $this->getX()->getCurrentPageUri("form");

			return "[ <a href=\"$href\">{$this->getX()->getVocabulary('add')}</a> ]";
		}
		else
		{
			return "";
		}
	}

	public function __toString()
	{
		return $this->get();
	}
}
