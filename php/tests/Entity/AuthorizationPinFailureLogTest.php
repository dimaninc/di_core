<?php

namespace diCore\Tests\Entity;

use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Tool\Auth\PinCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthorizationPinTestDb.php';

/**
 * The failed-check budget: per (ip, target), one across purposes, degrading instead of
 * throwing. Live tests: see AuthorizationPinTestDb.
 */
class AuthorizationPinFailureLogTest extends TestCase
{
    const IP = '203.0.113.7';
    const OTHER_IP = '203.0.113.99';
    const TARGET = 'user@example.com';

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        AuthorizationPinTestDb::clear();
    }

    public function testRetentionOutlastsTheWindow(): void
    {
        $this->assertGreaterThanOrEqual(
            FailureLog::FAILURE_WINDOW,
            FailureLog::RETENTION_DAYS * 86400
        );
    }

    public function testLiveBudgetIsPerIpAndTargetAndPerWindow(): void
    {
        AuthorizationPinTestDb::need($this);

        $this->recordFailures(FailureLog::MAX_FAILURES - 1, 1);
        $this->assertFalse(FailureLog::isLimited(self::IP, self::TARGET));

        $this->recordFailures(1, 1);
        $this->assertTrue(FailureLog::isLimited(self::IP, self::TARGET));
        $this->assertFalse(
            FailureLog::isLimited(self::OTHER_IP, self::TARGET),
            'another ip'
        );
        $this->assertFalse(
            FailureLog::isLimited(self::IP, 'other@example.com'),
            'another target'
        );

        AuthorizationPinTestDb::age(
            FailureLog::TABLE,
            '1 = 1',
            FailureLog::FAILURE_WINDOW + 60
        );

        $this->assertSame(
            0,
            FailureLog::countRecent(self::IP, self::TARGET),
            'past the window'
        );
    }

    public function testLiveBudgetIsSharedByPurposes(): void
    {
        AuthorizationPinTestDb::need($this);

        $this->recordFailures(FailureLog::MAX_FAILURES / 2, 1);
        $this->recordFailures(FailureLog::MAX_FAILURES / 2, 2);

        $this->assertTrue(FailureLog::isLimited(self::IP, self::TARGET));
    }

    public function testLiveCaseAndSpacesAroundTheTargetAreOneKey(): void
    {
        AuthorizationPinTestDb::need($this);

        FailureLog::record(self::IP, 1, ' USER@Example.com ');

        $this->assertSame(1, FailureLog::countRecent(self::IP, self::TARGET));
    }

    public function testLiveRowStoresOnlyTheHashAndANullableUser(): void
    {
        AuthorizationPinTestDb::need($this);

        FailureLog::record(self::IP, 1, self::TARGET, 42);
        FailureLog::record(self::IP, 1, self::TARGET, 0);
        FailureLog::record(self::IP, 1, self::TARGET);

        $hash = PinCode::targetHash(self::TARGET);
        $table = FailureLog::TABLE;

        $this->assertSame(
            3,
            AuthorizationPinTestDb::count($table, "target_hash = '$hash'")
        );
        $this->assertSame(1, AuthorizationPinTestDb::count($table, 'user_id = 42'));
        $this->assertSame(
            2,
            AuthorizationPinTestDb::count($table, 'user_id IS NULL'),
            'unknown user'
        );
    }

    public function testLivePurgeDropsOnlyRowsPastTheRetention(): void
    {
        AuthorizationPinTestDb::need($this);

        $this->recordFailures(2, 1);
        AuthorizationPinTestDb::age(
            FailureLog::TABLE,
            'id = (SELECT m FROM (SELECT MIN(id) AS m FROM ' .
                FailureLog::TABLE .
                ') AS t)',
            FailureLog::RETENTION_DAYS * 86400 + 60
        );

        $this->assertSame(1, FailureLog::purge());
        $this->assertSame(1, AuthorizationPinTestDb::count(FailureLog::TABLE));
    }

    public function testLiveMissingTableDegradesInsteadOfThrowing(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $db->q('ALTER TABLE ' . FailureLog::TABLE . ' RENAME TO apf_hidden');

        try {
            FailureLog::record(self::IP, 1, self::TARGET);
            $this->assertSame(0, FailureLog::countRecent(self::IP, self::TARGET));
            $this->assertFalse(FailureLog::isLimited(self::IP, self::TARGET));
            $this->assertNull(FailureLog::purge());
        } finally {
            $db->q('ALTER TABLE apf_hidden RENAME TO ' . FailureLog::TABLE);
        }
    }

    private function recordFailures(int $times, int $purpose): void
    {
        for ($i = 0; $i < $times; $i++) {
            FailureLog::record(self::IP, $purpose, self::TARGET);
        }
    }
}
