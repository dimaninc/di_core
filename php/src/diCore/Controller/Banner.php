<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.02.2018
 * Time: 12:26
 */

namespace diCore\Controller;

class Banner extends \diBaseController
{
    public function redirectAction()
    {
        $bannerId = $this->param(0, 0);
        $uri = \diRequest::get('uri', '');

        /** @var \diBannerModel $banner */
        $banner = \diModel::create(\diTypes::banner, $bannerId);

        if ($banner->exists()) {
            \diBannerDailyStatModel::add(
                $banner->getId(),
                \diCore\Helper\Banner::STAT_CLICK,
                $uri
            );

            $this->redirectTo($banner->get('href'));

            return null;
        } else {
            return [
                'ok' => false,
                'message' => 'Banner #' . $bannerId . ' not found',
            ];
        }
    }
}
