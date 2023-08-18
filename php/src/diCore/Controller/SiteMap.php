<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 17.10.2018
 * Time: 18:16
 */

namespace diCore\Controller;

class SiteMap extends \diBaseController
{
    public function generateAction()
    {
        $this->adminRightsHardCheck();

        \diSiteMapGenerator::createAndGenerate();
    }
}
