<?php
class diMigration_20170829114748 extends diMigration
{
	public static $idx = "20170829114748";
	public static $name = "Mail queue: attachment -> blob";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE mail_queue CHANGE COLUMN attachment attachment MEDIUMBLOB");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE mail_queue CHANGE COLUMN attachment attachment MEDIUMTEXT");
	}
}