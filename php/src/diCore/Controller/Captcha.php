<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.05.2022
 * Time: 11:41
 */

namespace diCore\Controller;

class Captcha extends \diBaseController
{
    public function pngAction()
    {
        $uid = $this->param(0);
        $captcha = \diCore\Tool\Captcha\Captcha::basicCreate($uid);

        $captcha->printPng();
    }
}