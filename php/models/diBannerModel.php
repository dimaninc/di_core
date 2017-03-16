<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:42
 */
/**
 * Class diBannerModel
 * Methods list for IDE
 *
 * @method string	getLng
 * @method string	getPlace
 * @method string	getTitle
 * @method string	getHref
 * @method string	getHrefTarget
 * @method string	getPic
 * @method integer	getPicW
 * @method integer	getPicH
 * @method integer	getPicT
 * @method string	getDate1
 * @method string	getDate2
 * @method integer	getVisible
 * @method integer	getViewsCount
 * @method integer	getClicksCount
 * @method string	getLastViewDate
 * @method integer	getOrderNum
 *
 * @method bool hasLng
 * @method bool hasPlace
 * @method bool hasTitle
 * @method bool hasHref
 * @method bool hasHrefTarget
 * @method bool hasPic
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicT
 * @method bool hasDate1
 * @method bool hasDate2
 * @method bool hasVisible
 * @method bool hasViewsCount
 * @method bool hasClicksCount
 * @method bool hasLastViewDate
 * @method bool hasOrderNum
 *
 * @method diBannerModel setLng($value)
 * @method diBannerModel setPlace($value)
 * @method diBannerModel setTitle($value)
 * @method diBannerModel setHref($value)
 * @method diBannerModel setHrefTarget($value)
 * @method diBannerModel setPic($value)
 * @method diBannerModel setPicW($value)
 * @method diBannerModel setPicH($value)
 * @method diBannerModel setPicT($value)
 * @method diBannerModel setDate1($value)
 * @method diBannerModel setDate2($value)
 * @method diBannerModel setVisible($value)
 * @method diBannerModel setViewsCount($value)
 * @method diBannerModel setClicksCount($value)
 * @method diBannerModel setLastViewDate($value)
 * @method diBannerModel setOrderNum($value)
 */
class diBannerModel extends diModel
{
	const type = diTypes::banner;
	protected $table = "banners";

	public function getRedirectHref()
	{
		return diLib::getWorkerPath('banner', 'redirect', [$this->getId()]) .
			'?uri=' . urlencode(diRequest::server('REQUEST_URI'));
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'redirect_href' => $this->getRedirectHref(),
		]);
	}
}