<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.11.2017
 * Time: 19:11
 */

namespace diCore\Vendor\ResponsiveFileManager;

use diCore\Traits\BasicCreate;

class Settings
{
	use BasicCreate;
	
	public static function get()
	{
		return [
			'maxWidth' => 4000,
			'maxHeight' => 3000,
		];
	}
}