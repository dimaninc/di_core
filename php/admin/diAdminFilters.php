<?php
/*
    // dimaninc

    // 2012/10/19
        * timestamp (datetime_str) support added

    // 2011/05/06
        * ::possible_sortby_ar added

    // 2011/04/20
        * js datetime picker support added
        * date range selects naming changed
        * ::applied_date added

    // 2011/04/18
        * auto detection of date range improved

    // 2011/04/15
        * default value #2 added (for date ranges)

    // 2010/12/22
        * default values added

    // 2010/11/29
        * ::title added
        * ::static_inputs_ar added
        * ::values_ar added
        * ::get_static_form() added
        * ::set_static_input() added

    // 2010/11/07
        * ::andor added

    // 2009/07/08
        * lots of additions

    // 2009/05/28
        * ::add_where_condition()

    // 2009/02/13
        * birthday
*/

class diAdminFilters
{
	const DEFAULT_WHERE_TPL = "[-field-]='[-value-]'";

	public static $dirAr = [
		"ASC" => "По возрастанию",
		"DESC" => "По убыванию",
	];

	public static $dateRangeAr = [
		"d1" => "mday",
		"m1" => "mon",
		"y1" => "year",
		"d2" => "mday",
		"m2" => "mon",
		"y2" => "year",
	];

	public static $lngStrings = [
		'en' => [
			'form.submit.title' => 'Apply filter',
			'form.reset.title' => 'Reset',
		],

		'ru' => [
			'form.submit.title' => 'Применить фильтр',
			'form.reset.title' => 'Сбросить',
		],
	];

	public $table;

	/** @var diDB */
	private $db;

	/** @var diAdminBasePage */
	private $AdminPage;

	/**
	 * @var array
	 */
	protected $buttonOptions = [];

	private $notes = [];

	protected $language = 'ru';

	public $ar = [];
	public $applied_date = false;
	private $predefinedData = [];
	public $data = [];
	public $sortby = "";
	public $dir = "";
	public $default_sortby = "";
	public $default_dir = "";
	public $where = "";
	public $where_ar = [];
	public $inputs_ar = [];
	public $input_params_ar = [];
	public $andor = "and";
	public $static_inputs_ar = [];
	public $values_ar = [];
	public $possible_sortby_ar = false;
	public $reset = false;

	protected $sortable = true;

	private $buttonsPrefix = null;
	private $buttonsSuffix = null;

	public function __construct($table, $sortby = "id", $dir = "ASC", $possible_sortby_ar = false)
	{
		global $db;

		if (gettype($table) == "object")
		{
			$this->AdminPage = $table;

			$this->table = $this->AdminPage->getTable();
			$this->language = $this->AdminPage->getAdmin()->getLanguage();
		}
		else
		{
			$this->table = $table;
		}

		$this->db = $db;

		$this->applied_date = isset($_COOKIE["admin_filter_applied"][$this->table]) ? (int)$_COOKIE["admin_filter_applied"][$this->table] : false;

		$this->possible_sortby_ar = is_string($possible_sortby_ar) ? explode(",", $possible_sortby_ar) : $possible_sortby_ar;
		$this->set_default_sorter($sortby, $dir);

		if (!empty($_GET["__diaf_reset"]))
		{
			$this->reset = true;
		}
	}

	public function setSortableState($state)
	{
		$this->sortable = $state;

		return $this;
	}

