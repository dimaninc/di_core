<?php
/*
    // dimaninc

    // 2014/12/04
        * diSearcher added

    // 2011/06/08
        * total reorganizing
        * diTextfileSearch
        * diDBSearch

    // 2006/07/19
        * index_table() improved

    // 2005/12/24
        * all da shit reorganized into a class

    // 2005/07/05
        * index_table() improved
        * prepare_string_for_index() improved
        * $_di_min_word_length variable added
*/

$disearch_min_word_length = 3;

$disearch_endings_ar = array(
    "ть",
    "ина", "ин", // фамилии
    "ова", "ева", "ёва",
    "ами", "ыми", "ими", "оми",
    "ой", "ей", "ай", "ый", "яй",
    "ая", "яя",
    "яю", "аю", "ою",
    "ое", "ее", "ие", "ия", "ые",
    "их", "ых", "ах",
    "ов", "ев", "ёв",
    "ья", "ье", "ьё", "ью",
    "ам", "ым", "им", "ом",
    "че",
    "ь", "а", "о", "и", "ы", "е", "э", "я", "ю",
    "л", // для прошедшего времени
    'ы',
);

/****************************************************************************************

    diSearch

*****************************************************************************************/

abstract class diSearch
{
	protected $db;
    public $table;
	public $min_word_length;
	public $use_query_replaces = false;
    public $prepare_replace_ar = [
        "\n", "\r", "\t", "<", ">", " -", "-", ".", ",", "!", "?", ":", ";", ")", "(", '"', "'", "\\", "/", "|",
    ];

	function __construct($table)
	{
		global $disearch_min_word_length, $db;

		$this->table = $table;
		$this->min_word_length = $disearch_min_word_length;
		$this->db = $db;
	}

	abstract function drop_index();
	abstract function update_search_index($id, $primary_data, $data);
	abstract function kill_index_record($id);
	abstract function search($query);

    public static function hey()
    {
    }

	public function getTable()
	{
		return $this->table;
	}

	public function getDb()
	{
		return $this->db;
	}

	/**
	 * @param string $table
	 * @param string $method
	 * @return diSearch
	 */
	public static function create($table, $method = null)
	{
		$method = $method ?: DISEARCH_MODE;

		$className = camelize("di_".$method."_search");

		return new $className($table);
	}

	public static function makeRecordIndex($table, $id)
	{
		$a = self::getSettings($table);

		if ($a)
		{
			$search = self::create($table);
			$search->index_record($id, "", $a["fields"], $a["where"], $a["callback"]);

            return true;
		}

        return false;
	}

    public static function makeTableIndex($table)
    {
        $a = self::getSettings($table);

        if ($a)
        {
            $search = self::create($table);
            $search->index_full_table('', $a["fields"], $a["where"], $a["callback"]);

            return true;
        }

        return false;
    }

	public static function getSettings($table)
	{
		global $search_q_ar;

		if (!isset($search_q_ar[$table]))
		{
			return false;
		}

        return [
            "fields" => $search_q_ar[$table]["fields"],
            "where" => !empty($search_q_ar[$table]["where"]) ? "WHERE " . str_replace("t.", "", $search_q_ar[$table]["where"]) : "",
            "callback" => !empty($search_q_ar[$table]["callback"]) ? $search_q_ar[$table]["callback"] : "",
        ];
	}

	// indexing the table
	// returns the count of records indexed
	//
	// $fields_ar -- array of table field names to get indexed
	// $callback -- is a function which returns the ending for current records index line.
	//              used if you want to index some extra data not contained in the table
	//              $callback($rec, $data) has 2 params are: $rec - current record object and
	//              $data - the current record index line
	function index_full_table($primary_fields_ar, $fields_ar, $q_ending = "", $callback = "")
	{
		if (!is_array($primary_fields_ar))
		{
			$primary_fields_ar = $primary_fields_ar ? explode(",", $primary_fields_ar) : array();
		}

		if (!is_array($fields_ar))
		{
			$fields_ar = $fields_ar ? explode(",", $fields_ar) : array();
		}

		$rs = $this->getDb()->rs($this->table, "$q_ending ORDER BY id ASC");
		while ($rs && $r = $this->getDb()->fetch($rs))
		{
			$this->index_record($r, $primary_fields_ar, $fields_ar, $q_ending, $callback);
}

		return $this;
	}

