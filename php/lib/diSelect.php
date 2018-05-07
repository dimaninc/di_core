<?php
/*
    // dimaninc

    // 2015/05/06
    	* current value type options added

    // 2012/10/21
        * ::FastCreate() added
        * ::AddItemsFromDB() added
        * ::SetParam() improved

    // 2010/11/30
        * ::getSimpleItemsAr() added

    // 2010/07/08
        * option attrs added

    // 2006/12/10
        * xHTML support added

    // 2006/09/12
        * diSelect::AddItemArray2() added
*/

use diCore\Helper\ArrayHelper;

class diSelect
{
	private $attrsAr;	 	// select parameters such as 'id','name','size',etc.

	private $indent;	 	// this will be inserted in the beginning of
													// each output string

	private $itemsAr;		// array of items
	private $currentValue;	// current value for the select

	/*
		currentValue can be:
			* scalar value
			* array of scalar values
			* callback function which return true/false
			* boolean (for multiple selects: true for all selected, false for all deselected)
	*/
	public function __construct($name, $currentValue = null)
	{
		$this->attrsAr = [
			"id" => $name,
			"name" => $name,
			"size" => 1,
		];

		$this->indent = "";

		$this->itemsAr = [];
		$this->currentValue = $currentValue;
	}

	// data feed could be a hash array or $db->rs
	static public function fastCreate($name, $value, $dataFeed, $prefixAr = [], $suffixAr = [],
	                                  $templateTextOrFormatCallback = null, $templateValue = null)
	{
		$sel = new static($name, $value);

		if ($prefixAr)
		{
			$sel->addItemArray($prefixAr);
		}

		if (diDB::is_rs($dataFeed))
		{
			$sel->addItemsFromDB($dataFeed, [], [], $templateTextOrFormatCallback ?: "%title%", $templateValue ?: "%id%");
		}
		elseif (is_array($dataFeed))
		{
			$sel->addItemArray($dataFeed);
		}
		elseif ($dataFeed instanceof diCollection)
		{
			$sel->addItemsCollection($dataFeed, $templateTextOrFormatCallback);
		}

		if ($suffixAr)
		{
			$sel->addItemArray($suffixAr);
		}

		return $sel;
	}

	public function setAttr($name, $value = null)
	{
		if (!is_array($name))
		{
			$this->attrsAr[$name] = is_null($value) ? $name : $value;
		}
		else
		{
			if (is_null($value))
			{
				foreach ($name as $n => $v)
				{
					$this->attrsAr[$n] = $v;
				}
			}
			else
			{
				foreach ($name as $n)
				{
					$this->attrsAr[$n] = $value;
				}
			}
		}

		return $this;
	}

	public function getAttr($name)
	{
		return isset($this->attrsAr[$name]) ? $this->attrsAr[$name] : null;
	}

	public function addItem($value, $text, $attrsAr = [])
	{
		$this->itemsAr[] = [
			"value" => trim($value),
			"text" => trim($text),
			"attrs" => $attrsAr,
		];

		return $this;
	}

	public function addItemsFromDB($db_rs, $prefix_ar = [], $suffix_ar = [], $template_text = "%title%", $template_value = "%id%")
	{
		global $db;

		if (diDB::is_rs($db_rs))
		{
			$db->reset($db_rs);
		}

		$this->addItemArray($prefix_ar);

		while ($db_rs && $db_r = $db->fetch($db_rs))
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

			$this->addItem($value, $text);
		}

		$this->addItemArray($suffix_ar);

		return $this;
	}

	public static function getDefaultCollectionFormatter()
	{
		return [get_called_class(), "defaultCollectionFormat"];
	}

	public static function defaultCollectionFormat(diModel $m)
	{
		return [
			"value" => $m->getId(),
			"text" => $m->get("title"),
			"attributes" => [],
		];
	}

	public function addItemsCollection(diCollection $collection, $format = null, $prefixAr = [], $suffixAr = [])
	{
		if ($format === null || is_array($format))
		{
			if (is_array($format))
			{
				$suffixAr = $prefixAr;
				$prefixAr = $format;
			}

			$format = self::getDefaultCollectionFormatter();
		}

		$this->addItemArray($prefixAr);

		/** @var diModel $model */
		foreach ($collection as $model)
		{
			$data = extend([
				'value' => $model->getId(),
				'text' => null,
				'attributes' => [],
			], call_user_func($format, $model));

			$this->addItem($data["value"], $data["text"], $data["attributes"]);
		}

		$this->addItemArray($suffixAr);

		return $this;
	}

	public function addItemArray($ar)
	{
		if (is_array($ar))
		{
			foreach ($ar as $value => $text)
			{
				$this->addItem($value, $text);
			}
		}

		return $this;
	}

	public function addItemArray2($ar)
	{
		if (is_array($ar))
		{
			foreach($ar as $text)
			{
				$this->addItem($text, $text);
			}
		}

		return $this;
	}

	public function getHTML()
	{
		$html = $this->indent."<select";

		foreach ($this->attrsAr as $name => $value)
		{
		    $html .= " {$name}";

			if ($value)
			{
				$html .= "=\"{$value}\"";
			}
		}

		$html .= ">\n";

		foreach ($this->itemsAr as $item)
		{
			$attrs = $this->getSelected($item["value"])." value=\"{$item["value"]}\"";

			if ($item["attrs"])
			{
				if (is_array($item["attrs"]))
				{
					$attrs .= " " . ArrayHelper::toAttributesString($item["attrs"], true, ArrayHelper::ESCAPE_HTML);
				}
				else
				{
					$attrs .= " " . $item["attrs"];
				}
			}

			$html .= $this->indent . " <option{$attrs}>" . $item["text"] . "</option>\n";
		}

		$html .= $this->indent . "</select>";

		return $html;
	}

	public function isSelected($value)
	{
		if (is_array($this->currentValue))
		{
			return in_array($value, $this->currentValue);
		}
		elseif ($this->currentValue === true || $this->currentValue === false)
		{
			return $this->currentValue;
		}
		elseif (is_callable($this->currentValue) && gettype($this->currentValue) == "object")
		{
			return call_user_func($this->currentValue, $value);
		}
		else
		{
			return $value == $this->currentValue;
		}
	}

	public function getSelected($value)
	{
		return $this->isSelected($value) ? " selected=\"selected\"" : "";
	}

	public function getCurrentValue()
	{
		return $this->currentValue;
	}

	public function setCurrentValue($value)
	{
		$this->currentValue = $value;

		return $this;
	}

	public function getItemsAr()
	{
		return $this->itemsAr;
	}

	public function getItem($index, $what = null)
	{
		if (!isset($this->itemsAr[$index]))
		{
			return null;
		}

		$item = $this->itemsAr[$index];

		return $what ? $item[$what] : $item;
	}

	public function getSimpleItemsAr()
	{
		$ar = [];

		foreach ($this->itemsAr as $item)
		{
			$ar[$item["value"]] = $item["text"];
		}

		return $ar;
	}

	public function getTextByValue($value)
	{
		foreach ($this->itemsAr as $item)
		{
			if ($item["value"] == $value)
			{
				return $item["text"];
			}
		}

		return null;
	}

	public function __toString()
	{
		return $this->getHTML();
	}

	/** @deprecated */
	public function createHTML()
	{
		return $this->getHTML();
	}

	/** @deprecated */
	public function setParam($name, $value)
	{
		return $this->setAttr($name, $value);
	}
}