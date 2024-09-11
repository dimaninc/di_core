<?php

use diCore\Controller\Db;

class diMigration_20240910221312 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20240910221312';
    public static $name = 'Additional variables';

    public function up()
    {
        $folder = Db::getCoreSqlFolder();
        $subFolder = $this->getDb()
            ->getConnection()
            ::isPostgres()
            ? 'postgres/'
            : '';

        $this->executeSqlFile(
            ['additional_variable.sql', 'additional_variable_value.sql'],
            $folder . $subFolder
        );
    }

    public function down()
    {
    }
}
