<?php

use diCore\Admin\BasePage;
use diCore\Base\CMS;
use diCore\Helper\ArrayHelper;

class diAdminList
{
	/** @var diNiceTable */
	private $T;

	/** @var BasePage */
	private $AdminPage;

	/** @var array */
	private $columnsAr = [];

	/** @var bool */
	private $columnsInitiated = false;

	/** @var array */
	private $printParts;

	/** @var array */
	private $htmlAr;

	/** @var array */
	private $replaceAr;

	/** @var diModel */
	private $curModel;

	/** @var array */
	private $options;

    protected static $language = 'ru';

    public static $lngStrings = [
        'en' => [
            'open' => 'Open',
            'choose_action' => 'Choose action with selected rows',
            'copy' => 'Copy',
            'move' => 'Move',
            'delete' => 'Delete',
            'toggle_all' => 'Toggle all',
            'notes_about_children' => 'Warning: if selected row has subrows, all actions will be applied to them as well',
        ],

        'ru' => [
            'open' => 'Открыть',
            'choose_action' => 'Операции с выделенными строками',
            'copy' => 'Копировать',
            'move' => 'Переместить',
            'delete' => 'Удалить',
            'toggle_all' => 'Выделить/снять все',
            'notes_about_children' => 'Внимание, если у выделенной строки имеются подразделы, то все действия автоматически будут примененыи и для них',
        ],
    ];

	public function __construct(BasePage $AdminPage, $options = [])
	{
		$this->AdminPage = $AdminPage;

		$this->htmlAr = [
			'head' => '',
			'body' => '',
			'foot' => '',
		];

		$this->options = extend([
			'showControlPanel' => false,
			'showHeader' => true,
			'formBasePath' => null,
		], $options);

		$this->printParts = array_keys($this->htmlAr);

		$this->T = new \diNiceTable(
		    $this,
			$this->getAdminPage()->getPagesNavy(false),
			$this->getAdminPage()->getLanguage()
		);

		if ($this->options['formBasePath']) {
			$this->T->setFormPathBase($this->options['formBasePath']);
		}

        self::$language = $AdminPage->getLanguage();
	}

    public static function L($token, $language = null)
    {
        $language = $language ?: self::$language;

        return self::$lngStrings[$language][$token] ?? $token;
    }

	public function getAdminPage()
	{
		return $this->AdminPage;
	}

	public function getOption($name)
	{
		return isset($this->options[$name]) ? $this->options[$name] : null;
	}

	public function getTable()
	{
		return $this->getAdminPage()->getTable() ?: $this->T->getTable();
	}

	public function addColumns($ar)
	{
		$this->columnsAr = extend($this->columnsAr, $ar);

		return $this;
	}

	public function columnExists($name)
	{
		return isset($this->columnsAr[$name]);
	}

	/**
	 * @param string|array $names
	 * @param string|array $attr
	 * @param mixed|null $value
	 * @return $this
	 */
	public function setColumnAttr($names, $attr, $value = null)
	{
		if (!is_array($names)) {
			$names = [$names];
		}

		foreach ($names as $name) {
			if (!$this->columnExists($name)) {
				$this->columnsAr[$name] = [];
			}

			if (!is_array($this->columnsAr[$name])) {
				$this->columnsAr[$name] = [
					'title' => $this->columnsAr[$name],
				];
			}

			if (!is_array($attr)) {
				$attr = [
					$attr => $value,
				];
			}

			$this->columnsAr[$name] = extend($this->columnsAr[$name], $attr);
		}

		return $this;
	}

	public function setColumnWidth($names, $width, $head = true, $body = false)
    {
        $ar = [];

        if ($head) {
            $ar['headAttrs'] = [
                'width' => $width,
            ];
        }

        if ($body) {
            $ar['bodyAttrs'] = [
                'width' => $width,
            ];
        }

        if ($ar) {
            $this->setColumnAttr($names, $ar);
        }

        return $this;
    }

	public function removeColumn($names)
	{
		if (!is_array($names))
		{
			$names = [$names];
		}

		foreach ($names as $name)
		{
			if (isset($this->columnsAr[$name]))
			{
				unset($this->columnsAr[$name]);
			}
		}

		return $this;
	}

