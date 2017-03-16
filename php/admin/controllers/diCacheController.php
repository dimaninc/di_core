<?php

use diCore\Helper\StringHelper;

class diCacheController extends diBaseAdminController
{
	public function rebuildAction()
	{
		self::rebuildTemplateAndContentCache();
		\diTwig::flushCache();

		$this->redirect();
	}

	public static function rebuildTemplateAndContentCache()
	{
		$Z = new \diCurrentCMS();
		$Z->init_tpl();
		$Z->getTpl()->rebuild_cache();
		$Z->build_content_table_cache();
	}

	public function updateCommentsCountsAction()
	{
		$errors = [];
		$addError = function ($s) use($errors)
		{
			$errors[] = $s;
		};

		/** @var diCommentModel $comment */
		$comment = \diModel::create(\diTypes::comment);

		$this->getDb()->rs_go(function($r, $counter) use ($comment, $addError) {
			try {
				$model = \diModel::create($r->target_type, $r->target_id);
				$model
					->set($comment::COMMENTS_COUNT_FIELD, $r->count)
					->set($comment::COMMENTS_LAST_DATE_FIELD, $r->dt)
					->save();
			} catch (\Exception $e) {
				$addError($e->getMessage() . ' for ' . \Kaluga\Data\Types::getName($r->target_type) . '#' . $r->target_id);
			}
		}, 'comments', 'GROUP BY target_type,target_id', 'target_type, target_id, COUNT(id) AS count, MAX(date) AS dt');

		if (!$errors)
		{
			$this->redirect();
			return null;
		}
		else
		{
			return $errors;
		}
	}
}
