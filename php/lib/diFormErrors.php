<?php
/*
    // dimaninc

    // 2006/12/16
        * add() improved. no more define() call after initiating

    // 2006/07/12
        * first version
*/

class diFormErrors
{
	public $errors_array;

	function __construct()
	{
		$this->errors_array = array();
	}

	function add($key, $error_text)
	{
		$this->define($key);
		$this->errors_array[$key][] = $error_text;
	}

	function happened()
	{
		$rez = false;

		foreach($this->errors_array as $kk => $vv)
		{
			if (count($vv))
			{
				$rez = true;

				break;
			}
		}

		return $rez;
	}

	function get_strings($key)
	{
		if (isset($this->errors_array[$key]) && count($this->errors_array[$key]))
			return join("<br>", $this->errors_array[$key]);
		else
			return "";
	}

	function get_keys()
	{
		return array_keys($this->errors_array);
	}

	// $key is a string or an array of strings
	function define($key)
	{
		if (is_array($key))
		{
			foreach($key as $k)
			{
				if (!isset($this->errors_array[$k]) || !is_array($this->errors_array[$k]))
					$this->errors_array[$k] = array();
			}
		}
		else
		{
			if (!isset($this->errors_array[$key]) || !is_array($this->errors_array[$key]))
				$this->errors_array[$key] = array();
		}
	}
}