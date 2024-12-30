<?php
/*
	// dimaninc

	// 2015/05/04
		* birthday

*/

class diPropertyInheritance
{
    private $ar = [];

    public function __construct(...$args)
    {
        foreach ($args as $arg) {
            $this->push($arg);
        }
    }

    public function push($rec)
    {
        $this->ar[] =
            (array) (is_object($rec) && $rec instanceof \diModel
                ? $rec->get()
                : $rec);
    }

    public function get($field, $inherit = true)
    {
        $rec = $this->getRec($field, $inherit);

        return $rec ? $rec[$field] : null;
    }

    public function getRec($field, $inherit = true)
    {
        for ($i = count($this->ar) - 1; $i >= 0; $i--) {
            if (!empty($this->ar[$i][$field])) {
                return $this->ar[$i];
            }

            if (!$inherit) {
                break;
            }
        }

        return null;
    }
}
