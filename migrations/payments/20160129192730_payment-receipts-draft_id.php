<?php
class diMigration_20160129192730 extends diMigration
{
	public static $idx = "20160129192730";
	public static $name = "Payment receipts: draft_id";
	protected $table = "payment_receipts";

	public function up()
	{
		$this->getDb()->q("ALTER TABLE $this->table ADD COLUMN draft_id bigint AFTER date_payed");
		$this->getDb()->q("ALTER TABLE $this->table ADD UNIQUE INDEX draft_idx(draft_id)");
	}

	public function down()
	{
		$this->getDb()->q("ALTER TABLE $this->table DROP INDEX draft_idx");
		$this->getDb()->q("ALTER TABLE $this->table DROP COLUMN draft_id");
	}
}