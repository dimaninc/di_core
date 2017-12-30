<?php
class diMigration_20170914195643 extends diMigration
{
	public static $idx = "20170914195643";
	public static $name = "Mail queue: reply-to and settings";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE mail_queue
			ADD COLUMN reply_to VARCHAR(255) DEFAULT '' AFTER recipient_id,
			ADD COLUMN settings TEXT AFTER news_id
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE mail_queue
			DROP COLUMN reply_to,
			DROP COLUMN settings
		");
	}
}