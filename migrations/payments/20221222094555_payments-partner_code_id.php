<?php
class diMigration_20221222094555 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20221222094555';
	public static $name = 'Payments: partner_code_id';

    public function up()
    {
        $this->getDb()->q("ALTER TABLE payment_drafts
            ADD COLUMN partner_code_id INT DEFAULT '0'");

        $this->getDb()->q("ALTER TABLE payment_receipts
            ADD COLUMN partner_code_id INT DEFAULT '0'");
    }

    public function down()
    {
        $this->getDb()->q("ALTER TABLE payment_drafts 
            DROP COLUMN partner_code_id");

        $this->getDb()->q("ALTER TABLE payment_receipts 
            DROP COLUMN partner_code_id");
    }
}