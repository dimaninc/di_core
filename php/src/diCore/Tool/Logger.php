<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:22
 */

namespace diCore\Tool;


class Logger
{
	use \diSingleton;

	const SUB_FOLDER = 'log/debug/';
	const EXTENSION = '.txt';
	const DATE_TIME_FORMAT = '[d.m.Y H:i:s]';
	const CHMOD = 0777;

	const PURPOSE_SIMPLE = 1;
	const PURPOSE_VARIABLE = 2;

	protected function init()
	{
	}

	public function getFolder()
	{
		return \diCore\Data\Config::getLogFolder();
	}

	protected function getFilename($purpose, $fnSuffix = '')
	{
		return \diDateTime::format('Y_m_d') . $fnSuffix . static::EXTENSION;
	}

	protected function getFullFilename($purpose, $fnSuffix = '')
	{
		return $this->getFolder() . static::SUB_FOLDER . $this->getFilename($purpose, $fnSuffix);
	}

	protected function getDateTime($purpose)
	{
		return \diDateTime::format(static::DATE_TIME_FORMAT);
	}

	protected function saveLine($line, $purpose, $fnSuffix = '')
	{
		$fn = $this->getFullFilename($purpose, $fnSuffix);

		$f = fopen($fn, 'a');
		fputs($f, $this->getDateTime($purpose) . ' ' . $line . "\n");
		fclose($f);

		chmod($fn, static::CHMOD);

		return $this;
	}

	public function log($message, $module = '', $fnSuffix = '')
	{
		if ($module)
		{
			$module = "[$module]";
		}

		$this->saveLine($module . ' ' . $message, self::PURPOSE_SIMPLE, $fnSuffix);

		return $this;
	}

	public function variable()
	{
		$arguments = func_get_args();

		foreach ($arguments as $arg)
		{
			$this->saveLine(print_r($arg, true) ?: var_export($arg, true), self::PURPOSE_VARIABLE);
		}

		return $this;
	}
}