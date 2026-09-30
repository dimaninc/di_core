<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * @method $this filterById($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByPurpose($value, $operator = null)
 * @method $this filterByChannel($value, $operator = null)
 * @method $this filterByTarget($value, $operator = null)
 * @method $this filterByCodeHash($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 * @method $this filterByAttempts($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 * @method $this filterByExpiredAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByCreatedAt($direction = null)
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::authorization_pin;
    const connection_name = 'default';
    protected $table = 'authorization_pin';
    protected $modelType = 'authorization_pin';

    /**
     * Deletes used and expired rows, but only past the rate window: rate limits count
     * rows by created_at, and an earlier purge would reset them.
     */
    public static function purge($now = null): int
    {
        $now = $now ?? time();
        $model = \diModel::create(static::type);
        $db = $model::getConnection()->getDb();

        return $db->execWrite(
            'DELETE FROM ' .
                $db->escapeTable($model->getTable()) .
                " WHERE created_at < '" .
                \diDateTime::sqlFormat($now - $model::RATE_WINDOW) .
                "' AND (status <> " .
                Status::pending .
                " OR expired_at < '" .
                \diDateTime::sqlFormat($now) .
                "')"
        );
    }
}
