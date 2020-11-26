<?php
/*
	// dimaninc

	// 2010/09/11
		* birth date
*/

class diFTP
{
	private $ftp;
	private $host;
	private $port;
	private $login;
	private $password;
	private $dir;
	private $sourceEncoding = 'utf-8';
	private $destEncoding = 'utf-8';

	public function __construct($host, $login, $password, $port = 21)
	{
		$this->connect($host, $login, $password, $port);
	}

	public function passiveMode()
	{
		if (!@ftp_pasv($this->ftp, true)) {
			$this->log('warning', 'Unable to switch into passive mode');
		}

		return $this;
	}

	public function connect($host, $login, $password, $port = 21)
	{
		if (substr($host, 0, 6) == "ftp://") {
			$host = substr($host, 6);
		}

		$x = strpos($host, "/");
		if ($x !== false) {
			$this->dir = substr($host, $x);
			$host = substr($host, 0, $x);
		} else {
			$this->dir = "/";
		}

		$this->host = $host;
		$this->port = $port;
		$this->login = $login;
		$this->password = $password;

		$this->ftp = ftp_connect($this->host, $this->port);

		if (!ftp_login($this->ftp, $this->login, $this->password)) {
			$this->log("fatal", "Unable to login to host $this->host:$this->port as $this->login");
		}

		if (!$this->dir) {
			$this->dir = ftp_pwd($this->ftp);
		}

		$this->change_dir($this->dir);
	}

	protected function log($status, $message, $details = "")
	{
		/*
		if ($status == "fatal")
		{
			dierror($message, DIE_WARNING); //DIE_FATAL
		}
		elseif ($status != "ok")
		{
			dierror($message, DIE_WARNING);
		}
		*/

		if ($message) {
			simple_debug($message, 'diFTP log');
		}

		if ($details) {
			simple_debug($details, 'diFTP log');
		}

		return $this;
	}

    public function setSourceEncoding($enc)
    {
        $this->sourceEncoding = $enc;

        return $this;
    }

	public function setDestEncoding($enc)
    {
        $this->destEncoding = $enc;

        return $this;
    }

    public function fixEncoding($name)
    {
        if ($this->sourceEncoding !== $this->destEncoding) {
            $name = mb_convert_encoding($name, $this->sourceEncoding, $this->destEncoding);
        }

        return $name;
    }

	public function change_dir($dir)
	{
		$this->dir = $this->fixEncoding($dir);

		if (!ftp_chdir($this->ftp, $this->dir)) {
			$this->log("warning", "Unable to change dir to $this->dir");
		}

		return $this;
	}

	public function get_dir()
	{
		$ar = array(
			"dirs" => array(),
			"files" => array(),
			"filesizes" => array(),
			"links" => array(),
			"other" => array(),
		);

		if ($contents = ftp_rawlist($this->ftp, "")) {
			foreach ($contents as $i => $rec) {
				$item = preg_split("/\s+/", $rec, 9);
				$item_type = substr($item[0], 0, 1);

				if ($item_type == "d") {
					$ar["dirs"][] = $item[8];
				} elseif ($item_type == "l") {
					$ar["links"][] = $item[8];
				} elseif ($item_type == "-") {
					$ar["files"][] = $item[8];
					$ar["filesizes"][] = $item[4];
				} elseif ($item_type == "+") {
					/* it's something on an anonftp server */
					$eplf = explode(",", join(" ", $item), 5);

					if ($eplf[2] == "r") {
						$ar["files"][] = trim($eplf[4]);
						$ar["filesizes"][] = substr($eplf[3], 1);
					} elseif ($eplf[2] == "/") {
						$ar["dirs"][] = trim($eplf[3]);
					}
				}
			}
		}

		return $ar;
	}

	public function make_dir($dir, $silent = false)
	{
		if (!@ftp_mkdir($this->ftp, add_ending_slash($this->dir) . $this->fixEncoding($dir))) {
			if (!$silent) {
				$this->log("warning", "Unable to create dir $this->dir/$dir");
			}
		}

		return $this;
	}

