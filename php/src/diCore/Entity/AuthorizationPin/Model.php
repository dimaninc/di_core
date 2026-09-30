<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Database\FieldType;
use diCore\Traits\Model\AutoTimestamps;

/**
 * One-time confirmation: a short code (typed by a person) or a long token (a link, a
 * bot deep link). The core stores, issues, verifies and rate-limits; delivery is the
 * project's job – issue*() returns the plain value once, only its hash is stored.
 *
 * The target (email, E.164 phone) is compared as is: the project normalizes it.
 * Verification failures return null without a reason, so a response can't tell
 * a registered target from an unknown one.
 *
 * @method integer	getUserId
 * @method integer	getPurpose
 * @method integer	getChannel
 * @method string	getTarget
 * @method string	getCodeHash
 * @method integer	getStatus
 * @method integer	getAttempts
 * @method string	getIp
 * @method string	getExpiredAt
 *
 * @method $this setUserId($value)
 * @method $this setPurpose($value)
 * @method $this setChannel($value)
 * @method $this setTarget($value)
 * @method $this setCodeHash($value)
 * @method $this setStatus($value)
 * @method $this setAttempts($value)
 * @method $this setIp($value)
 * @method $this setExpiredAt($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::authorization_pin;
    const connection_name = 'default';
    const table = 'authorization_pin';
    protected $table = 'authorization_pin';

    // Override in the project model to tune; the constants are read through static::.
    const CODE_LENGTH = 6;
    const CODE_TTL = 600;
    const MAX_ATTEMPTS = 3;
    const TOKEN_BYTES = 16;
    const RATE_WINDOW = 3600;
    const MAX_PER_TARGET = 5;
    const MAX_PER_IP = 10;

    protected static $fieldTypes = [
        'id' => FieldType::int,
        'user_id' => FieldType::int,
        'purpose' => FieldType::int,
        'channel' => FieldType::int,
        'target' => FieldType::string,
        'code_hash' => FieldType::string,
        'status' => FieldType::int,
        'attempts' => FieldType::int,
        'payload' => FieldType::json,
        'ip' => FieldType::string,
        'expired_at' => FieldType::timestamp,
        'created_at' => FieldType::timestamp,
        'updated_at' => FieldType::timestamp,
    ];

    /**
     * @param array $options user_id, payload, ip, ttl, length
     * @return array [static $pin, string $code]
     * @throws RateLimitedException
     */
    public static function issueCode(
        int $purpose,
        int $channel,
        string $target,
        array $options = []
    ): array {
        if (static::isRateLimited($purpose, $target, $options['ip'] ?? null)) {
            throw new RateLimitedException('Too many codes requested');
        }

        $code = static::generateCode($options['length'] ?? static::CODE_LENGTH);
        $pin = static::store(
            $purpose,
            $channel,
            $target,
            $code,
            $options['ttl'] ?? static::CODE_TTL,
            $options
        );

        return [$pin, $code];
    }

    /**
     * Not rate-limited: tokens are issued by the system (mailings), not on request.
     *
     * @param array $options user_id, payload, ip
     * @return array [static $pin, string $token]
     */
    public static function issueToken(
        int $purpose,
        int $channel,
        string $target,
        int $ttl,
        array $options = []
    ): array {
        $token = bin2hex(random_bytes(static::TOKEN_BYTES));
        $pin = static::store($purpose, $channel, $target, $token, $ttl, $options);

        return [$pin, $token];
    }

    /**
     * Earlier codes stay valid: invalidating them would let anyone cancel a victim's
     * code by requesting a new one. Each wrong guess burns an attempt on every live
     * code of the target, so the guess budget stays MAX_ATTEMPTS in total.
     *
     * @return static|null the verified pin
     */
    public static function verifyCode(int $purpose, string $target, string $code)
    {
        $hash = static::hashValue($code);
        $now = \diDateTime::sqlFormat();

        /** @var Collection $pins */
        $pins = Collection::create(static::type)
            ->select(['id', 'code_hash'])
            ->filterByPurpose($purpose)
            ->filterByTarget($target)
            ->filterByStatus(Status::pending)
            ->filterByExpiredAt($now, '>')
            ->filterByAttempts(static::MAX_ATTEMPTS, '<')
            ->orderById('desc');

        /** @var static $pin */
        foreach ($pins as $pin) {
            // The attempt is claimed before comparing: parallel guesses can't exceed the limit.
            if (!static::claimAttempt($pin->getId())) {
                continue;
            }

            if (hash_equals($pin->getCodeHash(), $hash) && static::markVerified($pin->getId())) {
                return static::createById($pin->getId());
            }
        }

        return null;
    }

    /**
     * @return static|null the consumed pin
     */
    public static function consumeToken(int $purpose, string $token)
    {
        // A short code must not be matched here by its hash without its target.
        if (!preg_match('/^[0-9a-f]{' . static::TOKEN_BYTES * 2 . '}\z/', $token)) {
            return null;
        }

        /** @var static $pin */
        $pin = Collection::create(static::type)
            ->select(['id'])
            ->filterByCodeHash(static::hashValue($token))
            ->filterByPurpose($purpose)
            ->filterByStatus(Status::pending)
            ->filterByExpiredAt(\diDateTime::sqlFormat(), '>')
            ->getFirstItem();

        return $pin->getId() && static::markVerified($pin->getId())
            ? static::createById($pin->getId())
            : null;
    }

    public static function isRateLimited(int $purpose, string $target, $ip = null): bool
    {
        $since = \diDateTime::sqlFormat(time() - static::RATE_WINDOW);

        $byTarget = Collection::create(static::type)
            ->filterByPurpose($purpose)
            ->filterByTarget($target)
            ->filterBy('created_at', '>=', $since)
            ->count();

        if ($byTarget >= static::MAX_PER_TARGET) {
            return true;
        }

        return $ip &&
            Collection::create(static::type)
                ->filterByIp($ip)
                ->filterByPurpose($purpose)
                ->filterBy('created_at', '>=', $since)
                ->count() >= static::MAX_PER_IP;
    }

    public static function generateCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        return $code;
    }

    public static function hashValue(string $value): string
    {
        return hash('sha256', $value);
    }

    protected static function store(
        int $purpose,
        int $channel,
        string $target,
        string $value,
        int $ttl,
        array $options
    ) {
        $pin = static::create()
            ->setUserId($options['user_id'] ?? null)
            ->setPurpose($purpose)
            ->setChannel($channel)
            ->setTarget($target)
            ->setCodeHash(static::hashValue($value))
            ->setStatus(Status::pending)
            ->setAttempts(0)
            ->setIp($options['ip'] ?? null)
            ->setExpiredAt(\diDateTime::sqlFormat(time() + $ttl));

        if (isset($options['payload'])) {
            $pin->setJsonData('payload', $options['payload']);
        }

        return $pin->save();
    }

    protected static function claimAttempt(int $id): bool
    {
        return static::guardedUpdate(
            $id,
            'attempts = attempts + 1',
            'status = ' . Status::pending . ' AND attempts < ' . (int) static::MAX_ATTEMPTS
        );
    }

    protected static function markVerified(int $id): bool
    {
        return static::guardedUpdate(
            $id,
            'status = ' . Status::verified,
            'status = ' . Status::pending
        );
    }

    protected static function guardedUpdate(int $id, string $set, string $guard): bool
    {
        $db = static::getConnection()->getDb();

        return $db->execWrite(
            'UPDATE ' .
                $db->escapeTable(static::table) .
                " SET $set, updated_at = '" .
                \diDateTime::sqlFormat() .
                "' WHERE id = $id AND $guard"
        ) === 1;
    }
}
