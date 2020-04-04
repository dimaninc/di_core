<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.05.2015
 * Time: 19:53
 */

namespace diCore\Helper;

use diCore\Data\Types;
use diCore\Database\Connection;
use diCore\Traits\BasicCreate;

class Banner
{
	use BasicCreate;

	const table = 'banners';
	const statTable = 'banner_daily_stat';
	const urisTable = 'banner_uris';

	const STAT_VIEW = 1;
	const STAT_CLICK = 2;

	public static $statTypes = [
		self::STAT_VIEW => 'view',
		self::STAT_CLICK => 'click',
	];

	public static $placesAr = [
		'left' => 'Слева',
		'right' => 'Справа',
		'top' => 'Сверху',
		'down' => 'Снизу',
	];

	public static $hrefTargetsAr = [
		'' => 'Том же окне',
		'blank' => 'Новом окне',
	];

	public static $replacePattern = "/\[BANNER\-([0-9]+)x([0-9]+)\]/";

	public static $ignoredDomainsAr = [
		'google.com',
		'google.ru',
		'yandex.ru',
		'ya.ru',
		'search.live.com',
		'rambler.ru',
		'mail.ru',
		'nigma.ru',
		'yahoo.com',
		'aport.ru',
		'search.ukr.net',
		'msn.com',
	];

	public static function getTpl()
	{
		global $Z;

		return $Z->getTpl();
	}

	/** @deprecated  */
	public static function getDb()
	{
		return Connection::get()->getDb();
	}

	final public static function getPlaces()
	{
		/** @var self $class */
		$class = self::getClass();

		return $class::$placesAr;
	}

	public static function getPlaceTitle($placeName)
	{
		$ar = static::getPlaces();

		return $ar[$placeName];
	}

	/** @deprecated  */
	public static function getHtml($r, $banner_token = '__BANNER_PIC')
	{
		self::storeStat($r->id, self::STAT_VIEW);

		$tpl_name = $r->pic
			? ($r->pic_t == 4 || $r->pic_t == 13 ? 'pic_a_swf' : 'pic_a_img')
			: 'text_banner';

		return self::getTpl()
			->assign([
				'TITLE'         => str_out($r->title),
				'HREF'          => "/redir.php?bid={$r->id}&uri=".urlencode(\diRequest::requestUri()),
				'HREF_TARGET'   => $r->href_target == 'blank' ? ' target=_blank' : '',
				'PIC'           => get_pics_folder(self::table).$r->pic,
				'PIC_W'         => $r->pic_w,
				'PIC_H'         => $r->pic_h,
				'BG'            => isset($r->background) ? str_out($r->background) : '#FFFFFF',
			], 'PIC_')
			->parse($banner_token, $tpl_name);
	}

	/** @deprecated  */
	public static function redirect()
	{
		$uri = \diRequest::get("uri", "") ?: \diRequest::get("url", "");
		$bid = \diRequest::get("bid", 0);

		$b_r = $bid
			? self::getDb()->r(self::table, "WHERE id='$bid' and NOW() BETWEEN date1 and date2 and visible='1'")
			: null;

		if ($b_r)
		{
			self::storeStat($b_r->id, self::STAT_CLICK, $uri);

			$uri = $b_r->href;
		}
		elseif (substr($uri, 0, 7) != "http://" && substr($uri, 0, 8) != "https://" && substr($uri, 0, 6) != "ftp://")
		{
			$uri = "http://{$uri}";
		}

		header("Location: $uri");
	}

	public static function isCurrentDomainIgnored()
	{
		$ip = get_user_ip();
		$host = $ip ? @gethostbyaddr($ip) : false;

		if (!$host)
		{
			return false;
		}

		for ($i = 0; $i < count(self::$ignoredDomainsAr); $i++)
		{
			if (substr($host, strlen($host) - strlen(self::$ignoredDomainsAr[$i])) == self::$ignoredDomainsAr[$i])
			{
				return true;
			}
		}

		return false;
	}

	/** @deprecated  */
	public static function storeStat($banner_id, $type, $uri = "")
	{
		return \diBannerDailyStatModel::add($banner_id, $type, $uri);
	}

