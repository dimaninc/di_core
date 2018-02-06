<?php
class diSearcher
{
	public $tables_ar = array();
	protected $search_mode = "db";
	protected $rc_ar = array();
	protected $search_ids_ar = array();
	public $orig_search_ids_ar = array();
	public $search_results_exist = false;
	public $current_area = false;
	protected $q = "";
	public $hardReplaceSymbols = true;

	protected $orig_page;

	/** @var diDB */
	private $db;

	public function __construct($tables_ar)
	{
		global $db;

		$this->db = $db;

		$this->tables_ar = $tables_ar;
		$this->search_mode = DISEARCH_MODE;

		$ids = diRequest::get("search_ids", "");

		$this->orig_search_ids_ar = $ids ? explode(",", str_in($ids)) : array();
		$this->current_area = str_in(diRequest::get("a", ""));
		$this->q = str_in(diRequest::get("q", ""));
		$this->orig_page = diRequest::get("page", 1);

		diSearch::hey();
	}

	protected function getDb()
	{
		return $this->db;
	}

	public function get_table_caption($table)
	{
		return isset($this->tables_ar[$table]) ? $this->tables_ar[$table] : "[$table]";
	}

	public function get_rc($table)
	{
		return isset($this->rc_ar[$table]) ? $this->rc_ar[$table] : 0;
	}

	public function get_search_ids_ar()
	{
		return $this->search_ids_ar;
	}

	public function get_q()
	{
		return $this->q;
	}

	protected function search()
	{
		global $db;

		$this->search_results_exist = false;

		$this->rc_ar = $this->search_ids_ar = array();

		foreach ($this->tables_ar as $table => $caption)
		{
			if (!$table)
			{
				continue;
			}

			$search_r = $this->orig_search_ids_ar ? $db->r("searches", "WHERE t='$table' and id".$db->in($this->orig_search_ids_ar)) : false;

			$search = $this->search_mode == "db" ? new diDBSearch($table) : new diTextfileSearch($table);

			if ($search_r)
			{
				$search->search_id = $search_r->id;

				$search_result_r = $db->r("search_results", "WHERE search_id='$search_r->id'", "COUNT(*) AS cc");
				$rc = $search_result_r->cc;
			}
			else
			{
				$search->hardReplaceSymbolsOfQuery = $this->hardReplaceSymbols;
				$ar = $search->search($this->q);
				$rc = count($ar);
			}

			if ($rc)
			{
				$this->search_results_exist = true;
			}

			$this->rc_ar[$table] = (int)$rc;
			$this->search_ids_ar[$table] = $search->search_id;
		}
	}

	protected function get_results_by_table($table)
	{
		global $search_q_ar, $db;

		$props = extend(array(
			"joins" => "",
			"where" => "",
			"order" => "",
			"fields" => "",
		), $search_q_ar[$table]);

		unset($_GET["page"]);

		if ($table == $this->current_area && $this->orig_page != 1)
		{
			$_GET["page"] = $this->orig_page;
		}

		$per_page = diConfiguration::safeGet(diConfiguration::exists("per_page[$table]") ? "per_page[$table]" : "per_page[search]", 100000);

		$pn = new diPagesNavy($table, $per_page, $this->rc_ar[$table]);

		$whereAr = array(
			"i.search_id='{$this->search_ids_ar[$table]}'",
		);

		if ($props["where"])
		{
			$whereAr[] = $props["where"];
		}

		$query = "WHERE ".join(" AND ", $whereAr)." ".$props["order"]." LIMIT $pn->start,$pn->per_page";

		$results = $db->rs("$table t INNER JOIN search_results i ON t.id=i.id {$props["joins"]}", $query, "t.*,i.rel");

		return $results;
	}

	protected function process_all_results($func)
	{
		$ar = array();

		foreach ($this->tables_ar as $table => $caption)
		{
			if (!$table)
			{
				continue;
			}

			$rs = $this->get_results_by_table($table);

			$ar[$table] = $func($this, $table, $rs);
		}

		return $ar;
	}

	public function go($func)
	{
		$this->search();

		return $this->process_all_results($func);
	}
}