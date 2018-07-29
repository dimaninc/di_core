<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 11:28
 */

namespace diCore\Controller;

use diCore\Data\Types;
use diCore\Entity\CommentCache\Model as CacheModel;
use diCore\Entity\CommentCache\Collection as CacheCol;
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

	public function updateCommentsHtmlForAction()
	{
		try {
			$CC = Comment::basicCreate();
			$cacheCommentId = $this->param(0, \diRequest::get('id', 0));

			if ($cacheCommentId) {
				$CC->rebuildHtml($cacheCommentId);
			} else {
				$CC->rebuildAll();
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

	public function updateCommentCollectionsAction()
	{
		try {
			$CC = Comment::basicCreate();

			/** @var CacheCol $col */
			$col = \diCollection::create(Types::comment_cache);
			$col
				->filterByActive(1)
				->filterByHtml('', '!=');
			/** @var CacheModel $cacheModel */
			foreach ($col as $cacheModel)
			{
				$CC->rebuildByTarget($cacheModel->getTargetType(), $cacheModel->getTargetId());
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