	public function make_dir_chain($path, $mode = 0775)
	{
		$folders_ar = explode("/", $this->fixEncoding($path));
		$path = "";

		foreach ($folders_ar as $f) {
			if ($f) {
				if ($path) {
					$path = add_ending_slash($path);
				}

				$path .= $f;

				$this->make_dir($path, true);

				if ($mode) {
					$this->chmod($path, $mode);
				}
			}
		}

		return $this;
	}

	// $fn_ar - string or array
	public function chmod($fn_ar, $mode)
	{
		if (!is_array($fn_ar)) {
			$fn_ar = array($fn_ar);
		}

		foreach ($fn_ar as $fn) {
		    $fn = $this->fixEncoding($fn);
			if (!@ftp_chmod($this->ftp, $mode, $fn)) {
				$this->log("warning", "Unable to chmod $this->dir/$fn");
			}
		}

		return $this;
	}

	public function delete($fn_ar)
	{
		if (!is_array($fn_ar)) {
			$fn_ar = array($fn_ar);
		}

		foreach ($fn_ar as $fn) {
            $fn = $this->fixEncoding($fn);
			if (!ftp_delete($this->ftp, $fn)) {
				$this->log("warning", "Unable to delete $this->dir/$fn");
			}
		}

		return $this;
	}

	public function get($fn_ar, $dir_to_store = "")
	{
		if (!is_array($fn_ar)) {
			$fn_ar = array($fn_ar);
		}

		$dir_to_store = add_ending_slash($dir_to_store);

		foreach ($fn_ar as $fn) {
            $fn = $this->fixEncoding($fn);
			$local_fn = $dir_to_store.basename($fn);

			@unlink($local_fn);

			if (!@ftp_get($this->ftp, $local_fn, $fn, FTP_BINARY)) {
				$this->log("warning", "Unable to get $this->dir/$fn");
			}
		}

		return $this;
	}

	public function put($fn_ar, $keep_folders_tree = false, $remoteFilenames = [])
	{
		$result = true;

		if (!is_array($fn_ar)) {
			$fn_ar = [$fn_ar];
		}

		if (!is_array($remoteFilenames)) {
            $remoteFilenames = [$remoteFilenames];
        }

		if (
            count($remoteFilenames) &&
		    count($fn_ar) !== count($remoteFilenames)
        ) {
		    throw new \Exception('FTP: Local and remote filenames count should match');
        }

		foreach ($fn_ar as $idx => $fn) {
            $fn = $this->fixEncoding($fn);
            $remote_fn = $remoteFilenames[$idx] ?? basename($fn);

			if ($keep_folders_tree) {
				$remote_dir = dirname($remoteFilenames[$idx] ?? $fn);

				if (!$remoteFilenames && $remote_fn != $fn) {
					$this->make_dir_chain($remote_dir);
				}

				$remote_fn = $fn;
			}

			if (!@ftp_put($this->ftp, $remote_fn, $fn, FTP_BINARY)) {
				$this->log("warning", "Unable to put file $fn to $this->dir/$remote_fn");

				$result = false;
			}
		}

		return $result;
	}

	public function raw($command)
    {
        @ftp_raw($this->ftp, $command);

        return $this;
    }

    public function utfOn()
    {
        return $this->raw('OPTS UTF8 ON');
    }

	public function simple_put($local_fn, $remote_fn)
	{
        $remote_fn = $this->fixEncoding($remote_fn);

		if (!@ftp_put($this->ftp, $remote_fn, $local_fn, FTP_BINARY)) {
			$this->log("warning", "Unable to put file $local_fn to $this->dir/$remote_fn");

			return false;
		}

		return true;
	}

	public function close()
	{
		ftp_close($this->ftp);

		return $this;
	}
}