	public static function needToShow($banner_id)
	{
		$uri = str_replace("%", "%%", str_in($_SERVER["REQUEST_URI"]));
		$query = "WHERE banner_id='$banner_id' and '$uri' LIKE REPLACE(uri,'*','%%') and positive='%d'";

		$u_r1 = self::getDb()->r(self::urisTable, sprintf($query, 1));
		$u_r2 = self::getDb()->r(self::urisTable, sprintf($query, 0));

		return !!$u_r1 && !$u_r2;
	}

	public static function storeUris($banner_id)
	{
		$signsAr = [
			0 => "negative",
			1 => "positive",
		];

		self::getDb()->delete(self::urisTable, "WHERE banner_id='$banner_id'");

		foreach ($signsAr as $positive => $key)
		{
			if (empty($_POST[$key]))
			{
				continue;
			}

			foreach ($_POST[$key] as $idx => $uri)
			{
				$uri = str_in($uri);

				if ($uri)
				{
					if ($uri[0] != "/" && $uri[0] != "*")
					{
						$uri = "/$uri";
					}

					self::getDb()->insert(self::urisTable, [
						"banner_id" => $banner_id,
						"uri" => $uri,
						"positive" => $positive,
					]);
				}

			}
		}
	}

	public static function getRawBannersForPlace($place = null)
	{
		/** @var \diBannerCollection $banners */
		$banners = \diCollection::create(Types::banner);

		if ($place)
		{
			$banners
				->filterByPlace($place);
		}

		$banners
			->filterManual("NOW() BETWEEN date1 and date2")
			->filterByVisible(1)
			->orderByLastViewDate();

		return $banners;
	}

	public static function getBannersForPlace($place = null)
	{
		$ar = [];
		/** @var self $class */
		$class = static::getClass();
		$banners = $class::getRawBannersForPlace($place);

		/** @var \diBannerModel $banner */
		foreach ($banners as $banner)
		{
			if (!$class::needToShow($banner->getId()))
			{
				continue;
			}

			$ar[$banner->getId()] = $banner;
		}

		return $ar;
	}

	public static function printForPlace($place)
	{
		if (!\diConfiguration::exists("max_banners_count[$place]"))
		{
			return '';
		}

		$banners = static::getBannersForPlace($place);
		$limit = (int)\diConfiguration::get("max_banners_count[$place]");
		$cc = 0;

		/** @var \diBannerModel $banner */
		foreach ($banners as $banner)
		{
			static::getHtml((object)$banner->get(), "BANNER_PIC");

			self::getTpl()->parse(strtoupper($banner->getPlace()) . "_BANNER_ROWS", ".{$banner->getPlace()}_banner_row");

			if (++$cc >= $limit)
			{
				break;
			}
		}

		return self::getTpl()->parse_if_not_empty(strtoupper($place) . "_BANNERS_BLOCK", strtoupper($place) . "_BANNER_ROWS");
	}

	public static function printAll()
	{
		$bannersByPlace = [];

		foreach (self::$placesAr as $place => $title)
		{
			$bannersByPlace[$place] = self::printForPlace($place);
		}

		return $bannersByPlace;
	}

	public static function getAll($shuffle = false)
	{
		$limits = [];
		$counts = [];

		foreach (self::$placesAr as $place => $title)
		{
			$limits[$place] = (int)\diConfiguration::safeGet("banners_max_count[$place]", 0);
			$counts[$place] = 0;
		}

		$ar = [];

		/** @var \diBannerCollection $banners */
		$banners = \diCollection::create(\diTypes::banner);
		$banners
			->filterByVisible(1)
			->filterManual('NOW() BETWEEN `date1` and `date2`')
			->orderByLastViewDate();

		/** @var \diBannerModel $banner */
		foreach ($banners as $banner)
		{
			if (
				static::needToShow($banner->getId()) &&
				(
					!$limits[$banner->getPlace()] ||
					($limits[$banner->getPlace()] && $counts[$banner->getPlace()] < $limits[$banner->getPlace()])
				)
			)
			{
				$ar[] = $banner;

				$counts[$banner->getPlace()]++;

				\diBannerDailyStatModel::add($banner->getId(), self::STAT_VIEW);
			}
		}

		if ($shuffle)
		{
			shuffle($ar);
		}

		return $ar;
	}
}