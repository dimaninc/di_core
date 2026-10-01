<?php

namespace diCore\Tool\Auth;

use diCore\Entity\AuthorizationPin\Channel;
use diCore\Entity\AuthorizationPin\CheckResult;
use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Entity\AuthorizationPin\Model;
use diCore\Entity\AuthorizationPin\Purpose;
use diCore\Entity\AuthorizationPin\RateLimitedException;
use diCore\Entity\AuthorizationPin\Verdict;
use diCore\Tool\Logger;

/**
 * Codes on request: issue with limits and deliver (`send()`), check against the
 * failed-check budget (`check()`). Who may get a code at all is the caller's
 * decision. Every extension point is protected – subclass and override.
 *
 * Neither method's outcome may shape the HTTP answer on its own: `send()` returns
 * false for a rate limit, and answering that differently from success would tell a
 * stranger the address is registered; the verdict of `check()` is for logs (see
 * Verdict).
 */
class PinCode
{
    const LOG_MODULE = 'AuthorizationPin';
    const LOG_SUFFIX = '-auth-pin';

    // Limit name in log lines for a refusal by the failed-check budget.
    const LIMIT_FAILURES = 'failures';

    // A project with its own Purpose subclass points this at it (used for names in logs/letters).
    const PURPOSE_CLASS = Purpose::class;

    /**
     * @return static
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Only REMOTE_ADDR: client-sent Client-IP / X-Forwarded-For would let anyone
     * bypass every limit with one header. Behind a trusted proxy, override.
     */
    public static function clientIp(): string
    {
        return (string) \diRequest::server('REMOTE_ADDR', '');
    }

    /**
     * Case and surrounding spaces don't tell people apart: without this one person
     * would get several budgets.
     */
    public static function normalizeTarget(int $channel, string $target): string
    {
        $target = trim($target);

        return $channel === Channel::email ? mb_strtolower($target) : $target;
    }

    /**
     * The only form a target takes in logs and in the failed-check log. Lowercased
     * whatever the channel (a no-op for phones and chat ids), so it doesn't depend on
     * the caller remembering to normalize.
     */
    public static function targetHash(string $target): string
    {
        return hash('sha256', mb_strtolower(trim($target)));
    }

    /**
     * Issue a code and deliver it. false – nothing was sent (a rate limit, logged).
     *
     * @param array $options user_id, payload, ip (default clientIp()), ttl, length,
     *   context (passed to the deliverer: user, language, …)
     * @throws \Exception the channel has no deliverer, or delivery failed
     */
    public function send(
        int $purpose,
        int $channel,
        string $target,
        array $options = []
    ): bool {
        $target = static::normalizeTarget($channel, $target);
        $ip = $this->ipOf($options);
        // Resolved first: a channel without a deliverer must not leave a stored code behind.
        $deliverer = $this->getDeliverer($channel);
        $model = $this->getModelClass();

        try {
            [$pin, $code] = $model::issueCode(
                $purpose,
                $channel,
                $target,
                array_merge(
                    array_intersect_key(
                        $options,
                        array_flip(['user_id', 'payload', 'ttl', 'length'])
                    ),
                    ['ip' => $ip !== '' ? $ip : null]
                )
            );
        } catch (RateLimitedException $e) {
            $this->log($this->limitLogLine($e->getLimit(), $purpose, $target, $ip));

            return false;
        }

        $deliverer->deliver($pin, $code, $this->getDeliveryContext($pin, $options));

        return true;
    }

    /**
     * Check a code typed by a person. A malformed code and a spent failure budget
     * are refused before any lookup (no attempt burnt, nothing recorded); every
     * other refusal is recorded in FailureLog.
     *
     * @param array $options ip (default clientIp()), user_id (for the failure row),
     *   channel (for target normalization, default email)
     */
    public function check(
        int $purpose,
        string $target,
        string $code,
        array $options = []
    ): CheckResult {
        $model = $this->getModelClass();
        $target = static::normalizeTarget(
            (int) ($options['channel'] ?? Channel::email),
            $target
        );
        $code = trim($code);

        // A code that can't match spends no budget: junk would otherwise lock a person out.
        if (!$model::isWellFormedCode($code)) {
            return new CheckResult(Verdict::malformed, $model::create());
        }

        // Before verifyCode(): a refusal by the budget must not burn an attempt of a live code.
        if ($this->isBlocked($target, $options + ['purpose' => $purpose])) {
            return new CheckResult(Verdict::blocked, $model::create());
        }

        $result = $model::verifyCode($purpose, $target, $code);

        if (!$result->isOk()) {
            $this->recordFailure($purpose, $target, $options);
        }

        return $result;
    }

    public function isWellFormedCode(string $code): bool
    {
        $model = $this->getModelClass();

        return $model::isWellFormedCode($code);
    }

    /**
     * Whether (ip, target) spent its failure budget; logged when it did. Call before
     * looking the user up, so the answer and the record can't depend on whether the
     * target is registered.
     *
     * @param array $options ip, purpose (for the log line)
     */
    public function isBlocked(string $target, array $options = []): bool
    {
        $ip = $this->ipOf($options);

        if (!FailureLog::isLimited($ip, $target)) {
            return false;
        }

        $this->log(
            $this->limitLogLine(
                static::LIMIT_FAILURES,
                (int) ($options['purpose'] ?? 0),
                $target,
                $ip
            )
        );

        return true;
    }

    /**
     * @param array $options ip, user_id
     */
    public function recordFailure(
        int $purpose,
        string $target,
        array $options = []
    ): void {
        FailureLog::record(
            $this->ipOf($options),
            $purpose,
            $target,
            $options['user_id'] ?? null
        );
    }

    /**
     * The deliverer of a channel. Email has a default; any other channel needs an override.
     *
     * @throws \InvalidArgumentException
     */
    protected function getDeliverer(int $channel): PinDeliverer
    {
        if ($channel === Channel::email) {
            return new PinEmailDeliverer();
        }

        throw new \InvalidArgumentException(
            'No deliverer for authorization pin channel ' .
                (Channel::name($channel) ?: $channel) .
                ': override ' .
                static::class .
                '::getDeliverer()'
        );
    }

    protected function getDeliveryContext(\diModel $pin, array $options): array
    {
        $model = $this->getModelClass();

        return array_merge(
            [
                'purpose_name' => $this->purposeName((int) $pin->getPurpose()),
                'ttl' => (int) ($options['ttl'] ?? $model::CODE_TTL),
            ],
            $options['context'] ?? []
        );
    }

    /**
     * The project's pin model when it extends the core one, else the core one.
     *
     * @return string|Model
     */
    protected function getModelClass(): string
    {
        $class = \diModel::existsFor(Model::type);

        return $class && is_a($class, Model::class, true) ? $class : Model::class;
    }

    protected function purposeName(int $purpose): string
    {
        $class = static::PURPOSE_CLASS;

        return (string) ($class::name($purpose) ?? $purpose);
    }

    /**
     * The target goes to the log only as a hash: lines of one person stay comparable.
     */
    protected function limitLogLine(
        string $limit,
        int $purpose,
        string $target,
        string $ip
    ): string {
        return sprintf(
            'rate limit: limit=%s purpose=%s target_sha256=%s ip=%s',
            $limit,
            $this->purposeName($purpose),
            static::targetHash($target),
            $ip
        );
    }

    protected function log(string $line): void
    {
        Logger::getInstance()->log($line, static::LOG_MODULE, static::LOG_SUFFIX);
    }

    protected function ipOf(array $options): string
    {
        return (string) ($options['ip'] ?? static::clientIp());
    }
}
