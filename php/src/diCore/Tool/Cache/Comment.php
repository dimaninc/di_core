<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 11:39
 */

namespace diCore\Tool\Cache;

use diCore\Controller\Cache;
use diCore\Data\Types;
use diCore\Database\Connection;
use diCore\Entity\Comment\Collection;
use diCore\Entity\Comment\Model;
use diCore\Entity\CommentCache\Model as CacheModel;
use diCore\Entity\CommentCache\Collection as CacheCol;
use diCore\Traits\BasicCreate;

class Comment
{
	use BasicCreate;

	public static function updateCounts()
	{
		$errors = [];
		$addError = function($s) use($errors) {
			$errors[] = $s;
		};

		/** @var Model $comment */
		$comment = \diModel::create(Types::comment);

		Connection::get()->getDb()->rs_go(function($r, $counter) use($comment, $addError) {
			try {
				$model = \diModel::create($r->target_type, $r->target_id);
				$model
					->set($comment::COMMENTS_COUNT_FIELD, $r->count)
					->set($comment::COMMENTS_LAST_DATE_FIELD, $r->dt)
					->save();
			} catch (\Exception $e) {
				$addError($e->getMessage() . ' for ' . \diTypes::getName($r->target_type) . '#' . $r->target_id);
			}
		}, 'comments', 'GROUP BY target_type,target_id', 'target_type, target_id, COUNT(id) AS count, MAX(date) AS dt');

		return $errors;
	}

	/**
	 * @param \diModel|int $targetType
	 * @param null|int $targetId
	 * @return Collection
	 */
	protected function createCollectionByTarget($targetType, $targetId = null, \diComments $Comments = null)
	{
		$col = Collection::createForTarget($targetType, $targetId);
		$col
			->filterByVisible(1);

		return $col;
	}

	public function rebuildByTarget($targetType, $targetId = null, \diComments $Comments = null)
	{
		/** @var Collection $comments */
		$comments = $this->createCollectionByTarget($targetType, $targetId, $Comments);

		/** @var \diCore\Entity\User\Collection $users */
		$users = \diCollection::create(Types::user, $comments->map('user_id'));

		/** @var Model $comment */
		foreach ($comments as $comment)
		{
			/** @var \diCore\Entity\User\Model $user */
			$user = $users[$comment->getUserId()];

			$comment
				->setRelated('user', $user);
		}

		$comments
			->buildCache(Collection::CACHE_BY_TARGET);

		return $this;
	}

	public function rebuildHtml($id)
	{
		if ($id instanceof CacheModel)
		{
			$cacheModel = $id;
		}
		else
		{
			/** @var CacheModel $cacheModel */
			$cacheModel = Model::create(Types::comment_cache, $id);
		}

		if (!$cacheModel->exists())
		{
			throw new \Exception("Module #{$id} doesn't exist");
		}

		$this->rebuildWorker($cacheModel);

		$cacheModel
			->setUpdatedAt(\diDateTime::format(\diDateTime::FORMAT_SQL_DATE_TIME))
			->save();

		return $this;
	}

	protected function rebuildWorker(CacheModel $cacheModel)
	{
		// todo!!!
		$module = CMS::getModuleClassName($cacheModel->getModuleId());
		$module = $module::create($this->createCMS(), [
			'noCache' => true,
			'bootstrapSettings' => $cacheModel->getBootstrapSettings(),
		]);

		$this->storeHtml($cacheModel, $module->getResultPage());

		return $this;
	}

	protected function storeHtml(CacheModel $cacheModel, $content)
	{
		$cacheModel->setHtml($content);

		return $this;
	}

	public function rebuildAll()
	{
		/** @var CacheCol $col */
		$col = \diCollection::create(Types::comment_cache);
		$col
			->filterByActive(1);
		/** @var CacheModel $cache */
		foreach ($col as $cache)
		{
			$this->rebuildHtml($cache);
		}
	}
}