	public function getSortableState()
	{
		return $this->sortable;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getSortBy()
	{
		return $this->sortby;
	}

	public function getDir()
	{
		return $this->dir;
	}

	public function getData($field)
	{
		return isset($this->data[$field]) ? $this->data[$field] : null;
	}

	public function setData($field, $value)
	{
		$this->data[$field] = $value;

		return $this;
	}

	public function getPredefinedData($field)
	{
		return isset($this->predefinedData[$field]) ? $this->predefinedData[$field] : null;
	}

	public function setPredefinedData($field, $value)
	{
		$this->predefinedData[$field] = $value;

		return $this;
	}

	/** @deprecated */
	public function set_input($field, $input)
	{
		return $this->setInput($field, $input);
	}

	public function setInput($field, $input)
	{
		$this->inputs_ar[$field] = $input;

		if (gettype($input) == "object" && get_class($input) == "diSelect")
		{
			/** @var $input diSelect */

			// setting clean name for 'get' submit
			if (strpos($input->getAttr("name"), "admin_filter[") === 0)
			{
				$input->setAttr("name", substr($input->getAttr("name"), 13, -1));
			}

			// getting first option
			if (!empty($this->ar[$field]["strict"]))
			{
				$this
					->setPredefinedData($field, $input->getItem(0, "value"))
					->buildQuery();
			}
		}

		return $this;
	}

	public function set_static_input($field, $input)
	{
		$this->static_inputs_ar[$field] = $input;

		return $this;
	}

	public function setNote($field, $note)
	{
		$this->notes[$field] = $note;

		return $this;
	}

	public function getNote($field)
	{
		return isset($this->notes[$field]) ? $this->notes[$field] : null;
	}

	private function getFieldHtml($title, $input)
	{
		return "<b>$title:</b> $input";
	}

	private function getRowHtml($html)
	{
		return "<div class=\"row\">$html</div>";
	}

	public function getBlockHtml()
	{
		if ($this->getInput("sortby"))
		{
			$sorterBlock = $this->getRowHtml($this->getFieldHtml("Сортировать", $this->getInput("sortby")." ".$this->getInput("dir")));

			if ($this->getNote("sortby"))
			{
				$sorterBlock .= $this->getRowHtml($this->getNote("sortby"));
			}
		}
		else
		{
			$sorterBlock = "";
		}

		$filterRowsAr = [];

		foreach ($this->ar as $a)
		{
			$filterRowsAr[] = $this->getRowHtml($this->getFieldHtml($a["title"], $this->getInput($a["field"])));

			if ($this->getNote($a["field"]))
			{
				$filterRowsAr[] = $this->getRowHtml($this->getNote($a["field"]));
			}
		}

		$filterRows = join("\n", $filterRowsAr);

		return <<<EOF
<form name="admin_filter_form[{$this->table}]" method="get" action="">
<div class="filter-block">

	{$filterRows}

	{$sorterBlock}

	{$this->get_buttons_block()}

</div>
</form>
EOF;
	}

	public static function get_user_id_where($userFields = ["name", "login", "email"])
	{
		$condition = join(" or ", array_map(function($field) {
			return "INSTR($field,'[-value-]')>'0'";
		}, $userFields));

		return "([-field-]>'0' and ([-field-]='[-value-]' or [-field-] in (SELECT id FROM users WHERE $condition)))";
	}

	public function getFilter($field)
	{
		return isset($this->ar[$field]) ? $this->ar[$field] : null;
	}

	public function getFilters()
	{
		return $this->ar;
	}

	/** @deprecated */
	public function add_filter($field, $type = "str", $where_tpl = null, $title = "", $default_value = null, $default_value2 = null)
	{
		return $this->addFilter($field, $type, $where_tpl, $title, $default_value, $default_value2);
	}

	// $where_tpl could be string w tokens: [-field-], [-value-]
	// or a function($field, $value) which returns string for WHERE condition
	public function addFilter($field, $type = "str", $where_tpl = null, $title = "", $default_value = null, $default_value2 = null)
	{
		$opts = [
			"field" => is_array($field) ? "" : $field,
			"type" => $type,
			"where_tpl" => $where_tpl,
			"title" => $title,
			"default_value" => $default_value,
			"default_value2" => $default_value2,
			"strict" => false,
			"value" => false,
			"not" => false,
			'queryPrefix' => '',
			'querySuffix' => '',
		];

		if (is_array($field))
		{
			$opts = extend($opts, $field);
		}

		if ($opts["where_tpl"] === null)
		{
			$opts["where_tpl"] = self::DEFAULT_WHERE_TPL;
		}

		$this->ar[$opts["field"]] = $opts;

		return $this;
	}

	public function add_where_condition($condition)
	{
		$this->where_ar[] = $condition;

		return $this;
	}

	public function set_default_sorter($sortby, $dir = null)
	{
		if (is_array($sortby) && is_null($dir))
		{
			$dir = $sortby["dir"];
			$sortby = $sortby["sortBy"];
		}

		$this->default_sortby = $sortby;
		$this->default_dir = $dir;

		$this->sortby = $sortby;
		$this->dir = $dir;

		return $this;
	}

	public function get_js_data($print_script_tags = false)
	{
		$ar = array();

		foreach ($this->ar as $a)
		{
			if (in_array($a["type"], array("date_range", "date_str_range")))
			{
				foreach (self::$dateRangeAr as $_f => $_r)
				{
					$_ff = "d".$_f{0};
					$_idx = $_f{1};

					$ar[] = "{$a["field"]}][{$_idx}][{$_ff}";
				}
			}
			else
			{
				$ar[] = $a["field"];
			}
		}

		if ($this->getSortableState())
		{
			$ar[] = "sortby";
			$ar[] = "dir";
		}

		if ($print_script_tags)
		{
			$s = "<script type=\"text/javascript\">$(function() { ".
				"var diAF = new diAdminFilters({table: '".$this->getTable()."', fields: ['".join("','", $ar)."']});".
				" });</script>\n";
		}
		else
		{
			$s = "<script type=\"text/javascript\" src=\"_js/filters.js\"></script>\n".
				"filters_ar['{$this->table}'] = ['".join("','", $ar)."'];\n";
		}

		return $s;
	}

	public function setButtonOptions($options)
	{
		$this->buttonOptions = extend($this->buttonOptions, $options);

		return $this;
	}

	public function setButtonsPrefix($prefix)
	{
		$this->buttonsPrefix = $prefix;

		return $this;
	}

	public function setButtonsSuffix($suffix)
	{
		$this->buttonsSuffix = $suffix;

		return $this;
	}

	public function getButtonsPrefix()
	{
		return $this->buttonsPrefix;
	}

	public function getButtonsSuffix()
	{
		return $this->buttonsSuffix;
	}

	public function get_buttons_block($opts = [])
	{
		$opts = extend([
			"prefix" => $this->getButtonsPrefix(),
			"suffix" => $this->getButtonsSuffix(),
		], $this->buttonOptions, (array)$opts);

		return <<<EOF
<div class="buttons">
{$opts["prefix"]}
	<button type="submit" class="violet">{$this->L('form.submit.title')}</button>
	<button type="button" class="gray" data-purpose="reset">{$this->L('form.reset.title')}</button>
{$opts["suffix"]}
</div>
EOF;
	}

	public function get_where($table_prefix = "")
	{
		$this->buildQuery($table_prefix);

		return $this->where;
	}

	public function getQuery()
	{
		return $this->where;
	}

	public function buildQuery($table_prefix = "")
	{
		// sorter
		if (!$this->reset && isset($_COOKIE["admin_filter"][$this->table]["sortby"]))
			$this->sortby = str_in(strtolower($_COOKIE["admin_filter"][$this->table]["sortby"]));

		if (!$this->reset && isset($_COOKIE["admin_filter"][$this->table]["dir"]))
			$this->dir = str_in(strtoupper($_COOKIE["admin_filter"][$this->table]["dir"]));

		if (isset($_GET["sortby"]))
			$this->sortby = str_in(strtolower($_GET["sortby"]));

		if (isset($_GET["dir"]))
			$this->dir = str_in(strtoupper($_GET["dir"]));

		if (isset($_GET["admin_filter"]["sortby"]))
			$this->sortby = str_in(strtolower($_GET["admin_filter"]["sortby"]));

		if (isset($_GET["admin_filter"]["dir"]))
			$this->dir = str_in(strtoupper($_GET["admin_filter"]["dir"]));

		if ($this->possible_sortby_ar !== false)
		{
			if (!in_array($this->sortby, $this->possible_sortby_ar))
				$this->sortby = $this->default_sortby;
		}

		if (!in_array($this->dir, array("ASC","DESC")))
			$this->dir = $this->default_dir;
		//

		$where_ar = $this->where_ar;

		foreach ($this->ar as $idx => $a)
		{
			$value = !$this->reset && isset($_COOKIE["admin_filter"][$this->table][$a["field"]])
				? is_array($_COOKIE["admin_filter"][$this->table][$a["field"]]) ? $_COOKIE["admin_filter"][$this->table][$a["field"]] : urldecode($_COOKIE["admin_filter"][$this->table][$a["field"]])
				: $this->getPredefinedData($a["field"]);

			$value = diRequest::get($a["field"], $value);

			$value = isset($_GET["admin_filter"][$a["field"]])
				? $_GET["admin_filter"][$a["field"]]
				: $value;

			if (in_array($a["type"], ["date_range", "date_str_range"]))
			{
				$value = [];
			}

			if ($value === null && $a["default_value"] !== null)
			{
				$value = $a["default_value"];
			}

			if ($value !== null)
			{
				switch ($a["type"])
				{
					case "int":
					case "float":
					case "double":
						if ($value && $value{0} == "!")
						{
							$a["not"] = $this->ar[$idx]["not"] = true;
							$value = substr($value, 1);

							if (!is_callable($a["where_tpl"]))
							{
								$a["where_tpl"] = str_replace("=", "!=", $a["where_tpl"]);
							}
						}
						break;
				}

				switch ($a["type"])
				{
					case "int":
						$value = intval($value);
						break;

					case "float":
					case "double":
						$value = str_replace(",", ".", $value);
						$value = ${"{$a["type"]}val"}($value);
						break;

					case "checkboxes":
						if (empty($a["where_tpl"]))
						{
							$a["where_tpl"] = "[-field-] in ([-value-])";
						}
						break;

					case "date_range":
					case "date_str_range":
						$r1 = $a["default_value"] === null ? $this->getDb()->r($this->table, "", "MIN({$a["field"]}) as d1_min") : null; //,MAX($a["field"]) as d1_max

						if ($a["type"] == "date_str_range")
						{
							$r1->d1_min = strtotime($r1->d1_min);
						}

						$t1 = $a["default_value"] !== null ? $a["default_value"] : ($r1 && $r1->d1_min ? $r1->d1_min : time() - (date("d") - 1) * SECS_PER_DAY); // 1st day of current month
						$t2 = $a["default_value2"] !== null ? $a["default_value2"] : strtotime("+1 day"); // tomorrow

						$dt1 = getdate($t1);
						$dt2 = getdate($t2);

						foreach (self::$dateRangeAr as $_f => $_r)
						{
							$_ff = "d".$_f{0};
							$_idx = $_f{1};

							$value[$_f] = !$this->reset && isset($_COOKIE["admin_filter"][$this->table]["{$a["field"]}"][$_idx][$_ff])
								? (int)$_COOKIE["admin_filter"][$this->table]["{$a["field"]}"][$_idx][$_ff]
								: ($_f{1} == "1" ? $dt1[$_r] : $dt2[$_r]);
						}

						$value["timestamp1"] = $value["d1"] && $value["m1"] && $value["y1"] ? mktime(0,0,0,$value["m1"],$value["d1"],$value["y1"]) : 0;
						$value["timestamp2"] = $value["d2"] && $value["m2"] && $value["y2"] ? mktime(23,59,59,$value["m2"],$value["d2"],$value["y2"]) : 0;

						break;

					default:
					case "str":
					case "string":
						$value = diStringHelper::in($value);
						break;
				}

				$this->ar[$idx]["value"] = $value;

				if ($value || ($value == "0" && substr($a["type"], 0, 3) == "str"))
				{
					$replace_ar = [
						"[-field-]" => $table_prefix . $a["field"],
						"[-value-]" => $value,
					];

					if (in_array($a["type"], ["date_range", "date_str_range"]) && $a["where_tpl"] == self::DEFAULT_WHERE_TPL)
					{
						if ($a["type"] == "date_range")
						{
							$a["where_tpl"] = "diaf_get_date_range_filter";
						}
						elseif ($a["type"] == "date_str_range")
						{
							$a["where_tpl"] = "diaf_get_date_str_range_filter";
						}
					}

					$w = is_callable($a["where_tpl"])
						? $a["where_tpl"]($a["field"], $value, $a["not"], $table_prefix, $a['queryPrefix'], $a['querySuffix'])
						: str_replace(array_keys($replace_ar), array_values($replace_ar), $a["where_tpl"]);

					if ($w) $where_ar[] = $w;
				}
			}

			$this->data[$a["field"]] = $value;
		}

		$this->where = $where_ar ? "WHERE " . join(" $this->andor ", $where_ar) : "";

		return $this;
	}

	protected function getResetButton($field)
	{
		return "<span data-purpose='reset-filter' data-field='$field'></span>";
	}

	/** @deprecated */
	public function get_input($field)
	{
		return $this->getInput($field);
	}

	public function getInput($field)
	{
		if (isset($this->inputs_ar[$field]))
		{
			return $this->inputs_ar[$field];
		}

		if (isset($this->ar[$field]))
		{
			$ar = $this->ar[$field];

			switch ($ar["type"])
			{
				default:
					if (!$ar["value"] && in_array($ar["type"], ['int', 'float', 'double']))
					{
						$ar["value"] = "";
					}

					switch ($ar['type'])
					{
						case 'int':
						case 'float':
						case 'double':
							$size = 6;
							break;

						default:
							$size = 35;
					}

					$input = "<input id='admin_filter[$field]' name='$field' value=\"{$ar["value"]}\" size='$size'>";

					if (true)
					{
						$input .= $this->getResetButton($field);
					}

					return $input;

				case "date_range":
				case "date_str_range":
					$d1_sel = new diSelect("admin_filter[{$field}][1][dd]", $ar["value"]["d1"]);
					$m1_sel = new diSelect("admin_filter[{$field}][1][dm]", $ar["value"]["m1"]);
					$y1_sel = new diSelect("admin_filter[{$field}][1][dy]", $ar["value"]["y1"]);
					$d2_sel = new diSelect("admin_filter[{$field}][2][dd]", $ar["value"]["d2"]);
					$m2_sel = new diSelect("admin_filter[{$field}][2][dm]", $ar["value"]["m2"]);
					$y2_sel = new diSelect("admin_filter[{$field}][2][dy]", $ar["value"]["y2"]);

					for ($i = 1; $i <= 31; $i++)
					{
						$d1_sel->addItem(lead0($i), lead0($i));
						$d2_sel->addItem(lead0($i), lead0($i));
					}

					for ($i = 1; $i <= 12; $i++)
					{
						$m1_sel->addItem(lead0($i), lead0($i));
						$m2_sel->addItem(lead0($i), lead0($i));
					}

					$r1 = $this->getDb()->r($this->table, "", "MIN($field) as d1_min,MAX($field) as d1_max");

					if ($ar["type"] == "date_str_range")
					{
						$r1->d1_min = strtotime($r1->d1_min);
						$r1->d1_max = strtotime($r1->d1_max);
					}

					$y1 = date("Y") - 1;
					if ($ar["value"]["y1"] < $y1) $y1 = $ar["value"]["y1"];
					if ($r1 && date("Y", $r1->d1_min) < $y1) $y1 = date("Y", $r1->d1_min);

					$y2 = date("Y") + 3;
					if ($ar["value"]["y2"] > $y2) $y2 = $ar["value"]["y2"];
					if ($r1 && date("Y", $r1->d1_max) > $y2) $y2 = date("Y", $r1->d1_max);

					for ($i = $y1; $i <= $y2; $i++)
					{
						$y1_sel->addItem($i, $i);
						$y2_sel->addItem($i, $i);
					}

					$s = $d1_sel . "." . $m1_sel . "." . $y1_sel . " &ndash; " .
						$d2_sel . "." . $m2_sel . "." . $y2_sel;

					// js
					$uid = substr(get_unique_id(), 0, 8);

					$calendar_cfg_js = "months_to_show: 2, date1: 'admin_filter[{$field}][1]', date2: 'admin_filter[{$field}][2]', able_to_go_to_past: true";

					$s .= " <button type=button onclick=\"c_{$uid}.toggle();\">Календарь</button>" .
						"<script type=\"text/javascript\">var c_{$uid} = new diCalendar({instance_name: 'c_{$uid}', $calendar_cfg_js});</script>";
					//

					return $s;
			}
		}

		return null;
	}

	public function set_input_params($field, $params_ar = array())
	{
		if (!isset($this->input_params_ar[$field]))
			$this->input_params_ar[$field] = array();

		$this->input_params_ar[$field] = array_merge($this->input_params_ar[$field], $params_ar);

		return $this;
	}

	public function get_idx_by_field($field)
	{
		foreach ($this->ar as $idx => $ar)
		{
			if ($ar["field"] == $field)
				return $idx;
		}

		return null;
	}

	/** @deprecated */
	public function set_select_from_db_input($field, $db_rs, $template_text = "%title%", $template_value = "%id%",
	                                         $prefix_ar = [], $suffix_ar = [])
	{
		return $this->setSelectFromDbInput($field, $db_rs, $template_text, $template_value, $prefix_ar, $suffix_ar);
	}

	public function setSelectFromDbInput($field, $db_rs, $template_text = "%title%", $template_value = "%id%",
	                                     $prefix_ar = [], $suffix_ar = [])
	{
		if (is_array($template_text))
		{
			$prefix_ar = $template_text;
			$template_text = "%title%";
		}

		if (is_array($template_value))
		{
			$suffix_ar = $template_value;
			$template_value = "%id%";
		}

		$sel = new diSelect("admin_filter[$field]", $this->data[$field]);

		if (isset($this->input_params_ar[$field]))
		{
			$sel->setAttr($this->input_params_ar[$field]);
		}

		if ($prefix_ar)
		{
			$sel->addItemArray($prefix_ar);
		}

		while ($db_rs && $db_r = $this->getDb()->fetch_array($db_rs))
		{
			$ar1 = [];
			$ar2 = [];

			foreach ($db_r as $k => $v)
			{
				$ar1[] = "%$k%";
				$ar2[] = $v;
			}

			$text = str_replace($ar1, $ar2, $template_text);
			$value = str_replace($ar1, $ar2, $template_value);

			$sel->addItem($value, $text);
		}

		if ($suffix_ar)
		{
			$sel->addItemArray($suffix_ar);
		}

		$this->setInput($field, $sel);

		$this->values_ar[$field] = $sel->getSimpleItemsAr();

		return $this;
	}


	public function setSelectFromCollectionInput($field, diCollection $collection, $format = null, $prefixAr = [], $suffixAr = [])
	{
		if ($format === null || is_array($format))
		{
			if (is_array($format))
			{
				$suffixAr = $prefixAr;
				$prefixAr = $format;
			}

			$format = null;
		}

		$sel = new diSelect("admin_filter[$field]", $this->getData($field));

		if (isset($this->input_params_ar[$field]))
		{
			$sel->setAttr($this->input_params_ar[$field]);
		}

		if ($prefixAr)
		{
			$sel->addItemArray($prefixAr);
		}

		$sel->addItemsCollection($collection, $format);

		if ($suffixAr)
		{
			$sel->addItemArray($suffixAr);
		}

		$this->setInput($field, $sel);

		return $this;
	}

	protected function getValueForField($field)
	{
		switch ($field)
		{
			case "sortby":
				return $this->sortby;

			case "dir":
				return $this->dir;

			default:
				return $this->data[$field];
		}
	}

	/** @deprecated */
	public function set_select_from_array_input($field, $ar, $prefix_ar = [], $suffix_ar = [])
	{
		return $this->setSelectFromArrayInput($field, $ar, $prefix_ar, $suffix_ar);
	}

	public function setSelectFromArrayInput($field, $ar, $prefix_ar = [], $suffix_ar = [])
	{
		$sel = new diSelect("admin_filter[$field]", $this->getValueForField($field));

		if (isset($this->input_params_ar[$field]))
		{
			foreach ($this->input_params_ar[$field] as $_pn => $_pv)
			{
				$sel->setAttr($_pn, $_pv);
			}
		}

		if ($prefix_ar)
		{
			$sel->addItemArray($prefix_ar);
		}

		$sel->addItemArray($ar);

		if ($suffix_ar)
		{
			$sel->addItemArray($suffix_ar);
		}

		$x = $this->get_idx_by_field($field);
		if ($x !== null && $this->ar[$x]["not"])
		{
			foreach ($sel->getItemsAr() as $_k => $_v)
			{
				if ($_v["value"] == $this->data[$field])
				{
					$sel->addItem("!$_k", "НЕ {$_v["text"]}");
					$sel->setCurrentValue("!$_k");

					break;
				}
			}
		}

		$this->setInput($field, $sel);

		$this->values_ar[$field] = $sel->getSimpleItemsAr();

		return $this;
	}

	/** @deprecated */
	public function set_select_from_array2_input($field, $ar)
	{
		return $this->setSelectFromArray2Input($field, $ar);
	}

	public function setSelectFromArray2Input($field, $ar)
	{
		$sel = new diSelect("admin_filter[$field]", $this->data[$field]);

		if (isset($this->input_params_ar[$field]))
		{
			foreach ($this->input_params_ar[$field] as $_pn => $_pv)
			{
				$sel->setAttr($_pn, $_pv);
			}
		}

		$sel->addItemArray2($ar);

		$this->setInput($field, $sel);

		$this->values_ar[$field] = $sel->getSimpleItemsAr();

		return $this;
	}

	/** @deprecated */
	public function set_checkbox_from_array_input($field, $ar, $columns = 1)
	{
		return $this->setCheckboxFromArrayInput($field, $ar, $columns);
	}

	public function setCheckboxFromArrayInput($field, $ar, $columns = 1)
	{
		$ar2 = array();

		foreach ($ar as $k => $v)
		{
			$checked = strpos(",{$this->data[$field]},", ",$k,") !== false ? " checked=\"checked\"" : "";

			if (false && $this->static_mode)
			{
				if ($checked)
				{
					$ar2[] = $v;
				}
			}
			else
			{
				$ar[$k] = "<input type='checkbox' id='diaf_{$field}[$k]' name='{$field}[]' value='$k'$checked onclick=\"diadminfilter_toggle_cb('$field',0);\" /> <label for='diaf_{$field}[$k]' id='diaf_label_{$field}[$k]'>$v</label>";
			}
		}

		if (false && $this->static_mode)
		{
			$table = join(", ", $ar2);
		}
		else
		{
			$table = "<table><tr>";

			$per_column = ceil(count($ar) / $columns);

			for ($i = 0; $i < $columns; $i++)
			{
				$table .= "<td style=\"padding-right: 20px; vertical-align: top;\">".join("<br />", array_slice($ar, $per_column * $i, $per_column))."</td>";
			}

			$table .= "</tr></table>";
		}
		//

		$this->setInput($field, "<input type=hidden id='admin_filter[$field]' value='{$this->data[$field]}'>".$table);

		return $this;
	}

	public function set_checkbox_from_db_input($field, $db_rs, $template_text = "%title%", $template_value = "%id%", $cols_count = 1, $prefix_ar = array(), $suffix_ar = array(), $suffix_buttons_ar = array())
	{
		return $this->setCheckboxFromDbInput($field, $db_rs, $template_text, $template_value, $cols_count, $prefix_ar, $suffix_ar, $suffix_buttons_ar);
	}

	public function setCheckboxFromDbInput($field, $db_rs, $template_text = "%title%", $template_value = "%id%", $cols_count = 1, $prefix_ar = array(), $suffix_ar = array(), $suffix_buttons_ar = array())
	{
		$ar = array();
		$static_ar = array();

		foreach ($prefix_ar as $value => $text)
		{
			$class = " cb_level0";
			$checked = strpos(",{$this->data[$field]},", ",$value,") !== false ? " checked=\"checked\"" : "";

			$inp = "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />";
			$ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";

			if ($checked)
				$static_ar[] = $text;
		}

		while ($db_rs && $db_r = $this->getDb()->fetch_array($db_rs))
		{
			$ar1 = array();
			$ar2 = array();

			foreach ($db_r as $k => $v)
			{
				$ar1[] = "%$k%";
				$ar2[] = $v;
			}

			$text = str_replace($ar1, $ar2, $template_text);
			$value = str_replace($ar1, $ar2, $template_value);

			$class = isset($db_r["level_num"]) ? " cb_level{$db_r["level_num"]}" : "";
			$checked = strpos(",{$this->data[$field]},", ",$value,") !== false ? " checked=\"checked\"" : "";

			if ($checked)
				$static_ar[] = $text;

			$inp = !isset($db_r["level_num"]) || $db_r["level_num"] > 0 ? "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />" : "";
			$ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";
		}

		foreach ($suffix_ar as $value => $text)
		{
			$class = " cb_level0";
			$checked = strpos(",{$this->data[$field]},", ",$value,") !== false ? " checked=\"checked\"" : "";

			$inp = "<input type='checkbox' id='diaf_{$field}[$value]' name='{$field}[]' value='$value'$checked />";
			$ar[] = "<div class=\"cb_level_any{$class}\">$inp <label for='diaf_{$field}[$value]' id='diaf_label_{$field}[$value]'>$text</label></div>";

			if ($checked)
				$static_ar[] = $text;
		}

		$tds_ar = array();
		$per_col = ceil(count($ar) / $cols_count);
		for ($i = 0; $i < $cols_count; $i++)
		{
			$tds_ar[] = "<td valign=top>".join("\n", array_slice($ar, $i * $per_col, $per_col))."</td>";
		}

		$inputs = "<table><tr>".join("\n", $tds_ar)."</tr></table>";
		$static_inputs = $static_ar ? join(", ", $static_ar) : "Нет (выбрать)";

		//this.style.display='none';
		//_ge('static_cb[$field]').style.display='block';

		$buttons_suffix = join("", $suffix_buttons_ar);

		$this->setInput($field, "<input type=hidden id='admin_filter[$field]' value='{$this->data[$field]}'>".
			"<div onclick=\"diadminfilter_toggle_cb('$field',1);\" id=\"static_cb[$field]\" style=\"cursor:pointer;\">$static_inputs</div>".
			"<div id=\"cb[$field]\" style=\"display: none; position: absolute; border: 1px solid #777; padding: 5px; background: #fff;\">".
			"$inputs".
			"<button type=button onclick=\"diadminfilter_toggle_cb('$field',0);\">ОК</button>".
			"<button type=button onclick=\"diadminfilter_close_box('$field');\">Отмена</button>".
			"<button type=button onclick=\"diadminfilter_select_all_cb('$field',1);\">Выделить все</button>".
			"<button type=button onclick=\"diadminfilter_select_all_cb('$field',0);\">Снять выделение</button>".
			$buttons_suffix.
			"</div>"
		);

		return $this;
	}

	public function L($token, $language = null)
	{
		$language = $language ?: $this->language;

		return isset(self::$lngStrings[$language][$token])
			? self::$lngStrings[$language][$token]
			: $token;
	}

  function convert_from_and_to_dates()
  {
    $x = strpos($this->where, "(date BETWEEN");
    if ($x !== false)
    {
      $y = strpos($this->where, ")", $x + 1);

      if ($y !== false)
      {
        $s = substr($this->where, $x, $y - $x + 1);

        $this->where = substr($this->where, 0, $x).
          "(".str_replace("(date ", "(from_date ", $s).
          " or ".
          str_replace("(date ", "(to_date ", $s).")".
          substr($this->where, $y + 1);
      }
    }

	  return $this;
  }

  function get_static_input($field)
  {
    if (isset($this->static_inputs_ar[$field]))
      return $this->static_inputs_ar[$field];

    foreach ($this->ar as $idx => $ar)
    {
      if ($ar["field"] == $field)
      {
        switch ($ar["type"])
        {
          case "date_range":
          case "date_str_range":
            return date(diConfiguration::get("date_format"), $ar["value"]["timestamp1"])." to ".date(diConfiguration::get("date_format"), $ar["value"]["timestamp2"]);
            break;

          default:
            if (!$ar["value"] && in_array($ar["type"], explode(",", "int,float,double")))
              $ar["value"] = "";

            if ($this->values_ar[$field] && isset($this->values_ar[$field][$ar["value"]]))
              return $this->values_ar[$field][$ar["value"]];
            elseif (!$ar["value"] && $ar["value"] !== 0)
              return "No value";
            else
              return $ar["value"];

            break;
        }

        break;
      }
    }

    return "";
  }

  function get_static_form($glue = " ", $skip_fields_with_empty_title = true)
  {
    $ar2 = array();

    foreach ($this->ar as $idx => $ar)
    {
      if (empty($ar["title"]))
      {
        if ($skip_fields_with_empty_title)
          continue;
        else
          $ar["title"] = $ar["field"];
      }

      $ar2[] = "{$ar["title"]}: ".$this->get_static_input($ar["field"]);
    }

    return join($glue, $ar2);
  }

  static function fast_create($table)
  {
    switch ($table)
    {
      case "items":
        //$F = new diAdminFilters($table, "order_num", "ASC");
        $F = new diAdminFilters($table, "date", "DESC");
        //$F->add_filter("date", "date_range");
        $F->addFilter("id", "int");
        $F->addFilter("title", "str", "diaf_substr");
        $F->addFilter("marking", "str", "diaf_substr");
        $F->addFilter("price2", "int", "diaf_price2");
        $F->addFilter("category_id", "int", "'[-value-]' in ([-field-],subcategory_id,type_id)");
        //$F->add_filter("brand_id", "int", "diaf_minus_one");
        //$F->add_filter("character_id", "int", "diaf_minus_one");
        //$F->add_filter("supplier_id", "int", "diaf_minus_one");
        //$F->add_filter("tags", "checkboxes", "diaf_tags");
        $F->addFilter("top", "int", "diaf_minus_one");
        $F->addFilter("spec_template_id", "int");
        $F->addFilter("visible", "int", "diaf_minus_one");
        $F->addFilter("comments_count", "int", "diaf_minus_one2");
        $F->addFilter("votes", "int", "diaf_minus_one2");
        //$F->add_filter("presence", "int", "diaf_minus_one");
        $F->get_where(); //"i."

        break;

      default:
        return false;
    }

    return $F;
  }

	/**
	 * @param $F diAdminFilters
	 * @param $pn diPagesNavy
	 * @param array $o
	 * @return bool|string
	 */
  static function get_html($F, $pn, $o = array())
  {
    $o = (object)extend(array(
      "items_category_strict" => false,
      "items_show_counts" => true,
    ), $o);

    switch ($F->table)
    {
      case "items":
        $q_category_id = "ORDER BY order_num ASC";

        if ($o->items_category_strict && !$F->data["category_id"])
        {
          $r = $F->getDb()->r("categories", $q_category_id);

          $_GET["category_id"] = $r ? $r->id : -1;
          $F->buildQuery();
        }

        //$F->set_select_from_db_input("content_id", $F->getDb()->rs("content", "WHERE type='security' ORDER BY order_num ASC"));
        //$F->set_select_from_db_input("character_id", $F->getDb()->rs("characters", "ORDER BY title ASC"), "%title%", "%id%", array(0 => "Все", -1 => "Без героя",));
        //$F->set_select_from_db_input("brand_id", $F->getDb()->rs("brands", "ORDER BY title ASC"), "%title%", "%id%", array(0 => "Все", -1 => "Без бренда",));
        //$F->set_select_from_db_input("supplier_id", $F->getDb()->rs("suppliers", "ORDER BY title ASC"), "%title%", "%id%", array(0 => "Все", -1 => "Без поставщика",));
        $F->setSelectFromDbInput("spec_template_id", $F->getDb()->rs("spec_templates", "ORDER BY title ASC"), "%title%", "%id%", array(0 => "Все"));

        /*
        $F->set_checkbox_from_db_input("tags", $F->getDb()->rs("tags", "ORDER BY title ASC"),
          "%title%", "%id%", 5, array(-1 => "Без тегов"), array(),
          array("<button onclick=\"diadminfilter.multi_save_tags('$F->table','tags');\" style=\"background: #ffc;\" type=button>Применить для отмеченных товаров</button>")
        );
        */

        $F->setSelectFromArrayInput("price2", array(
          0 => "Все товары",
          1 => "Акционные товары",
          2 => "Неакционные товары",
        ));

        $F->setSelectFromArrayInput("top", array(
          0 => "Все товары",
          1 => "Топ-товары",
          -1 => "Не топ-товары",
        ));

        $F->setSelectFromArrayInput("visible", array(
          0 => "Все товары",
          1 => "Видимые товары",
          -1 => "Невидимые товары",
        ));

        $F->setSelectFromArrayInput("comments_count", array(
          0 => "Все",
          1 => "С комментариями",
          -1 => "Без комментариев",
        ));

        $F->setSelectFromArrayInput("votes", array(
          0 => "Все",
          1 => "С оценками",
          -1 => "Без оценок",
        ));

        /*
        $F->set_select_from_array_input("presence", array(
          0 => "Все товары",
          1 => "Товары в наличии",
          -1 => "Товары не в наличии",
        ));
        */

        // categories
        $cat_ar = array("" => "Все категории");
        $cat_rs = $F->getDb()->rs("categories", $q_category_id);
        while ($cat_r = $F->getDb()->fetch($cat_rs))
        {
          $cat_ar[$cat_r->id] = str_repeat("&nbsp;", $cat_r->level_num * 3).str_out($cat_r->title);
        }

        $F->setSelectFromArrayInput("category_id", $cat_ar);
        //

        // sortby select
        $sel = new diSelect("admin_filter[sortby]", $F->sortby);
        $sel->AddItem("title", "По названию");
        $sel->AddItem("marking", "Артикулу");
        $sel->AddItem("price", "По цене");
        $sel->AddItem("id", "По дате добавления (по ID)");
        $sortby_sel = $sel->CreateHTML();

        $dir_sel = new diSelect("admin_filter[dir]", $F->dir);
        $dir_sel->AddItem("ASC", "По возрастанию");
        $dir_sel->AddItem("DESC", "По убыванию");
        $dir_sel = $dir_sel->CreateHTML();
        //

        $w = $F->where;
        $w .= $w ? " and " : "WHERE ";
        $w .= "visible='1'";
        //$visible_r = $F->getDb()->r("$table i", $w, "COUNT(id) AS cc");
        $visible_r = $F->getDb()->r($F->table, $w, "COUNT(id) AS cc");

        /*
        <div style="padding-top: 10px;"><button type=button class="w_hover" onclick="refresh_table_summary('users');">Обновить</button></div>
        */

        $counts = !$o->items_show_counts ? "" : <<<EOF
  <div style="float: right; padding: 15px 15px; font-size: 20px; background: #B4B0A8;">
    Видимых/всего:
    <div>
      <span id="filter[total_visible_count]">$visible_r->cc</span>
      /
      <span id="filter[total_count]">$pn->total_records</span>
    </div>
  </div>
EOF;
                              //<?=divide3dig(

/*
    <b>Поставщик:</b> {$F->get_input("supplier_id")}
    <b>Метки:</b> <u style="max-width: 400px; display: inline-block;">{$F->get_input("tags")}</u>
    <b>Бренд:</b> {$F->get_input("brand_id")}
    <b>Наличие:</b> {$F->get_input("presence")}
    <b>Герой:</b> {$F->get_input("character_id")}
*/

        $html = <<<EOF
<form name="admin_filter_form[$F->table]" method="get" action="" onsubmit="apply_filter('$F->table'); return false;">
<div class="filter-block">

  $counts

  <div style="padding: 0 0 7px 0;">
    <b>ID:</b> {$F->get_input("id")}
    <b>Наименование:</b> {$F->get_input("title")}
    <b>Артикул:</b> {$F->get_input("marking")}
  </div>

  <div style="padding: 0 0 7px 0;">
    <b>Категория/тип:</b> {$F->get_input("category_id")}
    <b>Шаблон характеристик:</b> {$F->get_input("spec_template_id")}
  </div>

  <div style="padding: 0 0 7px 0;">
    <b>Акции/скидки:</b> {$F->get_input("price2")}
    <b>Топ:</b> {$F->get_input("top")}
    <b>Видимость:</b> {$F->get_input("visible")}
  </div>

  <div style="padding: 0 0 7px 0;">
    <b>Комментарии:</b> {$F->get_input("comments_count")}
    <b>Оценки:</b> {$F->get_input("votes")}
  </div>

  <div style="padding: 0 0 7px 0;">
    <b>Сортировать:</b> $sortby_sel $dir_sel
  </div>

{$F->get_buttons_block()}

</div>
</form>
EOF;

        break;

      default:
        return false;
    }

    return $html.$F->get_js_data(true);
  }
}

function diaf_get_date_range_filter($field, $value, $not = false, $table_prefix = "")
{
  $date1 = $value["d1"] && $value["m1"] && $value["y1"] ? mktime(0,0,0,$value["m1"],$value["d1"],$value["y1"]) : 0;
  $date2 = $value["d2"] && $value["m2"] && $value["y2"] ? mktime(23,59,59,$value["m2"],$value["d2"],$value["y2"]) : 0;

  return $date1 && $date2 ? "({$table_prefix}{$field} BETWEEN '$date1' AND '$date2')" : "";
}

function diaf_get_date_str_range_filter($field, $value, $not = false, $table_prefix = "", $queryPrefix = '', $querySuffix = '')
{
  $date1 = $value["d1"] && $value["m1"] && $value["y1"] ? "{$value["y1"]}-".lead0($value["m1"])."-".lead0($value["d1"])." 00:00:00" : 0;
  $date2 = $value["d2"] && $value["m2"] && $value["y2"] ? "{$value["y2"]}-".lead0($value["m2"])."-".lead0($value["d2"])." 23:59:59" : 0;

  return $date1 && $date2 ? $queryPrefix . "({$table_prefix}{$field} BETWEEN '$date1' AND '$date2')" . $querySuffix : "";
}

function diaf_minus_one($field, $value, $not = false, $table_prefix = "")
{
  $not_str = $not ? "!" : "";

  if ($value == -1) return "{$table_prefix}{$field}{$not_str}='0'";
  else return "{$table_prefix}{$field}{$not_str}='$value'";
}

function diaf_minus_one2($field, $value, $not = false, $table_prefix = "")
{
  $not_str = $not ? "!" : "";

  if ($value == -1) return "{$table_prefix}{$field}{$not_str}='0'";
  else return "{$table_prefix}{$field}";
}

function diaf_minus_one_hundred($field, $value, $not = false, $table_prefix = "")
{
  $not_str = $not ? "!" : "";

  if ($value == -100) return "{$table_prefix}{$field}{$not_str}='0'";
  else return "{$table_prefix}{$field}{$not_str}='$value'";
}

function diaf_from_to($field, $value, $not = false, $table_prefix = "")
{
  return "(INSTR(sender,'$value')>'0' OR INSTR(recipient,'$value')>'0')";
}

function diaf_like($field, $value, $not = false, $table_prefix = "")
{
  return diaf_substr($field, $value, $not, $table_prefix);
}

function diaf_substr($field, $value, $not = false, $table_prefix = "")
{
  return "INSTR({$table_prefix}{$field},'$value')>'0'";
}

function diaf_first_last_name($field, $value, $not = false, $table_prefix = "")
{
  return "(INSTR(first_name,'$value')>'0' OR INSTR(last_name,'$value')>'0')";
}

function diaf_empty($field, $value, $not = false, $table_prefix = "")
{
  return "";
}

function diaf_get_subcategories_ids($field, $value, $not = false, $table_prefix = "")
{
  $cs = new cmsStuff("categories");
  $ar = $cs->get_children_idz($value, array($value));

  return "{$table_prefix}{$field} in ('".join("','", $ar)."')";
}

function diaf_le($field, $value, $not = false, $table_prefix = "")
{
  return "{$table_prefix}{$field}<='$value'";
}

function diaf_ge($field, $value, $not = false, $table_prefix = "")
{
  return "{$table_prefix}{$field}>='$value'";
}

function diaf_bin_ip($field, $value, $not = false, $table_prefix = "")
{
  return "{$table_prefix}{$field}='".ip2bin($value)."'";
}

function diaf_host($field, $value, $not = false, $table_prefix = "")
{
  return "(INSTR({$table_prefix}{$field},'$value')>'0' or INSTR({$table_prefix}{$field},'www.$value')>'0')";
}

function diaf_checkboxes($field, $value, $not = false, $table_prefix = "")
{
  $ar = explode(",", $value);
  $ar2 = array();

  foreach ($ar as $x)
  {
    $ar2[] = "INSTR(CONCAT(',',{$table_prefix}{$field},','),',$x,')>0";
  }

  return "(".join(" or ", $ar2).")";
}

function diaf_checkboxes2($field, $value, $not = false, $table_prefix = "")
{
  $ar = explode(",", $value);
  $ar2 = array();

  foreach ($ar as $x)
  {
    if ($x == -1)
      $ar2[] = "{$table_prefix}{$field}=''";
    else
      $ar2[] = "INSTR(CONCAT(',',{$table_prefix}{$field},','),',$x,')>0";
  }

  return "(".join(" or ", $ar2).")";
}

function diaf_tags($field, $value, $not = false, $table_prefix = "")
{
  global $F;

  $w_suffix = $value == -1 ? " OR id NOT IN (SELECT target_id FROM tag_links WHERE type='$F->table')" : "";

  return "(id IN (SELECT target_id FROM tag_links WHERE tag_id IN ($value) AND type='$F->table')$w_suffix)";
}

function diaf_checkboxes_minus_one($field, $value, $not = false, $table_prefix = "")
{
  $ar = explode(",", $value);
  foreach($ar as $k => $v)
    if ($ar[$k] == -1) $ar[$k] = 0;

  $value = join(",", $ar);

  return $value ? "{$table_prefix}{$field} in ($value)" : "1=0";
}

function diaf_checkboxes_past_present_future($field, $value, $not = false, $table_prefix = "")
{
  $ar = explode(",", $value);
  $ar2 = array();

  $t = time();

  if (in_array("past", $ar)) $ar2[] = "{$table_prefix}{$field}2<'$t'";
  if (in_array("present", $ar)) $ar2[] = "({$table_prefix}{$field}1<='$t' and {$table_prefix}{$field}2>='$t')";
  if (in_array("future", $ar)) $ar2[] = "{$table_prefix}{$field}1>'$t'";

  return "(".join(" or ", $ar2).")";
}

function diaf_several_ints($field, $value, $not = false, $table_prefix = "")
{
  $ar = preg_split("/[\x20,;\.\t\s]+/", $value);
  foreach($ar as $k => $v)
    $ar[$k] = $v*1;

  $value = join(",", $ar);

  return $value ? "{$table_prefix}{$field} in ($value)" : ""; //1=0
}

function diaf_several_ints_or_clean_titles($field, $value, $not = false, $table_prefix = "")
{
  $ar = preg_split("/[\x20,;\.\t\s]+/", $value);
  foreach($ar as $k => $v)
    $ar[$k] = "'$v'"; //*1

  $value = join(",", $ar);

  return $value ? "({$table_prefix}{$field} in ($value) or clean_title in ($value))" : "1=0";
}

function diaf_price2($field, $value, $not = false, $table_prefix = "")
{
  if ($value == 1) return "(price2!='0' and '".time()."' BETWEEN action_date1 and action_date2)";
  elseif ($value == 2) return "(price2='0' or '".time()."'<action_date1 or '".time()."'>action_date2)";
  return "";
}
