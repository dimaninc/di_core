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

	protected function init()
	{
	}

	public function log($message, $module = '')
	{
		simple_debug($message, $module);
	}
}