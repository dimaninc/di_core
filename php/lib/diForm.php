<?php
/*
    // dimaninc

    // 2014/03/28
        * field titles added
        * forms creation mechanism added

    // 2011/05/17
        * some tuning

    // 2010/12/07
        * ::skip_params added
        * int_ar support added

    // 2007/07/22
        * birth
*/

/**
 * Use models instead
 * @deprecated
 */
class diForm
{
  var $submit_params = array();
  var $local_params = array();
  var $all_params = array();
  var $skip_params = array();
  var $hold = array();
  var $errors;
  var $data = array();
  var $table = "";
  var $method = "";
  var $random_code_field = "";
  var $random_code_img_w = 0;
  var $random_code_img_h = 0;
  var $last_insert_id = 0;
  var $cur_rec = false;
  var $id = 0;
  var $alter_is_submit_function = false;
  var $is_submit = null;
  public $inputs = array();

  function __construct($table = "", $method = "POST")
  {
    $this->table = $table;
    $this->errors = new diFormErrors();
    $this->method = strtoupper($method);

    srand((double)microtime() * 1000000);
  }

  function set_submit_params_str($s, $sep = ",")
  {
    $ar = explode($sep, $s);
    $this->set_submit_params($ar);
  }

  function set_local_params_str($s, $sep = ",")
  {
    $ar = explode($sep, $s);
    $this->set_local_params($ar);
  }

  function set_skip_params_str($s, $sep = ",")
  {
    $this->skip_params = explode($sep, $s);
  }

  function add_form_params_str($kind, $s, $sep = ",")
  {
    $ar = explode($sep, $s);

    foreach ($ar as $s)
      $this->add_form_param($kind, $s);
  }

  function set_hold_param($key, $value)
  {
    $this->hold[$key] = $value;
  }

  function remove_local_params($params_ar = array())
  {
    if (!is_array($params_ar)) $params_ar = array($params_ar);

    foreach ($this->local_params as $i => $a)
    {
      if (in_array($a["field"], $params_ar)) unset($this->local_params[$i]);
    }

    foreach ($this->all_params as $i => $a)
    {
      if (in_array($a["field"], $params_ar)) unset($this->all_params[$i]);
    }
  }

  function remove_submit_params($params_ar = array())
  {
    if (!is_array($params_ar)) $params_ar = array($params_ar);

    foreach ($this->submit_params as $i => $a)
    {
      if (in_array($a["field"], $params_ar)) unset($this->submit_params[$i]);
    }

    foreach ($this->all_params as $i => $a)
    {
      if (in_array($a["field"], $params_ar)) unset($this->all_params[$i]);
    }

    $this->submit_params = array_values($this->submit_params);
    $this->all_params = array_values($this->all_params);
  }

  function set_alter_is_submit_function($f)
  {
    $this->alter_is_submit_function = $f;
  }

  function is_submit($lite = false)
  {
    if (is_object($this->alter_is_submit_function))
      return $this->alter_is_submit_function->__invoke();

    $is_submit = true;
    $lite_submit = false;

    $var = "_".$this->method;

    foreach ($this->submit_params as $p)
    {
      if (
          !isset($GLOBALS[$var][$p["f"]]) &&
          !isset($_FILES[$p["f"]]) &&
          !in_array($p["t"], array("checkbox"))
         )
      {
        //echo $p["f"]."<br />";
        $is_submit = false;
      }

      if (!empty($GLOBALS[$var][$p["f"]]) || !empty($_FILES[$p["f"]]))
      {
        $lite_submit = true;
      }
    }

    $this->is_submit = $is_submit;

    return $lite ? $lite_submit : $is_submit;
  }

  function is_any_change()
  {
    foreach ($this->submit_params as $p)
    {
      $f = $p["f"];
      $t = $p["t"];

      if ($this->data[$f] != $this->cur_rec->$f)
        return true;
    }

    return false;
  }

  function define_errors_of_submit_params($array_of_additional_keys = array())
  {
    foreach ($this->submit_params as $p)
    {
      $this->errors->define($p["f"]);
    }

    if ($array_of_additional_keys)
      foreach ($array_of_additional_keys as $p)
      {
        $this->errors->define($p);
      }
  }