	function index_record($r_or_id, $primary_fields_ar, $fields_ar, $q_ending = "", $callback = "")
	{
		$r = is_object($r_or_id) ? $r_or_id : $this->getDb()->r($this->table, $q_ending ? "$q_ending and id='$r_or_id'" : $r_or_id);

		if (!$r)
		{
			if ((int)$r_or_id)
			{
				$this->kill_index_record((int)$r_or_id);
			}

			return $this;
		}

		if (!is_array($primary_fields_ar)) $primary_fields_ar = explode(",", $primary_fields_ar);
		if (!is_array($fields_ar)) $fields_ar = explode(",", $fields_ar);

        $primary_data_ar = [];
        $data_ar = [];

		foreach ($primary_fields_ar as $f)
		{
			if ($f)
			{
				$primary_data_ar[] = $this->prepare_string($r->$f);
			}
		}

		foreach ($fields_ar as $f)
		{
			if ($f)
			{
				$data_ar[] = "{$r->$f}";
			}
		}

		$data = join(" ", $data_ar);

		if ($callback)
		{
			$data = $callback($this->table, $r, $data);
		}

		$data = $this->prepare_string($data);

		$this->update_search_index($r->id, join("|", $primary_data_ar), $data);

		return $this;
	}

  function prepare_string($s)
  {
    $s = $this->lo($s);

    $s = strip_tags($s);
    $s = preg_replace("/&#?[a-z0-9\s]+;/", " ", $s);

    $s = str_replace($this->prepare_replace_ar, " ", $s);

    $s_ar = explode(" ", $s);
    array_walk($s_ar, "kill_lil_word");
      array_walk($s_ar, "kill_ending2");
    $s_ar = array_unique($s_ar);
    $s = implode(" ", $s_ar);

    return trim($s);
  }

  function get_replaced_query($query)
  {
    $sr_r = $this->getDb()->r("search_replaces", "WHERE query='".str_in($query)."'");

    if ($sr_r)
      return $this->lo($sr_r->replacement);
    else
    {
      $sr_r = $this->getDb()->r("search_replaces", "WHERE '".str_in($query)."' LIKE query");

      if ($sr_r)
      {
        $l1 = strlen($query);
        $l2 = strlen($sr_r->query);

        $query = substr($sr_r->query, 0, 1) == "%"
          ? substr($query, 0, $l1 - $l2 + 1)
          : substr($query, $l2 - 1);

        $query = str_replace("%", trim($query), $this->lo($sr_r->replacement));
      }
    }

    return $query;
  }

  // returns value in percents (0-100%%)
  function get_relevance($q, $id, $orig_text_field = "")
  {
    if (!isset($this->index_ar[$id]))
      return 0;

    $orig_text_field = $this->lo($orig_text_field);

    $coef = 0;
    $cc0 = 0;

    $qu2 = str_replace("+", " ", $q);
    $qu2 = $this->lo($qu2);
    $query = explode(" ", $qu2);

    array_walk($query, "kill_ending");

    //echo "text=$orig_text_field|";

    $cc = 0;
    foreach ($query as $w)
    {
      if (strlen(strpos(" {$this->index_ar[$id]}", " $w"))) $cc++;
      if ($orig_text_field && strlen(strpos(" $orig_text_field", " $w"))) $cc0++;
    }

    $coef += count($query) ? $cc0 / count($query) : 0;

    return count($query) ? round($cc / count($query) * 100 * $coef) : 0;
  }

  function lo($s)
  {
    return mb_strtolower($s);
  }
}

/****************************************************************************************

    diTextfileSearch

*****************************************************************************************/

class diTextfileSearch extends diSearch
{
    public $index_filename;
    public $index_ar = [];
    public $fullfile = "";

  function __construct($table)
  {
    parent::__construct($table);

    $this->index_filename = isset($GLOBALS[$this->table."_index_filename"])
      ? $GLOBALS[$this->table."_index_filename"]
      : "uploads/search/{$this->table}.idx";
  }

	function kill_index_record($id)
	{

	}

  function index_full_table($primary_fields_ar, $fields_ar, $q_ending = "", $callback = "")
  {
    $this->fullfile = "";

    $counter = parent::index_full_table($primary_fields_ar, $fields_ar, $q_ending, $callback);

    if ($fp = fopen($this->index_filename, "w"))
    {
      fwrite($fp, $this->fullfile);
      fclose($fp);

      $this->fullfile = "";
    }
    else
    {
      die("unable to store index file ($this->index_filename)");
    }

    return $counter;
  }

