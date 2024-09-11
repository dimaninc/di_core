<?php

use diCore\Controller\Db;

class diMigration_20180501000000 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20180501000000';
    public static $name = 'Basic CMS init';

    public function up()
    {
        $folder = Db::getCoreSqlFolder();

        $this->executeSqlFile(
            [
                'admins.sql',
                'content.sql',
                'mail_incuts.sql',
                'mail_plans.sql',
                'mail_queue.sql',
            ],
            $folder
        );
    }

    public function down()
    {
    }
}
