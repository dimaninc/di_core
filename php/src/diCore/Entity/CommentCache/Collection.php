<?php
/**
 * Created by \diModelsManager
 * Date: 25.06.2017
 * Time: 16:47
 */

namespace diCore\Entity\CommentCache;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByUpdateEveryMinutes($value, $operator = null)
 * @method Collection filterByPage($value, $operator = null)
 * @method Collection filterByHtml($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 * @method Collection filterByUpdatedAt($value, $operator = null)
 * @method Collection filterByActive($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByUpdateEveryMinutes($direction = null)
 * @method Collection orderByPage($direction = null)
 * @method Collection orderByHtml($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 * @method Collection orderByUpdatedAt($direction = null)
 * @method Collection orderByActive($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectUpdateEveryMinutes()
 * @method Collection selectPage()
 * @method Collection selectHtml()
 * @method Collection selectCreatedAt()
 * @method Collection selectUpdatedAt()
 * @method Collection selectActive()
 */
class Collection extends \diCollection
{
	const type = \diTypes::comment_cache;
	protected $table = 'comment_cache';
	protected $modelType = 'comment_cache';
}