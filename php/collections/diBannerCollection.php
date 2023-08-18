<?php
/**
 * Created by diModelsManager
 * Date: 19.08.2016
 * Time: 22:59
 */

/**
 * Class diBannerCollection
 * Methods list for IDE
 *
 * @method diBannerCollection filterById($value, $operator = null)
 * @method diBannerCollection filterByLng($value, $operator = null)
 * @method diBannerCollection filterByPlace($value, $operator = null)
 * @method diBannerCollection filterByTitle($value, $operator = null)
 * @method diBannerCollection filterByHref($value, $operator = null)
 * @method diBannerCollection filterByHrefTarget($value, $operator = null)
 * @method diBannerCollection filterByPic($value, $operator = null)
 * @method diBannerCollection filterByPicW($value, $operator = null)
 * @method diBannerCollection filterByPicH($value, $operator = null)
 * @method diBannerCollection filterByPicT($value, $operator = null)
 * @method diBannerCollection filterByDate1($value, $operator = null)
 * @method diBannerCollection filterByDate2($value, $operator = null)
 * @method diBannerCollection filterByVisible($value, $operator = null)
 * @method diBannerCollection filterByViewsCount($value, $operator = null)
 * @method diBannerCollection filterByClicksCount($value, $operator = null)
 * @method diBannerCollection filterByLastViewDate($value, $operator = null)
 * @method diBannerCollection filterByOrderNum($value, $operator = null)
 *
 * @method diBannerCollection orderById($direction = null)
 * @method diBannerCollection orderByLng($direction = null)
 * @method diBannerCollection orderByPlace($direction = null)
 * @method diBannerCollection orderByTitle($direction = null)
 * @method diBannerCollection orderByHref($direction = null)
 * @method diBannerCollection orderByHrefTarget($direction = null)
 * @method diBannerCollection orderByPic($direction = null)
 * @method diBannerCollection orderByPicW($direction = null)
 * @method diBannerCollection orderByPicH($direction = null)
 * @method diBannerCollection orderByPicT($direction = null)
 * @method diBannerCollection orderByDate1($direction = null)
 * @method diBannerCollection orderByDate2($direction = null)
 * @method diBannerCollection orderByVisible($direction = null)
 * @method diBannerCollection orderByViewsCount($direction = null)
 * @method diBannerCollection orderByClicksCount($direction = null)
 * @method diBannerCollection orderByLastViewDate($direction = null)
 * @method diBannerCollection orderByOrderNum($direction = null)
 *
 * @method diBannerCollection selectId()
 * @method diBannerCollection selectLng()
 * @method diBannerCollection selectPlace()
 * @method diBannerCollection selectTitle()
 * @method diBannerCollection selectHref()
 * @method diBannerCollection selectHrefTarget()
 * @method diBannerCollection selectPic()
 * @method diBannerCollection selectPicW()
 * @method diBannerCollection selectPicH()
 * @method diBannerCollection selectPicT()
 * @method diBannerCollection selectDate1()
 * @method diBannerCollection selectDate2()
 * @method diBannerCollection selectVisible()
 * @method diBannerCollection selectViewsCount()
 * @method diBannerCollection selectClicksCount()
 * @method diBannerCollection selectLastViewDate()
 * @method diBannerCollection selectOrderNum()
 */
class diBannerCollection extends diCollection
{
    const type = diTypes::banner;
    protected $table = 'banners';
    protected $modelType = 'banner';
}
