<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.04.2017
 * Time: 15:40
 */

namespace diCore\Controller;

class Search extends \diBaseController
{
    public function rebuildIndexForTableAction()
    {
        $table = $this->param(0);

        $ok = \diSearch::makeTableIndex($table);

        return [
            'ok' => $ok,
            'table' => $table,
        ];
    }
}