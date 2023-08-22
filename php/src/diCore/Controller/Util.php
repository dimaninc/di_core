<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.08.2023
 * Time: 16:10
 */

namespace diCore\Controller;

use diCore\Tool\Auth as AuthTool;

class Util extends \diBaseController
{
    public function _getStatusAction()
    {
        return $this->okay();
    }

    public function _getStatusAuthenticatedAction()
    {
        $Auth = AuthTool::create();

        return $this->ok([
            'ok' => $Auth->authorized(),
        ]);
    }

    public function _getCrashAction()
    {
        throw new \Exception('App crashed');
    }
}
