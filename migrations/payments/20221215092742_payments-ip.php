<?php
class diMigration_20221215092742 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20221215092742';
	public static $name = 'Payments: ip';

	public function up()
	{
        $this->getDb()->q("ALTER TABLE payment_drafts
            ADD COLUMN ip bigINT(11) DEFAULT '0',
            DROP INDEX idx,
            ADD INDEX idx (target_type, target_id, user_id, date_reserved, paid, ip)");

        $this->getDb()->q("ALTER TABLE payment_receipts
            ADD COLUMN ip bigINT(11) DEFAULT '0',
            DROP INDEX idx,
            ADD INDEX idx (target_type, target_id, user_id, date_reserved, date_payed, date_uploaded, ip)");
	}

	public function down()
	{
        $this->getDb()->q("ALTER TABLE payment_drafts 
            DROP COLUMN ip");

        $this->getDb()->q("ALTER TABLE payment_receipts 
            DROP COLUMN ip");
	}
}