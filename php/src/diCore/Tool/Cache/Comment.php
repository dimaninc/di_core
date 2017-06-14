<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 11:39
 */

namespace diCore\Tool\Cache;

use diCore\Database\Connection;
use diCore\Entity\Comment\Model;
use diCore\Data\Types;

class Comment
{
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
}