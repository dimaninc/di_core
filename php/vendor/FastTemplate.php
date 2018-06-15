<?php
/*
	some fixes, additions and cuts (c) by dimaninc 2008
	some additions (c) by dimaninc 2014
	some additions (c) by dimaninc 2015
*/

use diCore\Data\Config;
use diCore\Base\CMS;

/** @deprecated */
class FastTemplate
{
	const PLACE_WEB = 1;
	const PLACE_ADMIN = 2;

	protected $place = self::PLACE_WEB;

	public $data = array();             // holds parsed and assigned data
	public $cache_filename = "";
	public $TEMPLATES  = array();       // holds unparsed templates (cache)
	public $FILELIST   = array();       // Holds the array of filehandles FILELIST[HANDLE] == "fileName"
	public $PARSEVARS  = array();       // Holds the array of Variable handles. PARSEVARS[HANDLE] == "value"
	public $LOADED     = array();       // We only want to load a template once - when it's used.
										// LOADED[FILEHANDLE] == 1 if loaded undefined if not loaded yet.
	public $HANDLE     = array();       // Holds the handle names assigned by a call to parse()
	public $ROOT       = "";            // Holds path-to-templates
	public $WIN32      = false;         // Set to true if this is a WIN32 server
	public $ERROR      = "";            // Holds the last error message
	public $LAST       = "";            // Holds the HANDLE to the last template parsed by parse()
	private $STRICT    = false;         // Strict template checking. Unresolved vars in templates will generate a warning when found.

	private $default_folder = "pages";

	// ************************************************************

	public function __construct($pathToTemplates = "", $cache_filename = "", $place = self::PLACE_WEB)
	{
		$this->place = $place;

		if ($pathToTemplates)
		{
			$this->set_root($pathToTemplates);
		}

		if ($cache_filename)
		{
			$this->set_cache_filename($cache_filename);
		}

		$encoding = defined('DIMAILENCODING') ? DIMAILENCODING : 'UTF8';
		$encoding = \diCore\Data\Http\Charset::title(\diCore\Data\Http\Charset::id($encoding));

		$this->assign(array(
			"DI_HTML_MAIL_ENCODING" => $encoding,
		));
	}

	/**
	 * @return FastTemplate
	 */
	public static function createForWeb()
	{
		$tpl = new FastTemplate(
			Config::getOldTplFolder() . CMS::TPL_DIR,
			Config::getCacheFolder() . CMS::TPL_CACHE_PHP
		);
		$tpl
			->no_strict()
			->load_cache()
			->setupBasicAssignees();

		return $tpl;
	}

	public function setupBasicAssignees()
	{
		$protocol = $_SERVER["SERVER_PORT"] == 443 ? "https" : "http";

		$htmlBase = "{$protocol}://{$_SERVER["HTTP_HOST"]}";

		$this
			->assign(array(
				"HTML_BASE" => $htmlBase . "/",
				"HTML_BASE2" => $htmlBase,
				"HTTP_HOST" => $_SERVER["HTTP_HOST"],
				"HTTP_PROTOCOL" => $protocol,
				"HTTP_LINK" => "<a href=\"{$htmlBase}\">{$htmlBase}</a>",
			));

		return $this;
	}

	public function defined($template)
	{
		return !empty($this->FILELIST[$template]);
	}

	public function exists($template)
	{
		return !empty($this->TEMPLATES[$this->FILELIST[$template]]);
	}

	public function templateExists($templatePath)
	{
		return !empty($this->TEMPLATES[$templatePath]);
	}

	public function assigned($token)
	{
		return !!$this->getAssigned($token);
	}

	public function parse_if_not_empty($block_name, $content_name)
	{
		if ($this->assigned($content_name))
		{
			return $this->parse($block_name);
		}

		return "";
	}

	public function set_default_folder($folder = "")
	{
		$this->default_folder = $folder;

		return $this;
	}

