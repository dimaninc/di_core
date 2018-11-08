<?php
class diMigration_20181108181233 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20181108181233';
	public static $name = 'Payment receipt: date_uploaded';

	public function up()
	{
		$this->getDb()->q("ALTER TABLE payment_receipts
            ADD COLUMN date_uploaded timestamp DEFAULT NULL AFTER date_payed,
            DROP INDEX idx,
            ADD INDEX idx(target_type,target_id,user_id,date_reserved,date_payed,date_uploaded)
        ");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE payment_receipts
            DROP COLUMN date_uploaded timestamp DEFAULT NULL AFTER date_payed
        ");
	}
}