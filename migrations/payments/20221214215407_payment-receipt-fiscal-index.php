<?php
class diMigration_20221214215407 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20221214215407';
	public static $name = 'Payment receipt: fiscal index';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_receipts 
            ADD INDEX idx_fiscal(fiscal_doc_id, fiscal_mark, fiscal_date, fiscal_session, fiscal_number)");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE payment_receipts 
            DROP INDEX idx_fiscal");
	}
}