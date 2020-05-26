<?php
class diMigration_20200526113848 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20200526113848';
	public static $name = 'Admins: address';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE admins ADD COLUMN address varchar(255) default '' AFTER phone");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE admins DROP COLUMN address");
	}
}