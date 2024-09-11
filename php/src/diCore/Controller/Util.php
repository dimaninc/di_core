<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.08.2023
 * Time: 16:10
 */

namespace diCore\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Database\Connection;
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

    public function _getDbStatusAction()
    {
        $ar = [];

        /**
         * @var string $name
         * @var Connection $conn
         */
        foreach (Connection::getAll() as $name => $conn) {
            $ar[$name] = $conn->checkHealth();
        }

        if (!array_product($ar)) {
            throw HttpException::internalServerError($ar);
        }

        return $this->okay();
    }

    public function _getCrashAction()
    {
        throw new \Exception('App crashed');
    }
}
