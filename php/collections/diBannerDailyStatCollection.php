<?php
/**
 * Created by diModelsManager
 * Date: 19.08.2016
 * Time: 23:19
 */

/**
 * Class diBannerDailyStatCollection
 * Methods list for IDE
 *
 * @method diBannerDailyStatCollection filterById($value, $operator = null)
 * @method diBannerDailyStatCollection filterByBannerId($value, $operator = null)
 * @method diBannerDailyStatCollection filterByType($value, $operator = null)
 * @method diBannerDailyStatCollection filterByUri($value, $operator = null)
 * @method diBannerDailyStatCollection filterByDate($value, $operator = null)
 * @method diBannerDailyStatCollection filterByCount($value, $operator = null)
 *
 * @method diBannerDailyStatCollection orderById($direction = null)
 * @method diBannerDailyStatCollection orderByBannerId($direction = null)
 * @method diBannerDailyStatCollection orderByType($direction = null)
 * @method diBannerDailyStatCollection orderByUri($direction = null)
 * @method diBannerDailyStatCollection orderByDate($direction = null)
 * @method diBannerDailyStatCollection orderByCount($direction = null)
 *
 * @method diBannerDailyStatCollection selectId()
 * @method diBannerDailyStatCollection selectBannerId()
 * @method diBannerDailyStatCollection selectType()
 * @method diBannerDailyStatCollection selectUri()
 * @method diBannerDailyStatCollection selectDate()
 * @method diBannerDailyStatCollection selectCount()
 */
class diBannerDailyStatCollection extends diCollection
{
    const type = diTypes::banner_daily_stat;
    protected $table = 'banner_daily_stat';
    protected $modelType = 'banner_daily_stat';
}
