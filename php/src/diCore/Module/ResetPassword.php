<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.01.16
 * Time: 10:40
 */

namespace diCore\Module;

class ResetPassword extends \diModule
{
    public function render()
    {
        $this->getTwig()->renderPage('reset_password/page');
    }
}
