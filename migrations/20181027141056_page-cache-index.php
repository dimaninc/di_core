<?php
class diMigration_20181027141056 extends \diCore\Database\Tool\Migration
{
	public static $idx = '20181027141056';
	public static $name = 'Page cache index';

	public function up()
	{
		/** @var \diCore\Database\Legacy\Mongo $mongo */
		$mongo = \diCore\Database\Connection::get('mongo_main')->getDb();

		$mongo->getCollectionResource(\diCore\Entity\PageCache\Model::table)
			->createIndex([
				'uri' => 1,
				'active' => 1,
			], [
				'name' => 'uri_idx',
			]);
	}

	public function down()
	{
		/** @var \diCore\Database\Legacy\Mongo $mongo */
		$mongo = \diCore\Database\Connection::get('mongo_main')->getDb();

		$mongo->getCollectionResource(\diCore\Entity\PageCache\Model::table)
			->dropIndex('uri_idx');
	}
}