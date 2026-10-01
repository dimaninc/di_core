<?php

namespace diCore\Entity\AuthorizationPin;

use diCore\Database\Connection;
use diCore\Tool\Auth\PinCode;
use diCore\Tool\Logger;

/**
 * Failed code checks (`authorization_pin_failure`). Without a limit on them a
 * stranger who knows an address burns its owner's live code with MAX_ATTEMPTS
 * requests. No entity: only the limit and the purge read the table.
 *
 * The key is the pair (ip, target): not the bare ip – ten junk requests would lock
 * out everyone behind a carrier NAT; not the user id – an unknown target must hit
 * the limit the same way a registered one does, or the refusal is an oracle. One
 * budget across purposes, otherwise each purpose would add its own.
 *
 * The log guards against brute force, it is not a sign-in condition: a broken table
 * must neither lock everybody out nor throw, so every failure degrades to a log line.
 */
class FailureLog
{
    const TABLE = 'authorization_pin_failure';
    const CONNECTION_NAME = 'default';

    const MAX_FAILURES = 10;
    const FAILURE_WINDOW = 3600;
    // Kept past the window for diagnostics; must never be shorter than it.
    const RETENTION_DAYS = 7;

    /**
     * @param int|null $userId null/0 when the target is unknown
     */
    public static function record(
        string $ip,
        int $purpose,
        string $target,
        $userId = null
    ): void {
        try {
            $db = static::db();

            // PHP clock, as created_at of the codes (AutoTimestamps): one clock for the window.
            $written = $db->q(
                'INSERT INTO ' .
                    $db->escapeTable(static::TABLE) .
                    ' (ip, purpose, target_hash, user_id, created_at) VALUES (' .
                    $db->escapeValue($ip) .
                    ', ' .
                    $purpose .
                    ', ' .
                    $db->escapeValue(PinCode::targetHash($target)) .
                    ', ' .
                    ((int) $userId > 0 ? (int) $userId : 'NULL') .
                    ', ' .
                    $db->escapeValue(\diDateTime::sqlFormat()) .
                    ')'
            );
        } catch (\Exception $e) {
            $written = false;
        }

        if (!$written) {
            static::logUnavailable('write');
        }
    }

    public static function isLimited(string $ip, string $target): bool
    {
        return static::countRecent($ip, $target) >= static::MAX_FAILURES;
    }

    public static function countRecent(string $ip, string $target): int
    {
        try {
            $db = static::db();
            $rs = $db->q(
                'SELECT COUNT(*) AS c FROM ' .
                    $db->escapeTable(static::TABLE) .
                    ' WHERE ip = ' .
                    $db->escapeValue($ip) .
                    ' AND target_hash = ' .
                    $db->escapeValue(PinCode::targetHash($target)) .
                    ' AND created_at >= ' .
                    $db->escapeValue(
                        \diDateTime::sqlFormat(time() - static::FAILURE_WINDOW)
                    )
            );
            $row = $rs ? $db->fetch_array($rs) : null;
        } catch (\Exception $e) {
            $row = null;
        }

        if (!$row) {
            static::logUnavailable('read');

            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return int|null deleted rows; null when the purge failed (e.g. the table is
     *   not migrated yet) – a cron purging the codes too must not lose its answer
     */
    public static function purge(): ?int
    {
        try {
            $db = static::db();

            return $db->execWrite(
                'DELETE FROM ' .
                    $db->escapeTable(static::TABLE) .
                    ' WHERE created_at < ' .
                    $db->escapeValue(
                        \diDateTime::sqlFormat(
                            time() - static::RETENTION_DAYS * 86400
                        )
                    )
            );
        } catch (\Exception $e) {
            static::logUnavailable('purge');

            return null;
        }
    }

    protected static function logUnavailable(string $operation): void
    {
        Logger::getInstance()->log(
            "failure log unavailable: $operation",
            PinCode::LOG_MODULE,
            PinCode::LOG_SUFFIX
        );
    }

    protected static function db(): \diDB
    {
        return Connection::get(static::CONNECTION_NAME)->getDb();
    }
}
