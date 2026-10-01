<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Database\FieldType;
use diCore\Traits\Model\AutoTimestamps;

/**
 * One-time confirmation: a short code (typed by a person) or a long token (a link, a
 * bot deep link). The model stores, issues, verifies and rate-limits; delivery and
 * the HTTP answer are `Tool\Auth\PinCode`'s and the caller's – issue*() returns the
 * plain value once, only its hash is stored.
 *
 * The target (email, E.164 phone) is compared as is: normalize it first
 * (`Tool\Auth\PinCode::normalizeTarget()`).
 *
 * @method integer	getUserId
 * @method integer	getPurpose
 * @method integer	getChannel
 * @method string	getTarget
 * @method string	getCodeHash
 * @method integer	getStatus
 * @method integer	getAttempts
 * @method array	getPayload
 * @method string	getIp
 * @method string	getExpiredAt
 *
 * @method bool hasUserId
 * @method bool hasPurpose
 * @method bool hasChannel
 * @method bool hasTarget
 * @method bool hasCodeHash
 * @method bool hasStatus
 * @method bool hasAttempts
 * @method bool hasPayload
 * @method bool hasIp
 * @method bool hasExpiredAt
 *
 * @method $this setUserId($value)
 * @method $this setPurpose($value)
 * @method $this setChannel($value)
 * @method $this setTarget($value)
 * @method $this setCodeHash($value)
 * @method $this setStatus($value)
 * @method $this setAttempts($value)
 * @method $this setPayload($value)
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
    const DAY_WINDOW = 86400;
    const MAX_PER_TARGET_DAY = 10;

    // Names of the issue limits (RateLimitedException::getLimit()).
    const LIMIT_IP = 'ip';
    const LIMIT_TARGET = 'target';
    const LIMIT_TARGET_DAY = 'target_day';
    // The limit locks were not acquired in time: refused rather than issued unguarded.
    const LIMIT_BUSY = 'busy';

    // Seconds to wait for each limit lock.
    const LOCK_TIMEOUT = 3;

    // Environment variable with the HMAC key for short codes (see hashCode()).
    const CODE_SECRET_ENV = 'AUTH_PIN_SECRET';

    private static $unprotectedCodesLogged = false;

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
     * The limit check and the insert run under named locks (ip first, then
     * purpose+target), otherwise a burst of parallel requests all pass the count.
     *
     * Codes and tokens share the issue counter of a purpose: give them different purposes.
     *
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
        $ip = $options['ip'] ?? null;
        $db = static::getConnection()->getDb();
        $held = [];

        try {
            // Fixed order (ip, then target): two requests can't wait for each other.
            foreach (static::limitLockNames($purpose, $target, $ip) as $name) {
                if (!$db->acquireNamedLock($name, static::LOCK_TIMEOUT)) {
                    throw new RateLimitedException(static::LIMIT_BUSY);
                }

                $held[] = $name;
            }

            $limit = static::exceededRateLimit($purpose, $target, $ip);

            if ($limit !== null) {
                throw new RateLimitedException($limit);
            }

            $code = static::generateCode($options['length'] ?? static::CODE_LENGTH);
            $pin = static::store(
                $purpose,
                $channel,
                $target,
                static::hashCode($code),
                $options['ttl'] ?? static::CODE_TTL,
                $options
            );
        } finally {
            foreach (array_reverse($held) as $name) {
                try {
                    $db->releaseNamedLock($name);
                } catch (\Exception $e) {
                    // The session releases it on disconnect anyway; don't mask the result.
                }
            }
        }

        return [$pin, $code];
    }

    /**
     * Not rate-limited: tokens are issued by the system (mailings), not on request.
     * They still count against the issue limits of their purpose – never give tokens
     * the purpose of a code, or a mailing eats a person's code budget.
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
        $pin = static::store(
            $purpose,
            $channel,
            $target,
            static::hashToken($token),
            $ttl,
            $options
        );

        return [$pin, $token];
    }

    /**
     * Earlier codes stay valid: invalidating them would let anyone cancel a victim's
     * code by requesting a new one. Each wrong guess burns an attempt on every live
     * code of the target, so the guess budget stays MAX_ATTEMPTS in total. A right
     * code invalidates the target's other pending codes of the purpose: only someone
     * who knows a correct code can trigger that.
     *
     * The verdict is for logs and logic; see Verdict before exposing it.
     */
    public static function verifyCode(
        int $purpose,
        string $target,
        string $code
    ): CheckResult {
        $hash = static::hashCode($code);
        $now = \diDateTime::sqlFormat();
        $claimed = false;
        $usedInParallel = false;

        /** @var static $pin */
        foreach (static::liveCodes($purpose, $target, $now) as $pin) {
            // The attempt is claimed before comparing: parallel guesses can't exceed the limit.
            if (!static::claimAttempt($pin->getId())) {
                continue;
            }

            $claimed = true;

            if (hash_equals($pin->getCodeHash(), $hash)) {
                if (static::markVerified($pin->getId())) {
                    static::invalidateOtherCodes($purpose, $target, $pin->getId());

                    return new CheckResult(
                        Verdict::ok,
                        static::createById($pin->getId())
                    );
                }

                // A parallel request with the same code consumed it first.
                $usedInParallel = true;
            }
        }

        if ($usedInParallel) {
            $verdict = Verdict::missing;
        } elseif (!$claimed) {
            // No live code, or every one ran out between the read and the claim.
            $verdict = static::verdictWithoutLiveCode($purpose, $target);
        } else {
            $verdict = static::liveCodes(
                $purpose,
                $target,
                \diDateTime::sqlFormat()
            )->count()
                ? Verdict::mismatch
                : Verdict::exhausted;
        }

        return new CheckResult($verdict, static::create());
    }

    /**
     * Looks a token up without consuming it: a link opened by GET identifies, the
     * POST behind a button consumes (mail scanners follow links on their own).
     *
     * @return static|\diModel empty model when there's no live token
     */
    public static function findToken(int $purpose, string $token): \diModel
    {
        // A short code must not be matched here by its hash without its target.
        if (!static::isWellFormedToken($token)) {
            return static::create();
        }

        return Collection::create(static::type)
            ->filterByCodeHash(static::hashToken($token))
            ->filterByPurpose($purpose)
            ->filterByStatus(Status::pending)
            ->filterByExpiredAt(\diDateTime::sqlFormat(), '>')
            ->orderById('desc')
            ->getFirstItem();
    }

    /**
     * @return static|\diModel the consumed pin, empty model if the token is not live
     */
    public static function consumeToken(int $purpose, string $token): \diModel
    {
        $pin = static::findToken($purpose, $token);

        return $pin->exists() && static::markVerified((int) $pin->getId())
            ? static::createById($pin->getId())
            : static::create();
    }

    public static function isWellFormedCode(string $code): bool
    {
        // \z, not $: the latter lets a trailing newline through.
        return (bool) preg_match(
            '/^\d{' . (int) static::CODE_LENGTH . '}\z/',
            $code
        );
    }

    public static function isWellFormedToken(string $token): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{' . static::TOKEN_BYTES * 2 . '}\z/',
            $token
        );
    }

    public static function isRateLimited(
        int $purpose,
        string $target,
        $ip = null
    ): bool {
        return static::exceededRateLimit($purpose, $target, $ip) !== null;
    }

    /**
     * The limit a new code would break, narrowest window first (the one that lets go
     * soonest), or null. Counted per purpose: tokens of another purpose must not eat
     * a person's code budget.
     *
     * @return string|null one of LIMIT_*
     */
    public static function exceededRateLimit(
        int $purpose,
        string $target,
        $ip = null
    ): ?string {
        $now = time();
        $hourAgo = \diDateTime::sqlFormat($now - static::RATE_WINDOW);

        if (
            $ip &&
            Collection::create(static::type)
                ->filterByIp($ip)
                ->filterByPurpose($purpose)
                ->filterBy('created_at', '>=', $hourAgo)
                ->count() >= static::MAX_PER_IP
        ) {
            return static::LIMIT_IP;
        }

        if (
            static::countIssued($purpose, $target, $hourAgo) >=
            static::MAX_PER_TARGET
        ) {
            return static::LIMIT_TARGET;
        }

        $dayAgo = \diDateTime::sqlFormat($now - static::DAY_WINDOW);

        if (
            static::countIssued($purpose, $target, $dayAgo) >=
            static::MAX_PER_TARGET_DAY
        ) {
            return static::LIMIT_TARGET_DAY;
        }

        return null;
    }

    /**
     * The widest window any limit reads, in seconds: rows younger than this must survive purging.
     */
    public static function limitHorizon(): int
    {
        return max(static::RATE_WINDOW, static::DAY_WINDOW);
    }

    public static function generateCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        return $code;
    }

    /**
     * A short code is HMAC-ed with codeHashSecret(): a plain sha256 over 10^6 values
     * is reversed in under a second from a leaked dump. Without a secret it falls back
     * to plain sha256 (logged once per request) – the code is then readable from the
     * dump within its TTL. Changing the secret invalidates live codes, nothing more.
     */
    public static function hashCode(string $code): string
    {
        $secret = static::codeHashSecret();

        if ($secret === '') {
            static::logUnprotectedCodes();

            return hash('sha256', $code);
        }

        return hash_hmac('sha256', $code, $secret);
    }

    /**
     * Plain sha256: 128 random bits need no key, and issued link tokens must keep matching.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @deprecated the token hash; use hashToken() or hashCode()
     */
    public static function hashValue(string $value): string
    {
        return static::hashToken($value);
    }

    protected static function store(
        int $purpose,
        int $channel,
        string $target,
        string $hash,
        int $ttl,
        array $options
    ) {
        $pin = static::create()
            ->setPurpose($purpose)
            ->setChannel($channel)
            ->setTarget($target)
            ->setCodeHash($hash)
            ->setStatus(Status::pending)
            ->setAttempts(0)
            ->setIp($options['ip'] ?? null)
            ->setExpiredAt(\diDateTime::sqlFormat(time() + $ttl));

        // Not set without a user: an int field turns null into 0 on save, and 0 would
        // break a project's foreign key to its users table.
        if ((int) ($options['user_id'] ?? 0) > 0) {
            $pin->setUserId((int) $options['user_id']);
        }

        if (isset($options['payload'])) {
            $pin->setJsonData('payload', $options['payload']);
        }

        return $pin->save();
    }

    /**
     * Override to take the key from elsewhere; '' – codes are hashed without a key.
     */
    protected static function codeHashSecret(): string
    {
        $secret = \diRequest::env(static::CODE_SECRET_ENV);

        if (!$secret) {
            $secret = getenv(static::CODE_SECRET_ENV);
        }

        return is_string($secret) ? $secret : '';
    }

    protected static function logUnprotectedCodes(): void
    {
        if (self::$unprotectedCodesLogged) {
            return;
        }

        self::$unprotectedCodesLogged = true;

        \diCore\Tool\Logger::getInstance()->log(
            static::CODE_SECRET_ENV .
                ' is not set: authorization codes are stored as plain sha256',
            \diCore\Tool\Auth\PinCode::LOG_MODULE,
            \diCore\Tool\Auth\PinCode::LOG_SUFFIX
        );
    }

    /**
     * @return string[] lock names in acquisition order
     */
    protected static function limitLockNames(
        int $purpose,
        string $target,
        $ip
    ): array {
        $table = static::tableName();
        $names = [];

        if ($ip) {
            $names[] = "$table:ip:$purpose:$ip";
        }

        $names[] = "$table:target:$purpose:$target";

        return $names;
    }

    protected static function invalidateOtherCodes(
        int $purpose,
        string $target,
        int $verifiedId
    ): void {
        $db = static::getConnection()->getDb();

        $db->execWrite(
            'UPDATE ' .
                $db->escapeTable(static::tableName()) .
                ' SET status = ' .
                Status::invalidated .
                ", updated_at = '" .
                \diDateTime::sqlFormat() .
                "' WHERE purpose = " .
                $purpose .
                ' AND target = ' .
                $db->escapeValue($target) .
                ' AND status = ' .
                Status::pending .
                ' AND id <> ' .
                $verifiedId
        );
    }

    /**
     * The one source of the table name for raw SQL (the resolved model's, as the collections use).
     */
    public static function tableName(): string
    {
        return static::create()->getTable();
    }

    protected static function liveCodes(
        int $purpose,
        string $target,
        string $now
    ): \diCollection {
        return Collection::create(static::type)
            ->select(['id', 'code_hash'])
            ->filterByPurpose($purpose)
            ->filterByTarget($target)
            ->filterByStatus(Status::pending)
            ->filterByExpiredAt($now, '>')
            ->filterByAttempts(static::MAX_ATTEMPTS, '<')
            ->orderById('desc');
    }

    /**
     * Why there is nothing to check against: a pending code ran out of time, its
     * attempts are spent, or there is none at all.
     */
    protected static function verdictWithoutLiveCode(
        int $purpose,
        string $target
    ): string {
        $pending = function () use ($purpose, $target) {
            return Collection::create(static::type)
                ->filterByPurpose($purpose)
                ->filterByTarget($target)
                ->filterByStatus(Status::pending);
        };

        if ($pending()->filterByExpiredAt(\diDateTime::sqlFormat(), '<=')->count()) {
            return Verdict::expired;
        }

        if ($pending()->filterByAttempts(static::MAX_ATTEMPTS, '>=')->count()) {
            return Verdict::exhausted;
        }

        return Verdict::missing;
    }

    protected static function countIssued(
        int $purpose,
        string $target,
        string $since
    ): int {
        return Collection::create(static::type)
            ->filterByPurpose($purpose)
            ->filterByTarget($target)
            ->filterBy('created_at', '>=', $since)
            ->count();
    }

    protected static function claimAttempt(int $id): bool
    {
        return static::guardedUpdate(
            $id,
            'attempts = attempts + 1',
            'status = ' .
                Status::pending .
                ' AND attempts < ' .
                (int) static::MAX_ATTEMPTS .
                ' AND ' .
                static::notExpiredCondition()
        );
    }

    protected static function markVerified(int $id): bool
    {
        return static::guardedUpdate(
            $id,
            'status = ' . Status::verified,
            'status = ' . Status::pending . ' AND ' . static::notExpiredCondition()
        );
    }

    /**
     * PHP clock, as in the reads: one "expired" boundary for reading and writing.
     */
    protected static function notExpiredCondition(): string
    {
        return "expired_at > '" . \diDateTime::sqlFormat() . "'";
    }

    protected static function guardedUpdate(
        int $id,
        string $set,
        string $guard
    ): bool {
        $db = static::getConnection()->getDb();

        return $db->execWrite(
            'UPDATE ' .
                $db->escapeTable(static::tableName()) .
                " SET $set, updated_at = '" .
                \diDateTime::sqlFormat() .
                "' WHERE id = $id AND $guard"
        ) === 1;
    }
}
