<?php
class diMigration_20190220172119 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20190220172119';
	public static $name = 'Photos: top';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE photos
            ADD COLUMN top tinyint unsigned default '0' AFTER visible,
            DROP INDEX visible_idx,
            ADD INDEX idx(album_id,visible,top,order_num,date)
        ");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE photos
            DROP COLUMN top
        ");
	}
}