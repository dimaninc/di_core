<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.10.2019
 * Time: 13:09
 */

namespace diCore\Database\Tool;

use diCore\Tool\Localization;

abstract class LocalizationMigration extends Migration
{
    protected $names = [];

    protected function updateCache()
    {
        $L = Localization::basicCreate();
        $L->createCache();

        return $this;
    }

    protected function upWrapper()
    {
        $res = parent::upWrapper();

        $this->updateCache();

        return $res;
    }

    protected function downWrapper()
    {
        $res = parent::downWrapper();

        $this->updateCache();

        return $res;
    }

    public function down()
    {
        $this->getDb()->delete(
            'localization',
            "WHERE {$this->getDb()->escapeField('name')}" .
                $this->getDb()::in($this->names)
        );
    }
}