  function drop_index()
  {
    if ($fp = fopen($this->index_filename, "w"))
    {
      fclose($fp);
    }
    else
    {
      die("unable to drop index file ($this->index_filename)");
    }
  }

  function update_search_index($id, $primary_data, $data)
  {
    $this->fullfile .= "$id|$primary_data$data\n";
  }

  function search($query, $save_index_ar = false)
  {
    if ($this->use_query_replaces)
      $query = $this->get_replaced_query($query);

    $idz = array();
    $idz_orig = array();

    $qu2 = str_replace("+", "&", $query);
    $qu2 = $this->lo($qu2);
    $qu2 = chop($qu2);
    while (stristr($qu2, "  ")) $qu2 = str_replace("  ", " ", $qu2);
    $qu2 = preg_replace('/[.?,!()#\"\'\`:;|\\\\\/]/i', "", $qu2);

    $query = explode(" ", $qu2);
    $query_orig = $query; //04.08.04

    array_walk($query, "kill_ending");

    $this->search_id = $this->getDb()->insert("searches", array(
      "t" => $this->table,
      "date" => time(),
    ));

    if (strlen($qu2) >= $this->min_word_length)
    {
      if ($index = file($this->index_filename))
      {
        $num = count($index);

        for ($i = 0; $i < $num; $i++)
        {
          if (!trim($index[$i]))
            continue;

          list($id, $contents) = explode("|", $index[$i]);

          if ($save_index_ar)
            $this->index_ar[$id] = $contents;

          $wordcount = 0;
          $mustfound = 1;

          $wordorigfound = 0;
          $wordmyfound = 0;

          $mustntfound = 1;

          for ($q = 0; $q < count($query); $q++)
          {
            if (stristr($query[$q], "*"))
            {
              $search = str_replace("*", "", $query[$q]);
            }
            else
            {
              if (strlen($query[$q]) >= $this->min_word_length)
                $search = "".$query[$q]."";
              else
                $search = " ".$query[$q]." ";
            }

            $search_orig = " ".$query_orig[$q]." ";

            // если стоит знак +, то количество слов, которые _должны_ быть найдены, увеличиваются на 1
            if (strlen($search) && $search[0] == "&")
            {
              $search = str_replace("&", "", $search);
              $mustfound++;
            }

            // знак -
            if (strlen($search) > 0 && $search[0] == "-")
            {
              $search = str_replace("-", "", $search);
              $mustntfound = 0;
            }

            // если стоит знак +, то если слово найдено, весь результат умножается на 0 (смотри дальше).
            if (stristr($contents, $search_orig))
            {
              $wordorigfound++;
            }

            // если слово найдено, считаем его и умножаем на $mustntfound, то есть на 1,
            // если найдено "правильное" слово и на 0, если найдено слово, помеченное знаком -
            if (stristr($contents, $search))
            {
              $wordcount++;
              $wordcount = $wordcount * $mustntfound;

              $wordmyfound++;
            }
          }

          $gotcha = false;

          if (($wordorigfound / count($query)) > 0.6)
          {
            $idz_orig[] = $id;
            $rel = round($wordorigfound / count($query) * 10);
            $gotcha = true;
          }
          elseif ($wordcount >= $mustfound && ($wordmyfound / count($query)) >= 0.5)
          {
            $idz[] = $id;
            $rel = round($wordmyfound / count($query) * 10);
            $gotcha = true;
          }

          if ($gotcha)
          {
	          $this->getDb()->insert("search_results", array(
              "search_id" => $this->search_id,
              "id" => $id,
              "rel" => $rel,
            ));
          }
        }
      }
      else
      {
        die("unable to open index file ($this->index_filename)");
      }
    }
    else
    {
      //echo ("<br>Слишком короткий запрос!");
    }

    return array_merge($idz_orig, $idz);
  }
}

/****************************************************************************************

    diDBSearch

*****************************************************************************************/

class diDBSearch extends diSearch
{
    public $index_table;
    public $search_id = 0;

  function __construct($table)
  {
    parent::__construct($table);

    $this->index_table = "search_index_{$table}";

    $this->check_index_table_existence();
  }