	public function replaceColumn($name, array $newColumns)
	{
		$this
			->insertColumnsBefore($name, $newColumns)
			->removeColumn($name);

		return $this;
	}

	public function renameColumn($name, $newName)
	{
		$this
			->insertColumnsBefore($name, [$newName => $this->columnsAr[$name]])
			->removeColumn($name);

		return $this;
	}

	public function insertColumnsBefore($name, array $newColumns)
	{
		$this->columnsAr = ArrayHelper::addItemsToAssocArrayBeforeKey($this->columnsAr, $name, $newColumns);

		return $this;
	}

	public function insertColumnsAfter($name, array $newColumns)
	{
		$this->columnsAr = ArrayHelper::addItemsToAssocArrayAfterKey($this->columnsAr, $name, $newColumns);

		return $this;
	}

	public function getFieldTitle($name)
	{
		return $this->getAdminPage()->doesFieldExist($name)
			? $this->getAdminPage()->getFieldProperty($name, 'title')
                ?: \diCore\Admin\Form::getFieldTitle($name, $this->getAdminPage()->getFieldProperty($name), $this->getAdminPage()->getLanguage())
			: null;
	}

	private function initColumns()
	{
		foreach ($this->columnsAr as $name => $properties)
		{
		    if (is_string($properties) && $properties)
		    {
			    $properties = [
				    'title' => $properties,
			    ];
		    }

			$p = extend([
				'title' => $this->getFieldTitle($name),
				'attrs' => [],
				'headAttrs' => [],
			], $properties);

			$this->T->addColumn($p['title'], array_merge($p['attrs'], $p['headAttrs']));
		}

		$this->columnsInitiated = true;

		return $this;
	}

    private function prepareReplaceAr()
    {
        $this->replaceAr = [];

        foreach ($this->getCurRec() as $k => $v) {
            if (is_scalar($v) || !$v) {
                $this->replaceAr['%' . $k . '%'] = $v ?: '';
            }
        }

        return $this;
    }

	private function replaceValues($s)
	{
		return str_replace(array_keys($this->replaceAr), array_values($this->replaceAr), $s);
	}

	private function buildHref($href)
	{
		if (is_array($href))
		{
		    if (!isset($href['method']))
		    {
		    	$href['method'] = 'list';
		    }

			$href = \diCore\Admin\Base::getPageUri($href['module'], $href['method'], $href['params']);
		}

		return $href;
	}

	protected function setCurRec($r)
	{
		$this->curModel = $r instanceof \diModel
			? $r
			: \diModel::createForTableNoStrict($this->getTable(), $r);

		return $this;
	}

	public function getCurRec()
	{
		return (object)$this->getCurModel()->get();
	}

	public function getCurModel()
	{
		return $this->curModel ?: new \diModel();
	}

