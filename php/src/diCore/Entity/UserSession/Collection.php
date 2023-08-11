<?php
/**
 * Created by ModelsManager
 * Date: 11.08.2023
 * Time: 18:09
 */

namespace diCore\Entity\UserSession;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByToken($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByUserAgent($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 * @method $this filterBySeenAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByToken($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByUserAgent($direction = null)
 * @method $this orderByIp($direction = null)
 * @method $this orderBySeenAt($direction = null)
 *
 * @method $this selectId
 * @method $this selectToken
 * @method $this selectUserId
 * @method $this selectUserAgent
 * @method $this selectIp
 * @method $this selectSeenAt
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::user_session;
    const connection_name = 'default';
    protected $table = 'user_session';
    protected $modelType = 'user_session';
}