  function check_index_table_existence()
  {
    $rs = $this->getDb()->rs($this->index_table, "LIMIT 1", "1");

    if (!$rs || !$this->getDb()->count($rs))
    {
      $e = strtolower(DIENCODING);

	    $this->getDb()->q("CREATE TABLE IF NOT EXISTS $this->index_table(
               id bigint not null,
               primary_content text character set $e collate {$e}_general_ci,
               content text character set $e collate {$e}_general_ci,
               fulltext(content),
               primary key(id)
              ) ENGINE=MyISAM CHARSET={$e} COLLATE={$e}_general_ci;");
    }
  }

  function kill_index_record($id)
  {
	  $this->getDb()->delete($this->index_table, (int)$id);
  }

	function drop_index()
    {
	    $this->getDb()->drop($this->index_table);
        $this->check_index_table_existence();
    }

  function update_search_index($id, $primary_data, $data)
  {
    $r = $this->getDb()->r($this->index_table, $id, "id");

    $ar = array(
      "primary_content" => addslashes($primary_data),
      "content" => addslashes($data),
    );

    if ($r)
    {
	    $this->getDb()->update($this->index_table, $ar, $r->id);
    }
    else
    {
      $ar["id"] = $id;

	    $this->getDb()->insert($this->index_table, $ar);
    }
  }

  function search($query)
  {
    if ($this->use_query_replaces)
      $query = $this->get_replaced_query($query);

    $query = $this->lo($query);
    $query = chop($query);
    $query = str_replace($this->prepare_replace_ar, " ", $query);

    $query_ar = explode(" ", $query);
    array_walk($query_ar, "kill_ending2");
    $query = trim(join(" ", $query_ar));

    $ar = array();

    $this->search_id = $this->getDb()->insert("searches", array(
      "t" => $this->table,
      "date" => time(),
    ));

    $match = "MATCH (content) AGAINST ('".addslashes($query)."' IN BOOLEAN MODE)";
    //echo "select *,$match as rel from $this->index_table WHERE $match ORDER BY rel DESC<br>";

    $rs = $this->getDb()->rs($this->index_table, "WHERE $match ORDER BY rel DESC", "*,$match as rel");
    while ($r = $this->getDb()->fetch($rs))
    {
	    $this->getDb()->insert("search_results", array(
        "search_id" => $this->search_id,
        "id" => $r->id,
        "rel" => $r->rel,
      ));

      $ar[] = $r->id;
    }

    return $ar;
  }
}

/****************************************************************************************

    stuff

*****************************************************************************************/

function kill_lil_word(&$item, $key)
{
  global $disearch_min_word_length;

  if (strlen($item) < $disearch_min_word_length) $item = "";
}

function kill_ending(&$item, $key)
{
  global $disearch_min_word_length, $disearch_endings_ar;

  for ($i = 0; $i < count($disearch_endings_ar); $i++)
  {
    $x = mb_strlen($item) - mb_strlen($disearch_endings_ar[$i]);

    if (
        mb_strlen($item) > $disearch_min_word_length &&
        $disearch_endings_ar[$i] == mb_substr($item, $x)
       )
    {
      $item = mb_substr($item, 0, $x);

      break;
    }
  }
}

function kill_ending2(&$item, $key)
{
  global $disearch_min_word_length, $disearch_endings_ar;

  for ($i = 0; $i < count($disearch_endings_ar); $i++)
  {
    $x = mb_strlen($item) - mb_strlen($disearch_endings_ar[$i]);

    if (
        mb_strlen($item) > $disearch_min_word_length &&
        $disearch_endings_ar[$i] == mb_substr($item, $x)
       )
    {
      if ($x <= $disearch_min_word_length)
        $item = $item." ".mb_substr($item, 0, $x).(DISEARCH_MODE == "db" ? "*" : "");
      else
        $item = mb_substr($item, 0, $x).(DISEARCH_MODE == "db" ? "*" : "");

      break;
    }
  }
}

function purge_search_results()
{
  global $db;

  $t = time() - 60 * 20; // 20 mins

  $rs = $db->rs("searches", "WHERE date<'$t'");
  while ($r = $db->fetch($rs))
  {
    $db->delete("search_results", "WHERE search_id='$r->id'");
  }

  $db->delete("searches", "WHERE date<'$t'");
}