	public function addRow($r, $options = [])
	{
		if (!$this->columnsInitiated)
	    {
	    	$this->initColumns();
	    }

		$this
            ->setCurRec($r)
            ->prepareReplaceAr();

		$html = '';
		$html .= $this->T->openRow($r, $options);

		foreach ($this->columnsAr as $name => $properties)
		{
		    // converting callbacks to its resulting arrays
		    if (isset($properties['headAttrs']) && is_callable($properties['headAttrs']))
            {
                $properties['headAttrs'] = $properties['headAttrs']($r);
            }

            if (isset($properties['bodyAttrs']) && is_callable($properties['bodyAttrs']))
            {
                $properties['bodyAttrs'] = $properties['bodyAttrs']($r);
            }

            if ($name == 'id')
			{
				$html .= $this->T->idCell(true, false, false);
			}
			elseif ($name == '_checkbox' || $name == '#checkbox')
			{
				$p = extend([
					'active' => true,
				], $properties);

				if (is_callable($p['active']))
				{
					$p['active'] = $p['active']($this->getCurModel(), $name);
				}

				$html .= $this->T->idCell(false, $p['active'], false);
			}
			elseif ($name == '_expand' || $name == '#expand')
			{
				$p = extend([
					'active' => true,
				], $properties);

				if (is_callable($p['active']))
				{
					$p['active'] = $p['active']($this->getCurModel(), $name);
				}

				$html .= $this->T->idCell(false, false, $p['active']);
			}
			elseif (substr($name, 0, 1) == '#')
			{
				$name = substr($name, 1);
				$isToggle = CMS::isFieldToggle($name);
				$method = camelize(($isToggle ? 'toggle' : $name) . '_btn_cell');

				switch ($name)
				{
					case 'create':
						$p = extend([
							'maxLevelNum' => null,
							'hrefSuffix' => null,
						], $properties);

						$html .= $this->T->$method($p['maxLevelNum'], $p['hrefSuffix']);
						break;

					case 'href':
						$p = extend([
							'href' => null,
						], $properties);

						if ($p['href'] === null)
						{
							$p['href'] = $this->getCurModel()->getHref();
						}
						else
						{
							if (is_callable($p['href']))
							{
								$p['href'] = $p['href']($this->getCurModel(), $name);
							}

							$p['href'] = $this->replaceValues($p['href']);
						}

						$html .= $this->T->hrefCell($p['href']);
						break;

					case 'manage':
						$p = extend([
							'href' => null,
							'icon' => null,
						], $properties);

						$html .= $this->T->$method($this->replaceValues($this->buildHref($p['href'])), $p['icon']);
						break;

					default:
						$p = extend([
							'active' => true,
							'href' => '',
							'onclick' => '',
						], $properties);

						if (is_callable($p['active']))
						{
							$p['active'] = $p['active']($this->getCurModel(), $name);
						}

						$p['href'] = $this->replaceValues($p['href']);
						$p['onclick'] = $this->replaceValues($p['onclick']);

						$html .= $isToggle
							? $this->T->$method($name, $p['active'])
							: ($p['active'] ? $this->T->$method($p) : $this->T->emptyBtnCell());
						break;
				}
			} else {
				$p = extend([
					'bodyAttrs' => [],
				], $properties);

				$method = empty($properties['noLink']) && empty($properties['noHref'])
                    ? 'textLinkCell'
                    : 'textCell';

				if (empty($properties['value'])) {
					$value = '%' . $name . '%';
				} else {
					$value = $properties['value'];

					if (is_callable($value)) {
						$value = $value($this->getCurModel(), $name, $this);
					}
				}

				$value = $this->replaceValues($value);

				if (!isset($p['bodyAttrs']['data-field'])) {
					$p['bodyAttrs']['data-field'] = $name;
				}

				foreach ($p['bodyAttrs'] as $k => $attr) {
				    if ($attr instanceof Closure) {
                        $p['bodyAttrs'][$k] = $attr($this->getCurModel(), $name, $this);
                    }
                }

				$html .= $this->T->$method($value, $p['bodyAttrs']);
			}
		}

		$html .= $this->T->closeRow();

		$this->htmlAr['body'] .= $html;

		return $this;
	}

	public function setPrintParts($what = ['head', 'body'])
	{
		if (!is_array($what))
		{
			$what = explode(',', $what);
		}

		$this->printParts = $what;

		return $this;
	}

	private function wrap($html)
	{
		return
			$this->T->openTable($this->getOption('showHeader') ? diNiceTable::PRINT_HEADLINE : diNiceTable::NO_HEADLINE) .
			$html .
			$this->T->closeTable();
	}

	protected function getControlPanelHtml()
	{
		if (!$this->getOption('showControlPanel'))
		{
			return '';
		}

		return $this->getAdminPage()->getTwig()->getAssigned('list_control_panel') ?:
			$this->getAdminPage()->getTwig()->parse('admin/_index/list/control_panel', [
				'table' => $this->getAdminPage()->getTable(),
				'List' => $this,
			]);
	}

	public function getHtml()
	{
		$this->getAdminPage()->getTpl()
			->assign([
				'LIST_CONTROL_PANEL' => $this->getControlPanelHtml(),
			]);

		$html = '';

		foreach ($this->printParts as $what)
		{
			$html .= $this->htmlAr[$what];
		}

		$html = $this->wrap($html);

		return $html;
	}
}