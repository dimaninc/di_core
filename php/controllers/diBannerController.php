<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.08.2016
 * Time: 16:08
 */
class diBannerController extends diBaseController
{
	public function redirectAction()
	{
		$bannerId = $this->param(0, 0);
		$uri = diRequest::get('uri', '');

		/** @var diBannerModel $banner */
		$banner = \diModel::create(diTypes::banner, $bannerId);

		if ($banner->exists())
		{
			\diBannerDailyStatModel::add($banner->getId(), \diBanners::STAT_CLICK, $uri);

			$this->redirectTo($banner->get('href'));

			return null;
		}
		else
		{
			return [
				'ok' => false,
				'message' => 'Banner #' . $bannerId . ' not found',
			];
		}
	}
}