	public function rebuild_cache()
	{
		if (empty($this->ROOT))
		{
			//$this->error("Cannot rebuild cache. Root not valid.", 1);
			return $this;
		}

		if (empty($this->cache_filename))
		{
			$this->error("Cannot rebuild cache. Cache filename not valid.", 1);
			return $this;
		}

		switch ($this->place)
		{
			case self::PLACE_WEB:
				$core_basedir = diLib::getRoot() . "/tpl/web/";
				break;

			case self::PLACE_ADMIN:
				$core_basedir = diLib::getRoot() . "/tpl/_admin/";
				break;

			default:
				throw new Exception("Undefined place: " . $this->place);
		}

		$files_ar = $this->get_directory_contents($this->ROOT);
		$core_files_ar = $this->get_directory_contents($core_basedir);

		$cache_file = "<?php\n";

		foreach ($files_ar["f"] as $f)
		{
			$cache_file .= $this->get_cache_template_line($f, $this->ROOT);
		}

		foreach ($core_files_ar["f"] as $f)
		{
			$cache_file .= $this->get_cache_template_line($f, $core_basedir, "`");
		}

		file_put_contents($this->cache_filename, $cache_file);
		chmod($this->cache_filename, 0777);

		return $this;
	}

	private function get_cache_template_line($filename, $basedir, $filename_prefix = "")
	{
		$filename = substr($filename, strlen($basedir));

		$template = $this->get_template_from_file($filename, $basedir);
		$template = str_replace('$', '\$', $template);

		return "\$this->TEMPLATES[\"{$filename_prefix}{$filename}\"] = <<<EOT\n{$template}\nEOT;\n\n";
	}

	private function get_directory_contents($path = "")
	{
		if (empty($path))
		{
			$path = $this->ROOT;
		}

		if (substr($path, -1) != "/")
		{
			$path .= "/";
		}

		$rez = array(
			"f" => array(),
			"d" => array(),
		);

		if (!$path)
		{
			return $rez;
		}

		$handle = opendir($path);

		while ($f = readdir($handle))
		{
			if (is_file($path.$f) && $f != ".htaccess")
			{
				$rez["f"][] = $path.$f;
			}
			elseif (is_dir($path.$f) && $f != "." && $f != "..")
			{
				$rez["d"][] = $f;
			}
		}

		closedir($handle);

		sort($rez["f"]);
		sort($rez["d"]);

		foreach ($rez["d"] as $dir)
		{
			$a = $this->get_directory_contents($path.$dir);

			$rez["d"] = array_merge($rez["d"], $a["d"]);
			$rez["f"] = array_merge($rez["f"], $a["f"]);
		}

		return $rez;
	}

	public function load_cache()
	{
		if (empty($this->cache_filename))
		{
			$this->error("Cannot load cache. Cache filename not valid.", 1);
			return false;
		}

		if ($this->ROOT)
		{
			include $this->cache_filename;
		}

		return $this;
	}

	// ************************************************************
	//	All templates will be loaded from this "root" directory
	//	Can be changed in mid-process by re-calling with a new
	//	value.

	public function set_root($root)
	{
		$trailer = substr($root, -1);

		if (!$this->WIN32)
		{
			if (ord($trailer) != 47)
			{
				$root = "$root".chr(47);
			}

			if (is_dir($root))
			{
				$this->ROOT = $root;
			}
			else
			{
				$this->ROOT = "";
				//$this->error("Specified ROOT dir [$root] is not a directory");
			}
		}
		else
		{
			// WIN32 box - no testing
			if (ord($trailer) != 92)
			{
				$root = "$root".chr(92);
			}
			$this->ROOT = $root;
		}

		return $this;
	}

	public function set_cache_filename($cache_filename)
	{
		$this->cache_filename = $cache_filename;

		return $this;
	}

	//	**************************************************************
	//	Calculates current microtime
	//	I throw this into all my classes for benchmarking purposes
	//	It's not used by anything in this class and can be removed
	//	if you don't need it.


	public function utime()
	{
		$time = explode(" ", microtime());
		$usec = (double)$time[0];
		$sec = (double)$time[1];

		return $sec + $usec;
	}

	//	**************************************************************
	//	Strict template checking, if true sends warnings to STDOUT when
	//	parsing a template with undefined variable references
	//	Used for tracking down bugs-n-such. Use no_strict() to disable.

	public function strict()
	{
		$this->STRICT = true;

		return $this;
	}

	// ************************************************************
	//	Silently discards (removes) undefined variable references
	//	found in templates

	public function no_strict()
	{
		$this->STRICT = false;

		return $this;
	}

	// ************************************************************
	//	A quick check of the template file before reading it.
	//	This is -not- a reliable check, mostly due to inconsistencies
	//	in the way PHP determines if a file is readable.

