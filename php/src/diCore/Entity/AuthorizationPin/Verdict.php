<?php

namespace diCore\Entity\AuthorizationPin;

/**
 * Outcome of a code check (`CheckResult::getVerdict()`).
 *
 * The verdict is for the caller's logs and logic, not for the response: distinct
 * texts per verdict would tell a stranger whether an address is registered and has
 * a live code. Answer every non-ok verdict alike, except `blocked`, which does not
 * depend on the target's existence.
 */
class Verdict
{
    const ok = 'ok';
    /** No pending code: never issued, already used or purged. */
    const missing = 'missing';
    const expired = 'expired';
    /** The attempts of every live code are spent. */
    const exhausted = 'exhausted';
    const mismatch = 'mismatch';
    /** Not a code at all (wrong length, not digits); nothing was looked up. */
    const malformed = 'malformed';
    /** The failure budget of (ip, target) is spent; nothing was looked up. */
    const blocked = 'blocked';

    public static function all(): array
    {
        return [
            self::ok,
            self::missing,
            self::expired,
            self::exhausted,
            self::mismatch,
            self::malformed,
            self::blocked,
        ];
    }
}
