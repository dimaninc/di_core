<?php
/*
	// dimaninc

	// 2015/05/04
		* birthday

*/

class diPropertyInheritance
{
    private $ar = array();

	public function __construct()
	{
		for ($i = 0; $i < func_num_args(); $i++)
		{
			$this->push(func_get_arg($i));
		}
	}

	public function push($rec)
	{
		$this->ar[] = (array)(is_object($rec) && $rec instanceof \diModel ? $rec->get() : $rec);
	}

	public function get($field)
	{
		$rec = $this->getRec($field);

		return $rec ? $rec[$field] : null;
	}

	public function getRec($field)
	{
		for ($i = count($this->ar) - 1; $i >= 0; $i--)
		{
			if (!empty($this->ar[$i][$field]))
			{
				return $this->ar[$i];
			}
		}

		return null;
	}
}