	public function is_safe($filename, $silent = true)
	{
		if (!is_file($filename))
		{
		    if (!$silent)
		    {
				$this->error("[$filename] does not exist", 0);
			}

			return false;
		}

		return true;
	}

	// ************************************************************
	//	Grabs a template from the root dir and
	//	reads it into a (potentially REALLY) big string

	public function get_template($template, $val = "")
	{
		if (!isset($this->TEMPLATES[$template]))
		{
			$this->error("No such template cached ($template - $val)", 1);

			return false;
		}

		return $this->TEMPLATES[$template];
	}

	public function get_template_from_file($template, $path = false)
	{
		if (empty($this->ROOT))
		{
			$this->error("Cannot open template. Root not valid.", 1);
			return false;
		}

		if ($path === false)
		{
			$path = $this->ROOT;
		}

		$filename = "{$path}{$template}";

		$contents = file_get_contents($filename);

		/*
		if (!$contents or empty($contents))
		{
			if (empty($php_errormsg)) $php_errormsg = "";
			$this->error("get_template() failure: [$filename] $php_errormsg", 1);
		}
		*/

		return $contents;
	}

	// ************************************************************
	//	Prints the warnings for unresolved variable references
	//	in template files. Used if STRICT is true

	public function show_unknowns($line)
	{
		$unknown = array();
		if (preg_match("/({[A-Z0-9_]+})/", $line, $unknown))
		{
			$UnkVar = $unknown[1];
			if (!empty($UnkVar))
			{
				@error_log("[FastTemplate] Warning: no value found for variable: $UnkVar ",0);
			}
		}

		return $this;
	}

	// ************************************************************
	//	This routine gets called by parse() and does the actual
	//	{VAR} to VALUE conversion within the template.
	public function parse_template($template, $tpl_array)
	{
		foreach ($tpl_array as $key => $val)
		{
			if (!empty($key))
			{
				if (is_array($val))
				{
					continue;
				}

				if (gettype($val) != "string")
				{
					settype($val, "string");
				}

				$key = '{'."$key".'}';
				$template = str_replace("$key", "$val", "$template");
			}
		}

		if (!$this->STRICT)
		{
			// Silently remove anything not already found
			$template = preg_replace("/{([A-Z0-9_]+)}/", "", $template);
		}
		else
		{
			// Warn about unresolved template variables
			if (preg_match("/({[A-Z0-9_]+})/", $template))
			{
				$unknown = explode("\n", $template);
				foreach ($unknown as $Line)
				{
					$UnkVar = $Line;
					if (!empty($UnkVar))
					{
						$this->show_unknowns($UnkVar);
					}
				}
			}
		}

		return $template;
	}

	/**
	 * The meat of the whole class. The magic happens here.
	 *
	 * @param $returnVar
	 * @param bool|false $fileTags
	 * @return mixed
	 */
	public function parse($returnVar, $fileTags = false)
	{
		if ($fileTags === false || $fileTags === null)
		{
			$fileTags = strtolower($returnVar);
			$returnVar = strtoupper($returnVar);
		}

		$append = false;
		$this->LAST = $returnVar;
		$this->HANDLE[$returnVar] = 1;

		if (gettype($fileTags) == "array")
		{
			// Clear any previous data
			unset($this->data[$returnVar]);

			foreach ($fileTags as $val)
			{
				if (empty($this->data[$val]))
				{
					$this->LOADED["$val"] = 1;

					$fileName = $this->FILELIST["$val"];
					$this->data[$val] = $this->get_template($fileName, $val);
				}

				// Array context implies overwrite
				$this->data[$returnVar] = $this->parse_template($this->data[$val], $this->PARSEVARS);

				// For recursive calls.
				$this->assign(array(
					$returnVar => $this->data[$returnVar],
				));
			}
		}
		else
		{
			$val = $fileTags;

			if (substr($val, 0, 1) == '.')
			{
				// Append this template to a previous ReturnVar

				$append = true;
				$val = substr($val, 1);
			}

			if (true || empty($this->data[$val]))
			{
				$this->LOADED["$val"] = 1;

				$fileName = $this->FILELIST["$val"];
				$this->data[$val] = $this->get_template($fileName, $val);
			}

			$new = $this->parse_template($this->data[$val], $this->PARSEVARS);

			if ($append)
			{
				if (!isset($this->data[$returnVar]))
				{
					$this->data[$returnVar] = "";
				}

				$this->data[$returnVar] .= $new;
			}
			else
			{
				$this->data[$returnVar] = $new;
			}

			// For recursive calls.
			$this->assign(array(
				$returnVar => $this->data[$returnVar],
			));
		}

		return $this->data[$returnVar];
	}

