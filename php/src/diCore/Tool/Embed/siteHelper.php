<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.06.2016
 * Time: 22:14
 */

namespace diCore\Tool\Embed;

class siteHelper extends Helper
{
	protected static $queryParamsToRemove = [
	];

	protected static $identifyParams = [
		App::QUERY_PARAM => 'site-embed',
	];
}