<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.09.2016
 * Time: 12:32
 */

namespace diCore\Tool;

use diCore\Data\Types;

class Redirector
{
	public function __construct()
	{
		/** @var \diRedirectCollection $urls */
		$urls = \diCollection::create(Types::redirect);
		$urls
			->filterByOldUrl(static::currentUrlWithoutQuery())
			->filterByActive(1);

		/** @var \diRedirectModel $url */
		foreach ($urls as $url)
		{
			if ($url->getOldUrl() === static::currentUrl() || !$url->hasStrictForQuery())
			{
				static::go($url);
				die();
			}
		}
	}

	public static function checkAndRedirect()
	{
		new static();
	}

	public static function currentUrl()
	{
		return \diRequest::server('REQUEST_URI');
	}

	public static function urlQuery()
	{
		return \diRequest::server('QUERY_STRING');
	}

	public static function currentUrlWithoutQuery()
	{
		$q = static::urlQuery();

		return $q
			? substr(static::currentUrl(), 0, - (strlen($q) + 1))
			: static::currentUrl();
	}

	public static function go($url, $status = null)
	{
		if ($url instanceof \diRedirectModel)
		{
			$urlModel = $url;
			$status = $url->getStatus();
			$url = $url->getNewUrl();

			if (!$urlModel->hasStrictForQuery() && static::urlQuery())
			{
				$url .= '?' . static::urlQuery();
			}
		}
		else
		{
			$status = $status ?: 301;
		}

		switch ($status)
		{
			case 301:
				header("HTTP/1.1 301 Moved Permanently");
				break;
		}

		header("Location: " . $url);
	}
}