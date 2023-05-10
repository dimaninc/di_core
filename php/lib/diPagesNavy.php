<?php
/*
	// dimaninc

	// 2012/02/22
		* ::get_page_href() added
		* ::replaces_ar added (e.g. replace '/store/?page=1' and '/store/' to '/')
		* ::wrong_page_error_handler added

	// 2011/12/11
		* some new shit added

	// 2007/01/17
		* just was born
*/

class diPagesNavy
{
	const PAGE_PARAM = 'page';

	/** @var  \diCollection */
	private $col;

    private $db;

	private $table;
	public $per_page;
	public $per_load;
	public $start;
	public $page;
	public $total_records;
	public $total_pages;
	public $reverse;
	public $page_param;
	private $sortby_param = "sortby";
	private $dir_param = "dir";
	public $where;
	public $sortby;
	public $dir;

	private $init_on_last_page = false;

	/**
	 * @var callable|null
	 */
	protected $pageHrefProcessor = null;

	public $tpl_ar = [
		'link' => '<a href="{HREF}">{PAGE}</a>',
        'border-link' => '<a href="{HREF}" class="navy--border">{PAGE}</a>',
		'selected' => '<b>{PAGE}</b>',
		'inactive' => '<span>{PAGE}</span>',
		'dots' => '{DOTS}',
	];
	public $str_ar = [
		'prev_page' => '',
		'next_page' => '',
		'prev_symb' => '&laquo;',
		'next_symb' => '&raquo;',
	];

	public $wrong_page_error_handler = "diPagesNavy_wrong_page_error";
	public $glue = "&";
	public $replaces_ar = [];