  function process_data($r = false)
  {
    $this->cur_rec = $r;
    $this->id = !empty($this->cur_rec->id) ? $this->cur_rec->id : 0;

    $var = "_".$this->method;

    if (!isset($this->is_submit))
      $this->is_submit = $this->is_submit();

    //var_dump($this->is_submit);

    foreach ($this->all_params as $p)
    {
      $f = $p["f"];
      $t = $p["t"];

      if ($t == "checkbox" && !isset($GLOBALS[$var][$f]) && $this->is_submit)
        $GLOBALS[$var][$f] = 0;

      $this->data[$f] = isset($GLOBALS[$var][$f]) && !is_array($GLOBALS[$var][$f])
        ? $this->reduce_to_type($GLOBALS[$var][$f], $t)
        : (isset($r->$f) ? $r->$f : "");

      //$this->data[$f] = $this->reduce_to_type($this->data[$f], $t);
    }
  }

  function reduce_to_type($v, $t)
  {
    global $db;

    switch ($t)
    {
      case "int_ar":
        $v = explode(",", $v);
        //if (!is_array($v)) $v = array();
        foreach ($v as $_i => $_v) $v[$_i] = (int)$_v;
        break;

      case "int":
      case "tinyint":
      case "smallint":
      case "integer":
        $v = intval($v);
        break;

      case "float":
        $v = str_replace(",", ".", $v);
        $v = floatval($v);
        break;

      case "double":
        $v = str_replace(",", ".", $v);
        $v = doubleval($v);
        break;

      default:
      case "string":
      case "str":
      case "varchar":
        $v = $db->escape_string($v); //str_in //
        break;

      case "pic":
      case "file":
        $v = "";
        break;

      case "text":
      case "blob":
        $v = $db->escape_string($v);
        break;

      case "checkbox":
        $v = $v ? 1 : 0;
        break;
    }

    return $v;
  }

  function set_data_value($key_or_ar, $value = false)
  {
    if (gettype($key_or_ar) == "array")
    {
      foreach ($key_or_ar as $k => $v)
      {
        $this->data[$k] = $v;
      }
    }
    else
      $this->data[$key_or_ar] = $value;
  }

  function add_error($field, $msg, $hold = false)
  {
    $this->errors->add($field, $msg);

    if ($hold) $this->hold[$field] = "true";
  }

  function get_error_strings($field)
  {
    return $this->errors->get_strings($field);
  }

  function errors_happened()
  {
    return $this->errors->happened();
  }

  function set_random_code_field($field, $random_code_img_w = 0, $random_code_img_h = 0)
  {
    $this->random_code_field = $field;
    $this->random_code_img_w = $random_code_img_w;
    $this->random_code_img_h = $random_code_img_h;

    $this->define_errors_of_submit_params(array($field));

    //$this->add_form_params_str("random_code|str|[virtual]������� ���");
  }

  function check_random_code($error_msg)
  {
    if (!$this->random_code_field) return false;

    if (!isset($this->hold[$this->random_code_field]))
      $this->set_hold_param($this->random_code_field, "false");

    $random_code = isset($_POST[$this->random_code_field]) ? $_POST[$this->random_code_field] : "";
    $random_code_hash = isset($_POST["{$this->random_code_field}_hash"]) ? $_POST["{$this->random_code_field}_hash"] : "";

    if (
        !isset($_SESSION[$this->random_code_field]) ||
        !$random_code || !$random_code_hash ||
        $random_code != $_SESSION[$this->random_code_field] ||
        $random_code_hash != md5($_SESSION[$this->random_code_field])
       )
    {
      $this->add_error($this->random_code_field, $error_msg);
    }
  }

  function check_random_code2($error_msg)
  {
    global $db;

    if (!$this->random_code_field) return false;

    if (!isset($this->hold[$this->random_code_field]))
      $this->set_hold_param($this->random_code_field, "false");

    $ok = false;

    if (isset($_POST[$this->random_code_field]))
    {
      $id = isset($_POST[$this->random_code_field."_log_id"]) ? substr($_POST[$this->random_code_field."_log_id"], 4, strlen($_POST[$this->random_code_field."_log_id"]) - 8) : 0;
      $r = $id ? $db->r("captchas_log", $id) : false;

      if ($r && static::get_captcha_uid($r->captcha_id) == $_POST[$this->random_code_field])
        $ok = true;
    }

    if (!$ok)
      $this->add_error($this->random_code_field, $error_msg);
  }

