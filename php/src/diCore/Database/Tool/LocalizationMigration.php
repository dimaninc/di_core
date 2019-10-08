<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.10.2019
 * Time: 13:09
 */

namespace diCore\Database\Tool;

abstract class LocalizationMigration extends Migration
{
    protected $names = [];

    public function down()
    {
        $this->getDb()->q("DELETE FROM `localization` WHERE `name` IN ('" . join("','", $this->names) . "');");
    }
}