	public function __construct($tableOrCollection, $perPageOrPageParam = 1, $whereOrTotalRecords = "",
	                            $reverse = false, $page_param = self::PAGE_PARAM)
	{
		$this->init($tableOrCollection, $perPageOrPageParam, $whereOrTotalRecords, $reverse, $page_param);
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getTotalRecords()
	{
		return $this->total_records;
	}

	public function getTotalPages()
	{
		return $this->total_pages;
	}

	public function getPage()
	{
		return $this->page;
	}

    public function getPageParam()
    {
        return $this->page_param;
    }

	public function getPrevPage()
	{
		return $this->checkPrevNext($this->getPage() - $this->getSign());
	}

	public function getNextPage()
	{
		return $this->checkPrevNext($this->getPage() + $this->getSign());
	}

	public function getStart()
	{
		return $this->start;
	}

	public function getPerPage()
	{
		return $this->per_page;
	}

	public function getSortByParam()
	{
		return $this->sortby_param;
	}

	public function getDirParam()
	{
		return $this->dir_param;
	}

	public function getSortBy()
	{
		return $this->sortby;
	}

	public function getDir()
	{
		return $this->dir;
	}

	public function getSign()
	{
		return $this->reverse ? -1 : 1;
	}

	protected function checkPrevNext($page)
	{
		if ($page < 1 || $page > $this->getTotalPages()) {
			return null;
		}

		return $page;
	}

	protected function getDb()
	{
		return $this->db;
	}

	public function getSqlLimit()
	{
		return ' LIMIT ' . $this->getStart() . ',' . $this->getPerPage();
	}

	public function getWhere()
	{
		return $this->where;
	}

	public function init($table, $per_page = 1, $where = '', $reverse = false, $page_param = null)
	{
		global $pagesnavy_sortby_ar;

		if ($table instanceof \diCollection) {
			$this->col = $table;
			if ($per_page && is_string($per_page) && !isInteger($per_page)) {
				$page_param = $per_page;
			}
			$per_page = $this->col->getPageSize();
			$where = $this->col->getRealCount();
			$table = $table->getTable();
			$this->db = $this->col::db();
		} else {
		    $this->db = \diModel::createForTable($table)::getConnection()->getDb();
        }

		if (is_array($reverse)) {
			$this->init_on_last_page = isset($reverse["init_on_last_page"]) ? $reverse["init_on_last_page"] : false;
			$this->reverse = isset($reverse["reverse"]) ? $reverse["reverse"] : false;
		} else {
			$this->reverse = $reverse;
		}

		$this->table = $table;
		$this->page_param = $page_param ?: self::PAGE_PARAM;

		if (is_array($per_page)) {
			$this->per_page = $per_page['initial'];
			$this->per_load = $per_page['load'];
		} else {
			$this->per_page = $this->per_load = $per_page;
		}

		if (isInteger($where)) {
			$this->total_records = (int)$where;
			$this->where = "";
		} else {
			if ($where && strtoupper(substr($where, 0, 5)) != "WHERE") {
				$where = "WHERE $where";
			}

			$this->where = $where;

			if (!$this->col) {
				$this->col = $this->getInitialCollection();
			}

			$this->total_records = $this->col->count();
		}

		$this->total_pages = $this->total_records ? ceil($this->total_records / $this->per_page) : 0;
		$this->page = \diRequest::get($this->page_param,
			$this->reverse || $this->init_on_last_page ? ((int)$this->total_pages ?: 1) : 1);

		/*
		$sortby_ar = isset($pagesnavy_sortby_ar[$this->table])
            ? $pagesnavy_sortby_ar[$this->table]
            : $pagesnavy_sortby_ar["*default"];
		$sortby_defaults_ar = isset($pagesnavy_sortby_defaults_ar[$this->table])
            ? $pagesnavy_sortby_defaults_ar[$this->table]
            : $pagesnavy_sortby_defaults_ar["*default"];
		*/

		$this->sortby = \diRequest::get($this->sortby_param, ''); //, $sortby_defaults_ar["sortby"]
		$this->dir = strtolower(\diRequest::get($this->dir_param, '')); //, $sortby_defaults_ar["dir"]

        /*
		if (!in_array($this->dir, ["asc", "desc"])) {
			$this->dir = $sortby_defaults_ar["dir"];
		}
        */

		if (
		    isset($pagesnavy_sortby_ar[$this->table]) &&
            !in_array($this->sortby, array_keys($pagesnavy_sortby_ar[$this->table]))
        ) {
			$this->sortby = current(array_keys($pagesnavy_sortby_ar[$this->table]));
		}

		if (
		    ($this->page < 1 || $this->page > $this->total_pages) &&
            empty($GLOBALS["DIPAGESNAVY_FORCE_NO_404"])
        ) {
			if ($this->total_records || (!$this->total_records && $this->page != 1)) {
				$f = $this->wrong_page_error_handler;
				$f();
			}
			$this->page = $this->reverse || $this->init_on_last_page ? $this->total_pages : 1;
		}

        if (
            isset($_GET[$this->page_param]) &&
            (
                (!$this->reverse && !$this->init_on_last_page && $this->page == 1) ||
                (($this->reverse || $this->init_on_last_page) && $this->page == $this->total_pages)
            ) &&
            empty($GLOBALS["DIPAGESNAVY_FORCE_NO_404"])
        ) {
            $f = $this->wrong_page_error_handler;
            $f();
        }

		$this->start = ($this->reverse ? $this->total_pages - $this->page : $this->page - 1) * $this->per_page;

		return $this;
	}

	protected function getInitialCollection()
	{
		$col = \diCollection::createForTable($this->getTable());
		$col
			->setQuery($this->getWhere());

		return $col;
	}

	public function setPageHrefProcessor(callable $cb)
	{
		$this->pageHrefProcessor = $cb;

		return $this;
	}

	public function get_page_of($id, $orderByField, $dir)
	{
		$m = \diModel::createForTable($this->getTable(), $id, 'id');
		$orderByValue = $m->get($orderByField);

		$sign = strtolower($dir) == "asc" ? "<" : ">";

		$col = $this->getInitialCollection();

		if ($col->getQuery()) {
			$col
				->setQuery($col->getQuery() . " AND {$this->getDb()->escapeField($orderByField)} $sign {$this->getDb()->escapeValue($orderByValue)}");
		} else {
			$col
				->filterBy($orderByField, $sign, $orderByValue);
		}

		$page = ceil(($col->count() + 1) / $this->per_page);

		return $page;
	}

	public function extended_get_page_of($id, $orderby, $dir)
	{
		$r = $this->getDb()->r($this->table, $id, $orderby);

		$where = $this->where;

		$sign = strtolower($dir) == "asc" ? "<=" : ">=";
		$where2 = "{$orderby}{$sign}{$r->$orderby}";

		if ($where)
			$where .= " and ";
		else
			$where = "WHERE ";

		$where .= $where2;

		$cc = 0;
		$rs2 = $this->getDb()->rs($this->table, "$where ORDER BY $orderby $dir,date $dir", "id");
		while ($r2 = $this->getDb()->fetch($rs2)) {
			$cc++;

			if ($r2->id == $id)
				break;
		}

		$page = ceil(++$cc / $this->per_page);

		return $page;
	}

	public function get_next_r($id, $orderby, $dir, $circular = true)
	{
		return $this->get_sibling_r("next", $id, $orderby, $dir, $circular);
	}

	public function get_prev_r($id, $orderby, $dir, $circular = true)
	{
		return $this->get_sibling_r("prev", $id, $orderby, $dir, $circular);
	}

	public function get_sibling_r($position, $id, $orderby, $dir, $circular = true)
	{
		$r = $this->getDb()->r($this->table, $id);

		if ($position == "prev")
			$dir2 = strtolower($dir) == "asc" ? "desc" : "asc";
		else
			$dir2 = $dir;

		$sign = (strtolower($dir) == "asc" && $position == "next") || (strtolower($dir) == "desc" && $position == "prev") ? ">" : "<";
		$where2 = "(({$orderby}{$sign}'{$r->$orderby}') or ({$orderby}='{$r->$orderby}' and id{$sign}'$r->id'))";

		$where_orderby = " ORDER BY $orderby $dir2,id $dir2";

		//echo "$position ".($this->where ? $this->where." and " : "WHERE ").$where2.$where_orderby."<br>";

		$r2 = $this->getDb()->r($this->table, ($this->where ? $this->where." and " : "WHERE ").$where2.$where_orderby);

		if (!$r2) {
			$r2 = $this->getDb()->r($this->table, $this->where.$where_orderby);
			//echo "$position $this->where$where_orderby<br>";
		}

		return $r2;
	}

	public function set_tpl($name, $value = "")
	{
		if (is_array($name)) {
			$this->tpl_ar = array_merge($this->tpl_ar, $name);
		} else {
			$this->tpl_ar[$name] = $value;
		}

		return $this;
	}

	public function set_str($name, $value = "")
	{
		if (is_array($name)) {
			$this->str_ar = array_merge($this->str_ar, $name);
		} else {
			$this->str_ar[$name] = $value;
		}

		return $this;
	}

	public function set_wrong_page_error_handler($handler)
	{
		$this->wrong_page_error_handler = $handler;

		return $this;
	}

	public function add_replace($source, $result)
	{
		$this->replaces_ar[$source] = $result;

		return $this;
	}

	public function add_replaces_ar($ar)
	{
		foreach ($ar as $k => $v) {
			$this->add_replace($k, $v);
		}

		return $this;
	}

	public function get_page_href($p, $base_uri)
	{
		$href = ($this->reverse && $p == $this->total_pages) || (!$this->reverse && $p == 1)
			? $base_uri
			: "{$base_uri}{$this->glue}{$this->page_param}={$p}";

		foreach ($this->replaces_ar as $k => $v) {
			if ($href == $k) $href = $v;
		}

		if ($this->pageHrefProcessor && is_callable($this->pageHrefProcessor)) {
			$href = call_user_func($this->pageHrefProcessor, $href);
		}

		return $href;
	}

	public function print_pages($base_uri, $separator = " ", $pages_max_shown = 10, $dots = "...")
	{
		$s = "";
		$sign = $this->reverse ? -1 : 1;
		$this->glue = strpos($base_uri, "?") !== false ? "&" : "?";

		if ($this->total_pages > 1) {
			$_pages = [];
			$_pages["start"] = $this->page - $pages_max_shown; // * $sign;
			$_pages["finish"] = $this->page + $pages_max_shown; // * $sign;

			if ($_pages["start"] < 1) {
				$_pages["start"] = 1;
			}

			if ($_pages["finish"] > $this->total_pages) {
				$_pages["finish"] = $this->total_pages;
			}

			$_pages["ar"] = [];

			switch ($_pages["start"]) {
				case 1:
					break;

				case 2:
					$_pages["ar"][] = 1;
					break;

				default:
					$_pages["ar"][] = 1;
					$_pages["ar"][] = $dots;
					break;
			}

			for ($i = $_pages["start"]; $i <= $_pages["finish"]; $i++) {
				$_pages["ar"][] = $i;
			}

			switch ($_pages["finish"]) {
				case $this->total_pages:
					break;

				case $this->total_pages - 1:
					$_pages["ar"][] = $this->total_pages;
					break;

				default:
					$_pages["ar"][] = $dots;
					$_pages["ar"][] = $this->total_pages;
					break;
			}

			if ($this->reverse) $_pages["ar"] = array_reverse($_pages["ar"]);

			for ($i = 0; $i < count($_pages["ar"]); $i++) {
				$p = $_pages["ar"][$i];

				if ($p == $dots) {
					$tmp_s = str_replace("{DOTS}", $dots, $this->tpl_ar["dots"]);
				} else {
					$tpl = $p == $this->page ? "selected" : "link";

					$tmp_s = str_replace([
						"{PAGE}",
						"{HREF}",
					], [
						$p,
						$this->get_page_href($p, $base_uri),
					], $this->tpl_ar[$tpl]);
				}

				if ($separator && $i != count($_pages["ar"]) - 1) $tmp_s .= $separator;

				$s .= $tmp_s;
			}
		} else {
			$s = str_replace("{PAGE}", 1, $this->tpl_ar["selected"]);
		}

		$prev_page = $this->page - $sign;
		$next_page = $this->page + $sign;

		if ($this->reverse) {
			$prev_tpl = $prev_page <= $this->total_pages ? "border-link" : "inactive";
			$next_tpl = $next_page >= 1 ? "border-link" : "inactive";

			$prev_page_s = $this->str_ar["prev_symb"] ? str_replace([
				"{PAGE}",
				"{HREF}",
			], [
				"{$this->str_ar["prev_symb"]} {$this->str_ar["next_page"]}",
				$this->get_page_href($prev_page, $base_uri),
			], $this->tpl_ar[$prev_tpl]) : "";
			$next_page_s = $this->str_ar["next_symb"] ? str_replace([
				"{PAGE}",
				"{HREF}",
			], [
				"{$this->str_ar["prev_page"]} {$this->str_ar["next_symb"]}",
				$this->get_page_href($next_page, $base_uri),
			], $this->tpl_ar[$next_tpl]) : "";
		} else {
			$prev_tpl = $prev_page >= 1 ? "border-link" : "inactive";
			$next_tpl = $next_page <= $this->total_pages ? "border-link" : "inactive";

			$prev_page_s = $this->str_ar["prev_symb"] ? str_replace([
				"{PAGE}",
				"{HREF}",
			], [
				"{$this->str_ar["prev_page"]} {$this->str_ar["prev_symb"]}",
				$this->get_page_href($prev_page, $base_uri),
			], $this->tpl_ar[$prev_tpl]) : "";
			$next_page_s = $this->str_ar["next_symb"] ? str_replace([
				"{PAGE}",
				"{HREF}",
			], [
				"{$this->str_ar["next_symb"]} {$this->str_ar["next_page"]}",
				$this->get_page_href($next_page, $base_uri),
			], $this->tpl_ar[$next_tpl]) : "";
		}

		$this->parts = (object)[
			"prev_page" => $prev_page_s,
			"next_page" => $next_page_s,
			"pages" => $s,
		];

		return $this->total_pages >= 1 ? "$prev_page_s $separator $s $separator $next_page_s" : "";
	}
}

function diPagesNavy_wrong_page_error()
{
	global $Z;

	if (isset($Z)) {
		$Z->errorNotFound();
	}
}