	public function FastPrint($template = "")
	{
		if (empty($template))
		{
			$template = $this->LAST;
		}

		if (!empty($this->data[$template]))
		{
			print $this->data[$template];
		}

		return $this;
	}

	// ************************************************************

	public function fetch($template = "")
	{
		if (empty($template))
		{
			$template = $this->LAST;
		}

		if (!isset($this->data[$template]) || empty($this->data[$template]))
		{
			//$this->error("Nothing parsed, nothing printed",0);
			return "";
		}

		return $this->data[$template];
	}

	public function getTemplateFilenameByName($template)
	{
		if (!$this->defined($template))
		{
			return null;
		}

		return $this->FILELIST[$template];
	}

	public function getTemplateByName($template)
	{
		$fullTemplatePath = $this->getTemplateFilenameByName($template);

		return $fullTemplatePath ? $this->TEMPLATES[$fullTemplatePath] : null;
	}

	/**
	 * @param array $fileContentsList Assoc array [$tag => $fileContents]
	 * @param bool $nl2br
	 * @return $this
	 */
	public function emulateDefine($fileContentsList, $nl2br = false)
	{
		foreach ($fileContentsList as $tag => $contents)
		{
			if ($nl2br)
			{
				$contents = nl2br($contents);
			}

			$this->FILELIST["$tag"] = "#$tag";
			$this->TEMPLATES["#$tag"] = $contents;
		}

		return $this;
	}

	public function define($subFolderOrFileList, $realFileList = null)
	{
	    if (gettype($subFolderOrFileList) == "string" && is_array($realFileList))
	    {
	    	return $this->define2($subFolderOrFileList, $realFileList);
	    }

		foreach ($subFolderOrFileList as $FileTag => $FileName)
		{
			$this->FILELIST["$FileTag"] = $FileName;
		}

		return $this;
	}

	public function define2($subFolder, $fileList, $lng = "")
	{
		if (substr($subFolder, 0, 1) == "~") // tpl/index/..
		{
			$folder = "index";
			$subFolder = substr($subFolder, 1);
		}
		elseif (substr($subFolder, 0, 1) == "^") // root
		{
			$folder = "";
			$subFolder = substr($subFolder, 1);
		}
		elseif (substr($subFolder, 0, 1) == "`") // _core
		{
			$folder = "";
		}
		else // default: tpl/pages
		{
			$folder = $this->default_folder;
		}

		if ($subFolder)
		{
			$subFolder = add_ending_slash($subFolder);
		}

		if ($lng)
		{
			$lng = add_ending_slash($lng);
		}

		if ($folder)
		{
			$folder = add_ending_slash($folder);
		}

		foreach ($fileList as $k => $f)
		{
			if (!is_string($k))
			{
				$k = $f;
			}

			$this->FILELIST["$k"] = $lng . $folder . $subFolder . $f . ".htm";
		}

		return $this;
	}

	// ************************************************************

	/**
	 * @param $token
	 * @param string|null $tplName
	 * @param boolean|null $status
	 * @return FastTemplate
	 */
	public function process($token, $tplName = null, $status = null)
	{
		// 2 params instead of 3
		if (is_bool($tplName) && $status === null)
		{
			$status = $tplName;
			$tplName = strtolower($token);
			$token = strtoupper($token);
		}

		if ($status || $status === null)
		{
			$this->parse($token, $tplName);
		}
		else
		{
			$this->clear_parse($token);
		}

		return $this;
	}

	/** @deprecated */
	public function parse2($token, $tpl_name, $status)
	{
		if ($status)
		{
			return $this->parse($token, $tpl_name);
		}
		else
		{
			$this->clear_parse($token);

			return null;
		}
	}

	public function clear_parse($ReturnVar = "")
	{
		$this->clear($ReturnVar);

		return $this;
	}

