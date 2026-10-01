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
 * @method $this filterByPayload($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 * @method $this filterByExpiredAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByPurpose($direction = null)
 * @method $this orderByChannel($direction = null)
 * @method $this orderByTarget($direction = null)
 * @method $this orderByCodeHash($direction = null)
 * @method $this orderByStatus($direction = null)
 * @method $this orderByAttempts($direction = null)
 * @method $this orderByPayload($direction = null)
 * @method $this orderByIp($direction = null)
 * @method $this orderByExpiredAt($direction = null)
 *
 * @method $this selectId
 * @method $this selectUserId
 * @method $this selectPurpose
 * @method $this selectChannel
 * @method $this selectTarget
 * @method $this selectCodeHash
 * @method $this selectStatus
 * @method $this selectAttempts
 * @method $this selectPayload
 * @method $this selectIp
 * @method $this selectExpiredAt
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::authorization_pin;
    const connection_name = 'default';
    protected $table = 'authorization_pin';
    protected $modelType = 'authorization_pin';

    /**
     * Deletes used and expired rows, but only past the widest limit window
     * (Model::limitHorizon()): rate limits count rows by created_at, and an earlier
     * purge would reset them. The failed-check log has its own FailureLog::purge().
     */
    public static function purge($now = null): int
    {
        $now = $now ?? time();
        /** @var Model $model */
        $model = \diModel::create(static::type);
        $db = $model::getConnection()->getDb();

        return $db->execWrite(
            'DELETE FROM ' .
                $db->escapeTable($model::tableName()) .
                " WHERE created_at < '" .
                \diDateTime::sqlFormat($now - $model::limitHorizon()) .
                "' AND (status <> " .
                Status::pending .
                " OR expired_at < '" .
                \diDateTime::sqlFormat($now) .
                "')"
        );
    }
}
