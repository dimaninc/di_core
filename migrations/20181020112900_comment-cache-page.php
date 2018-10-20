<?php
class diMigration_20181020112900 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20181020112900';
	public static $name = 'Comment cache: page';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE comment_cache ADD COLUMN page INT DEFAULT '1' AFTER update_every_minutes");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE comment_cache DROP COLUMN page");
	}
}