	/**
	 * Clears out hash created by call to parse()
	 *
	 * @param string $names
	 * @return $this
	 */
	public function clear($names = null)
	{
		if ($names !== null)
		{
			if (gettype($names) != "array")
			{
				$names = strtoupper($names);

				unset($this->data[$names]);
				unset($this->PARSEVARS[$names]);
			}
			else
			{
				foreach ($names as $val)
				{
					$val = strtoupper($val);

					unset($this->data[$val]);
					unset($this->PARSEVARS[$val]);
				}
			}

			return $this;
		}

		foreach ($this->HANDLE as $key => $val)
		{
			unset($this->data[$key]);
		}

		return $this;
	}

	//************************************************************

	public function clear_all()
	{
		$this->clear();
		$this->clearAssign();
		$this->clear_define();
		$this->clear_tpl();

		return $this;
	}

	/**
	 * @param string $fileHandle
	 * @return $this
	 */
	public function clear_tpl($fileHandle = "")
	{
		if (empty($this->LOADED))
		{
			// Nothing loaded, nothing to clear

			return $this;
		}

		if (empty($fileHandle))
		{
			// Clear ALL fileHandles
			foreach ($this->LOADED as $key => $val)
			{
				unset($this->data[$key]);
			}

			unset($this->LOADED);
		}
		else
		{
			if (!is_array($fileHandle))
			{
				if (isset($this->data[$fileHandle]) || !empty($this->data[$fileHandle]))
				{
					unset($this->LOADED[$fileHandle]);
					unset($this->data[$fileHandle]);
				}
			}
			else
			{
				foreach ($fileHandle as $key => $val)
				{
					unset($this->LOADED[$key]);
					unset($this->data[$key]);
				}
			}
		}

		return $this;
	}

	/**
	 * @param string $fileTag
	 * @return $this
	 */
	public function clear_define($fileTag = "")
	{
		if (empty($fileTag))
		{
			unset($this->FILELIST);

			return $this;
		}

		if (!is_array($fileTag))
		{
			unset($this->FILELIST[$fileTag]);
		}
		else
		{
			foreach ($fileTag as $Tag => $Val)
			{
				unset($this->FILELIST[$Tag]);
			}
		}

		return $this;
	}

	/**
	 * Clears variables set by assign()
	 *
	 * @param null $mask
	 * @return $this
	 */
	public function clearAssign($mask = null)
	{
		if ($mask === null || empty($this->PARSEVARS))
		{
			$this->PARSEVARS = array();

			return $this;
		}

		$ar = array();

		foreach ($this->PARSEVARS as $ref => $val)
		{
			// callback
			if (is_callable($mask) && is_object($mask) && $mask($ref, $val))
			{
				continue;
			}
			// regexp
			elseif (is_string($mask) && preg_match($mask, $ref))
			{
				continue;
			}
			// array
			elseif (is_array($mask) && in_array($ref, $mask))
			{
				continue;
			}

			$ar[$ref] = $val;
		}

		$this->PARSEVARS = $ar;

		return $this;
	}

	/**
	 * dimaninc 2015-02-03
	 * $trailer may be used as prefix if array assigned
	 *
	 * @param $templates
	 * @param string $trailer
	 * @return FastTemplate
	 */
	public function assign($templates, $trailer = "")
	{
		if (gettype($templates) == "array")
		{
			foreach ($templates as $key => $val)
			{
				if (!empty($key))
				{
					// Empty values are allowed
					// Empty Keys are NOT

					$this->PARSEVARS[strtoupper("$trailer$key")] = $val;
				}
			}
		}
		else
		{
			// Empty values are allowed in non-array context now.
			if (!empty($templates))
			{
				$this->PARSEVARS[strtoupper("$templates")] = $trailer;
			}
		}

		return $this;
	}

	/**
	 * Return the value of an assigned variable.
	 * Christian Brandel cbrandel@gmx.de
	 * @deprecated
	 *
	 * @param string $tpl_name
	 * @return string|null
	 */
	public function get_assigned($tpl_name = "")
	{
		return $this->getAssigned($tpl_name);
	}

	/**
	 * @param $token
	 * @return string|null
	 */
	public function getAssigned($token)
	{
		$token = strtoupper($token);

		return isset($this->PARSEVARS["$token"]) ? $this->PARSEVARS["$token"] : null;
	}

	//************************************************************

	public function error($errorMsg, $die = false)
	{
		$this->ERROR = $errorMsg;

		if ($die)
		{
			throw new \Exception($this->ERROR);
		}
		else
		{
			echo "ERROR: $this->ERROR <BR>\n";
		}

		return $this;
	}
}