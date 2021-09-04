<?php

namespace diCore\Admin;

use diCore\Admin\Data\Skin;
use diCore\Data\Config;
use diCore\Traits\BasicCreate;

class Caption
{
	use BasicCreate;

	/** @var \diCore\Admin\Base */
	private $X;
	/** @var string */
	protected $delimiter = '<s>/</s>';

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
		$href = $this->getX()->getCurrentPageUri('list');

		if (is_array($caption)) {
			$caption = $caption[$this->getX()->getLanguage()];
		}

		return $this->getX()->getPage()->linkNeededInCaption($method)
			? sprintf('<a href="%s">%s</a>', $href, $caption)
			: sprintf('<i>%s</i>', $caption);
	}

	public function get()
	{
		if ($this->getX()->getPage()) {
		    $methodCaption = $this->getX()->getPage()->getCurrentMethodCaption();

			$ar = [];
			$ar[] = $this->getModuleCaptionHtml();

			if ($methodCaption) {
                $ar[] = sprintf('<i>%s</i>', $methodCaption);
            }

			return join($this->delimiter, array_filter($ar));
		} else {
			return $this->oldGet();
		}
	}

	/** @deprecated */
	public function oldGet()
	{
		global $admin_captions_ar;

		$no_caption = [
			'en' => 'This module title is not defined. Please contact administrator.',
			'ru' => 'Заголовок для этого раздела не определен. Свяжитесь с администратором.',
		];

		$path = $this->getX()->getOldSchoolPath($this->getX()->getModule(), $this->getX()->getMethod());

		if (isset($admin_captions_ar[$this->getX()->getLanguage()][$path])) {
			$s = $admin_captions_ar[$this->getX()->getLanguage()][$path];

			if (is_array($s)) {
				$action = (int)$this->getX()->getId() ? 'edit' : 'add';

				$s = $s[$action];
				$x = strpos($s, ' / ');

				if ($x !== false) {
					$href = $this->getX()->getCurrentPageUri('list');

					$s = sprintf('<a href="%s">%s</a>%s', $href, substr($s, 0, $x), substr($s, $x));
				}
			}

			return $s;
		}

		return $no_caption[$this->getX()->getLanguage()];
	}

	private function addButtonNeeded()
	{
		if (!$this->getX()->getPage()) {
			return false;
		}

		return $this->getX()->getPage()->addButtonNeededInCaption() &&
			$this->getX()->getPage()->getMethodCaption('add') &&
			$this->getX()->getRefinedMethod() == 'list';
	}

	public function hasButtons()
    {
        global $admin_captions_ar;

        return
            $this->addButtonNeeded() ||
            // back compatibility
            isset($admin_captions_ar[$this->getX()->getLanguage()][$this->getX()->getPath() . '_form']['add']);
    }

	public function getButtons()
	{
	    if (!$this->hasButtons()) {
            return '';
        }

        $params = $this->getX()->getPage()->getAddButtonUrlQueryParams();
	    $href = $this->getX()->getCurrentPageUri('form', $params);
	    $title = $this->getX()->getVocabulary('add');
        $tag = "<a href=\"{$href}\" class=\"simple-button\">{$title}</a>";

        return $tag;
	}

	public function __toString()
	{
		return $this->get();
	}
}
