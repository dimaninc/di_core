<?php
/**
 * Created by \diModelsManager
 * Date: 25.06.2017
 * Time: 16:47
 */

namespace diCore\Entity\CommentCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method integer	getUpdateEveryMinutes
 * @method string	getHtml
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
 * @method integer	getActive
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasUpdateEveryMinutes
 * @method bool hasHtml
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 * @method bool hasActive
 *
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setUpdateEveryMinutes($value)
 * @method Model setHtml($value)
 * @method Model setCreatedAt($value)
 * @method Model setUpdatedAt($value)
 * @method Model setActive($value)
 */
class Model extends \diModel
{
	const type = \diTypes::comment_cache;
	protected $table = 'comment_cache';
}