  function check_db_quote_need($f, $t)
  {
    $v = $this->data[$f];

    if (in_array($t, array("date_str","time_str","datetime_str","timestamp")) && strtoupper($v) == "NOW()")
      $f = "*$f";

    return $f;
  }

  function store()
  {
    global $db;

    $q_ar = array();

    for ($i = 0; $i < count($this->all_params); $i++)
    {
      $f = $this->all_params[$i]["f"];
      $t = $this->all_params[$i]["t"];

      if (in_array($f, $this->skip_params))
        continue;

      $q_ar[$this->check_db_quote_need($f, $t)] = $this->data[$f];
    }

    if ($this->cur_rec && !empty($this->cur_rec->id) && substr($this->cur_rec->id, 0, 2) != "__" && (int)$this->cur_rec->id)
    {
      $db->update($this->table, $q_ar, $this->cur_rec->id);

      return $this->cur_rec->id;
    }
    else
    {
      $this->last_insert_id = $db->insert($this->table, $q_ar);

      return $this->last_insert_id;
    }
  }

  function store_insert()
  {
    return $this->store();
  }

  function store_update($where)
  {
    return $this->store();
  }

  // $print_form is an array with params
  //   token => token of printed rows

  function assign_tpl_data(&$tpl, $print_form = false)
  {
    $this->assign_tpl_values($tpl);
    $this->assign_tpl_holds($tpl);
    $this->assign_tpl_errors($tpl);
    $this->assign_tpl_random_code($tpl);
    $this->assign_tpl_inputs($tpl, $print_form);
  }

  function assign_tpl_inputs(&$tpl, $print_form = false)
  {
    $opts = extend(array(
      "token" => "INPUT_ROWS",
    ), (array)$print_form);

    foreach ($this->inputs as $f => $s)
    {
      $tpl->assign(array(
        strtoupper($f)."_INPUT" => $s,
      ));
    }

    if ($print_form)
    {
      foreach ($this->submit_params as $p)
      {
        if ($p["title"] == "[hidden]")
          continue;

        $f = $p["f"];
        $t = $p["t"];

        if (empty($this->data[$f]))
          $this->data[$f] = $this->cur_rec ? $this->cur_rec->$f : "";

        $tpl->assign(array(
          "FF_FIELD" => $f,
          "FF_TITLE" => $p["title"],
          "FF_VALUE" => str_out($this->data[$f]),
          "FF_CHECKED" => $t == "checkbox" && $this->data[$f] ? " checked=\"checked\"" : "",
          "FF_INPUT" => isset($this->inputs[$f]) ? $this->inputs[$f] : "",

          "ERROR_TOKEN" => $f,
          "ERROR_TEXT" => $this->errors->get_strings($f),
        ));

        if (isset($this->inputs[$f]))
        {
          $input_tpl_name = "";
        }
        else
        {
          switch ($t)
          {
            default:
            case "str":
            case "string":
              $input_tpl_name = "string_input";
              break;

            case "text":
            case "blob":
              $input_tpl_name = "text_input";
              break;

            case "checkbox":
              $input_tpl_name = "checkbox_input";
              break;
          }
        }

        if ($input_tpl_name)
          $tpl->parse("FF_INPUT", $input_tpl_name);

        $tpl->parse("FF_ERROR_LINE", "error_line");

        $tpl->parse($opts["token"], ".form_field_row");
      }
    }
  }

  function assign_tpl_values(&$tpl, $cur_rec = false, $tpl_value_suffix = "_VALUE")
  {
    foreach ($this->all_params as $p)
    {
      $f = $p["f"];
      $t = $p["t"];

      if (isset($this->data[$f]) && is_array($this->data[$f])) continue;

      if (empty($this->data[$f])) $this->data[$f] = $cur_rec ? $cur_rec->$f : "";
      //if ($this->data[$f] == 0 && in_array($t, explode(",", "float,double,int,integer,tinyint,smallint"))) $this->data[$f] = "";

      $tpl->assign(strtoupper($f).$tpl_value_suffix, stripslashes($this->data[$f]));

      $tpl->assign(array(
        strtoupper($f)."_CHECKED" => $t == "checkbox" && $this->data[$f] ? " checked=\"checked\"" : "",
      ));
    }
  }

