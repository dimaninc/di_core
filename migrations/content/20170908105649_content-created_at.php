<?php
class diMigration_20170908105649 extends diMigration
{
    public static $idx = '20170908105649';
    public static $name = 'Content: timestamps';

    public function up()
    {
        $fields = $this->getDb()->getFields('content');

        if (!isset($fields['created_at'])) {
            $this->getDb()->q("ALTER TABLE content
                ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ");
        }

        if (!isset($fields['updated_at'])) {
            $this->getDb()->q("ALTER TABLE content
                ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ");
        }
    }

    public function down()
    {
        $this->getDb()->q("ALTER TABLE content
			DROP COLUMN created_at,
			DROP COLUMN updated_at
		");
    }
}
