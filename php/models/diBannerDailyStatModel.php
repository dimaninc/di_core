<?php
/**
 * Created by diModelsManager
 * Date: 19.08.2016
 * Time: 23:19
 */

/**
 * Class diBannerDailyStatModel
 * Methods list for IDE
 *
 * @method integer	getBannerId
 * @method integer	getType
 * @method string	getUri
 * @method string	getDate
 * @method integer	getCount
 *
 * @method bool hasBannerId
 * @method bool hasType
 * @method bool hasUri
 * @method bool hasDate
 * @method bool hasCount
 *
 * @method diBannerDailyStatModel setBannerId($value)
 * @method diBannerDailyStatModel setType($value)
 * @method diBannerDailyStatModel setUri($value)
 * @method diBannerDailyStatModel setDate($value)
 * @method diBannerDailyStatModel setCount($value)
 */
class diBannerDailyStatModel extends diModel
{
	const type = diTypes::banner_daily_stat;
	protected $table = "banner_daily_stat";

	public static function add($bannerId, $statType, $uri = '')
	{
		if (\diBanners::isCurrentDomainIgnored())
		{
			return false;
		}

		/** @var diBannerModel $banner */
		$banner = \diModel::create(diTypes::banner, $bannerId);

		if (!$banner->exists())
		{
			return false;
		}

		$uri = $uri ?: \diRequest::server('REQUEST_URI');
		$date = date("Y-m-d");

		/** @var diBannerDailyStatCollection $statCol */
		$statCol = \diCollection::create(self::type);
		/** @var diBannerDailyStatModel $stat */
		$stat = $statCol
			->filterByBannerId($bannerId)
			->filterByUri($uri)
			->filterByType($statType)
			->filterByDate($date)
			->getFirstItem();

		if ($stat->exists())
		{
			$stat
				->setCount($stat->getCount() + 1);
		}
		else
		{
			$stat
				->setBannerId($bannerId)
				->setType($statType)
				->setUri($uri)
				->setDate($date)
				->setCount(1);
		}

		$stat->save();

		switch ($statType)
		{
			case \diBanners::STAT_VIEW:
				$banner
					->setViewsCount($banner->getViewsCount() + 1)
					->setLastViewDate(\diDateTime::format(\diDateTime::FORMAT_SQL_DATE_TIME));
				break;

			case \diBanners::STAT_CLICK:
				$banner
					->setClicksCount($banner->getClicksCount() + 1);
				break;
		}

		$banner->save();

		return true;
	}
}