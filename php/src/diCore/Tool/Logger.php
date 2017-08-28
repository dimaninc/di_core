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

	protected function init()
	{
	}

	public function getFolder()
	{
		return \diCore\Data\Config::getLogFolder();
	}

	public function log($message, $module = '', $fnSuffix = '')
	{
		$fn = $this->getFolder() . static::SUB_FOLDER . date("Y_m_d").$fnSuffix. static::EXTENSION;

		if ($module)
		{
			$module = " [$module]";
		}

		$f = fopen($fn, "a");
		fputs($f, date("[d.m.Y H:i:s]")."{$module} $message\n");
		fclose($f);

		chmod($fn, 0777);

		return $this;
	}

	public function variable()
	{
		$fn = $this->getFolder() . static::SUB_FOLDER.date("Y_m_d").static::EXTENSION;

		$f = fopen($fn, "a");
		fputs($f, date("[d.m.Y H:i:s] ").var_export(func_get_args(), true)."\n");
		fclose($f);

		chmod($fn, 0777);

		return $this;
	}
}