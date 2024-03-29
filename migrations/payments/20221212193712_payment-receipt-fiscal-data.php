<?php
class diMigration_20221212193712 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20221212193712';
	public static $name = 'Payment receipt: fiscal data';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_receipts
            ADD COLUMN fiscal_mark varchar(16) default '',
            ADD COLUMN fiscal_doc_id varchar(16) default '',
            ADD COLUMN fiscal_date datetime default null,
            ADD COLUMN fiscal_session int default 0,
            ADD COLUMN fiscal_number int default 0
        ");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE payment_receipts
            DROP COLUMN fiscal_mark,
            DROP COLUMN fiscal_doc_id,
            DROP COLUMN fiscal_date, 
            DROP COLUMN fiscal_session,
            DROP COLUMN fiscal_number
       ");
	}
}