  function assign_tpl_holds(&$tpl, $tpl_hold_suffix = "_HOLD")
  {
    foreach ($this->hold as $f => $v)
    {
      $tpl->assign(strtoupper($f).$tpl_hold_suffix, $v);
    }
  }

  static function get_captcha_uid($id)
  {
    return md5("давай, игорек! $id ололо блеать $#@$@#% сука tro1o1o");
  }

  function assign_tpl_random_code(&$tpl)
  {
    global $di_captchas_ar, $db;

    if (!empty($tpl->FILELIST["captcha_row"]))
    {
      $idx_ar = array_rand($di_captchas_ar, 4);

      srand((double)microtime() * 1000000);
      $captcha_idx = $idx_ar[rand(0, 3)];

      $captcha_id = $di_captchas_ar[$captcha_idx]["id"];

      $captcha_log_id = $db->insert("captchas_log", array(
        "ip" => ip2bin(),
        "captcha_id" => $captcha_id,
        "date" => time(),
      ));

      $uids_ar = array();

      foreach ($idx_ar as $idx)
      {
        $ar = $di_captchas_ar[$idx];

        $uid = $captcha_id == $ar["id"] ? static::get_captcha_uid($captcha_id) : get_unique_id();
        $uids_ar[] = "'$uid'";

        $tpl->assign(array(
          "C_TITLE" => $ar["title"],
          "C_PIC" => get_pics_folder("captchas").$ar["pic"],
          "C_PIC_W" => $ar["pic_w"],
          "C_PIC_H" => $ar["pic_h"],
          "C_UID" => $uid,
        ));
        $tpl->parse("CAPTCHA_ROWS", ".captcha_row");
      }

      $tpl->assign(array(
        "CAPTCHA_TITLE" => $di_captchas_ar[$captcha_idx]["title"],
        "CAPTCHA_LOG_ID" => $captcha_log_id,
        "CAPTCHA_ID_PREFIX" => rand(1000, 9999),
        "CAPTCHA_ID_SUFFIX" => rand(1000, 9999),
        "CAPTCHA_UIDS_AR" => join(",", $uids_ar),
      ));
    }
    else
    {
      if ($this->random_code_field)
      {
        $random_code = substr(rand(1000, 9999), 0, 4);
        $random_code_hash = md5($random_code);

        $_SESSION[$this->random_code_field] = $random_code;

        $a = strtoupper($this->random_code_field);

        $tpl->assign(array(
          $a."_HASH" => $random_code_hash,
          $a."_IMG_W" => $this->random_code_img_w,
          $a."_IMG_H" => $this->random_code_img_h,
        ));
      }
    }
  }

  function assign_tpl_errors(&$tpl, $token = "ERROR_TEXT", $tpl_token_suffix = "_ERROR_LINE", $tpl_name = "error_line")
  {
    $err_ar = $this->errors->get_keys();

    foreach ($this->submit_params as $ar)
    {
      $kk = $ar["field"];

      $err_ar[] = $kk;
    }

    array_unique($err_ar);

    //foreach ($this->errors->get_keys() as $kk)
    //foreach ($this->submit_params as $ar)
    foreach ($err_ar as $kk)
    {
      //$kk = $ar["field"];
      $es = $this->errors->get_strings($kk);

      if (true || $es)
      {
        $tpl->assign(array(
          $token => $es,
          "ERROR_TOKEN" => $kk,
        ));

        $tpl->parse(strtoupper($kk).$tpl_token_suffix, $tpl_name);
      }
    }

    /*
    //foreach ($this->errors->get_keys() as $kk)
    foreach ($this->submit_params as $ar)
    {
      $kk = $ar["field"];
      $es = $this->errors->get_strings($kk);

      if (true || $es)
      {
        $tpl->assign(array(
          $token => $es,
          "ERROR_TOKEN" => $kk,
        ));

        $tpl->parse(strtoupper($kk).$tpl_token_suffix, $tpl_name);
      }
    }
    */
  }

