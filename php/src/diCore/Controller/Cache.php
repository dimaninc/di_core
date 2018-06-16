<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 11:28
 */

namespace diCore\Controller;

use diCore\Tool\Cache\Comment;
use diCore\Tool\Cache\Module;

class Cache extends \diBaseAdminController
{
	public static function rebuildTemplateAndContentCache()
	{
		$Z = new \diCurrentCMS();
		$Z->init_tpl();
		$Z->getTpl()->rebuild_cache();
		$Z->build_content_table_cache();
	}

	public function rebuildAction()
	{
		self::rebuildTemplateAndContentCache();
		\diTwig::flushCache();

		$this->redirect();
	}

	public function updateCommentsCountsAction()
	{
		$errors = Comment::updateCounts();

		if (!$errors) {
			$this->redirect();
		}

		return $errors ?: null;
	}

	public function updateModuleAction()
	{
		try {
			$MC = Module::basicCreate();
			$cacheModuleId = $this->param(0, \diRequest::get('id', 0));

			if ($cacheModuleId) {
				$MC->rebuild($cacheModuleId);
			} else {
				$MC->rebuildAll();
			}
		} catch (\Exception $e) {
			return [
				'done' => false,
				'error' => $e->getMessage(),
			];
		}

		return [
			'done' => true,
		];
	}
}