<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.02.16
 * Time: 10:41
 */
class GifInfo
{
	public $m_transparentRed;
	public $m_transparentGreen;
	public $m_transparentBlue;
	public $m_signature;
	public $m_version;
	public $m_width;
	public $m_height;
	public $m_colorFlag;
	public $m_backgroundIndex;

	function __construct($filename)
	{
		$fp	= fopen($filename, "rb");
		$result	= fread($fp, 13);
		$this->m_signature = substr($result, 0, 3);
		$this->m_version = substr($result, 3, 3);
		$this->m_width = ord(substr($result, 6, 1)) + ord(substr($result, 7, 1)) * 256;
		$this->m_height = ord(substr($result, 8, 1)) + ord(substr($result, 9, 1)) * 256;
		$this->m_colorFlag = ord(substr($result, 10, 1)) >> 7;
		$this->m_background = ord(substr($result, 11));

		if($this->m_colorFlag)
		{
			$tableSizeNeeded = ($this->m_background + 1) * 3;
			$result = fread($fp, $tableSizeNeeded);
			$this->m_transparentRed	= ord(substr($result, $this->m_background * 3, 1));
			$this->m_transparentGreen = ord(substr($result, $this->m_background * 3 + 1, 1));
			$this->m_transparentBlue = ord(substr($result, $this->m_background * 3 + 2, 1));
		}

		fclose($fp);
	}

	public static function is_ani($filename)
	{
		if (!($fh = @fopen($filename, 'rb')))
			return false;

		$count = 0;
		//an animated gif contains multiple "frames", with each frame having a
		//header made up of:
		// * a static 4-byte sequence (\x00\x21\xF9\x04)
		// * 4 variable bytes
		// * a static 2-byte sequence (\x00\x2C)

		// We read through the file til we reach the end of the file, or we've found
		// at least 2 frame headers
		while(!feof($fh) && $count < 2)
		{
			$chunk = fread($fh, 1024 * 100); //read 100kb at a time
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
		}

		fclose($fh);
		return $count > 1;
	}
}