  /* ------------------------------------------------------------- */

  function get_new_idx($where = "")
  {
    global $db;

    $idx_r = $db->r($this->table, $where, "MAX(idx) AS max_idx");
    return $idx_r ? (int)$idx_r->max_idx + 1 : 1;
  }

  function get_new_order_num($where = "", $where2 = "", $asc = true)
  {
    global $db;

    if ($order_r = $db->r($this->table, "{$where}{$where2}", "order_num"))
    {
      return $order_r->order_num;
    }
    else
    {
      $max = $asc ? "MAX" : "MIN";
      $sign = $asc ? 1 : -1;
      $def = $asc ? 1 : 65000;

      $order_r = $db->r($this->table, $where, "$max(order_num) AS num,COUNT(id) AS cc");
      return $order_r->cc ? (int)$order_r->num + $sign : $def;
    }
  }

  function get_clean_title($title, $field, $current_id = 0)
  {
    global $db;

    $title = strtolower($title);
    $title = str_replace(array(" ","/","\\","_"), "_", $title);
    $title = ereg_replace('_{2,}', "_", $title);
    //$title = ereg_replace("[\W]", "", $title);
    //$title = $cs->get_unique_field("clean_title", $title, $id);
    $title = urlencode($title);

    $_title = $title;
    $i = 1;

    while ($db->count($db->rs($this->table, "WHERE $field='$title' and id!='$current_id'", "id")))
      $title = $_title.strval($i++);

    return $title;
  }

  /* ------------------------------------------------------------- */

  function set_submit_params($ar)
  {
    $this->submit_params = $this->parse_form_params($ar);
    $this->all_params = array_merge($this->submit_params, $this->local_params);

    foreach ($this->submit_params as $p)
    {
      $this->hold[$p["f"]] = "false";
    }
  }

  function set_local_params($ar)
  {
    $this->local_params = $this->parse_form_params($ar);
    $this->all_params = array_merge($this->submit_params, $this->local_params);
  }

  function parse_form_params($ar)
  {
    $result_ar = array();

    $var = "_".$this->method;

    if (!is_array($ar)) $ar = array($ar);
    foreach ($ar as $p)
    {
      if (!$p) continue;

      list($f, $t, $title) = array_merge(explode("|", $p), array("", "", ""));
      $x = strpos($f, "#");
      if ($x !== false)
      {
        list($old, $f) = explode("#", $f);
        if (isset($GLOBALS[$var][$old])) $GLOBALS[$var][$f] = $GLOBALS[$var][$old];
      }

      if (substr($title, 0, 9) == "[virtual]")
      {
        $title = substr($title, 9);
        $this->skip_params[] = $f;
      }

      $result_ar[] = array(
        "field" => $f,
        "type" => $t,
        "title" => $title,
        "f" => $f,
        "t" => $t,
      );
    }

    return $result_ar;
  }

  // kind == "local/submit"
  function add_form_param($kind, $name_and_type)
  {
    $this->{$kind."_params"} = array_merge($this->{$kind."_params"}, $this->parse_form_params($name_and_type));
    $this->all_params = array_merge($this->submit_params, $this->local_params);
  }
}

/* ------------------------------------------------------------------------------------------ */

function does_record_exist($table, $field, $value, $current_value = "", $w = "")
{
  global $db;

  if ($r = $db->r($table, "WHERE $field='$value' $w", "id,$field"))
  {
    if ($r->$field != $current_value) return $r->id;
  }

  return false;
}

function does_record_exist2($table, $ar)
{
  global $db;

  $fields_ar = array();
  $values_ar = array();

  foreach ($ar as $a)
  {
    $fields_ar[] = $a["field"];
    $values_ar[] = $a["value"];
  }

  if (!count($fields_ar)) return false;

  $q = "WHERE CONCAT(".join(",",$fields_ar).")='".join("",$values_ar)."'";

  if ($r = $db->r($table, $q))
  {
    foreach ($ar as $a)
    {
      $field = $a["field"];
      $value = $a["value"];
      $current_value = $a["current_value"];

      if ($r->$field != $current_value) return true;
    }
  }

  return false;
}