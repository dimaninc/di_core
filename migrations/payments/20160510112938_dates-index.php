<?php
class diMigration_20160510112938 extends diMigration
{
	public static $idx = "20160510112938";
	public static $name = "Dates -> index";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_drafts
			DROP INDEX idx,
			ADD INDEX idx(target_type,target_id,user_id,date_reserved)
		");
		$this->getDb()->q("ALTER TABLE payment_receipts
			DROP INDEX idx,
			ADD INDEX idx(target_type,target_id,user_id,date_reserved,date_payed)
		");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE payment_drafts
			DROP INDEX idx,
			ADD INDEX idx(target_type,target_id,user_id)
		");
		$this->getDb()->q("ALTER TABLE payment_receipts
			DROP INDEX idx,
			ADD INDEX idx(target_type,target_id,user_id)
		");
	}
}