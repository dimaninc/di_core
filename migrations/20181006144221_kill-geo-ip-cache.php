<?php
class diMigration_20181006144221 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20181006144221';
	public static $name = 'Kill geo ip cache';

	public function up()
	{
		$this->getDb()->q("DROP TABLE geo_ip_cache;");
	}

	public function down()
	{
	}
}