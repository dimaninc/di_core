<?php

namespace diCore\Tests\Entity;

use diCore\Entity\AuthorizationPin\Channel;
use diCore\Entity\AuthorizationPin\Collection;
use diCore\Entity\AuthorizationPin\Model;
use diCore\Entity\AuthorizationPin\Purpose;
use diCore\Entity\AuthorizationPin\RateLimitedException;
use diCore\Entity\AuthorizationPin\Status;
use diCore\Entity\AuthorizationPin\Verdict;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthorizationPinTestDb.php';

class QuickLockPin extends Model
{
    const LOCK_TIMEOUT = 0;

    public static function lockNames(int $purpose, string $target, $ip): array
    {
        return static::limitLockNames($purpose, $target, $ip);
    }
}

/**
 * Tests marked "live" need a server, see AuthorizationPinTestDb. Without it they are skipped.
 */
class AuthorizationPinTest extends TestCase
{
    const PURPOSE = 1;
    const OTHER_PURPOSE = 2;
    const TARGET = 'user@example.com';

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    private $secret = [];

    protected function setUp(): void
    {
        AuthorizationPinTestDb::clear();
        $this->secret = [
            $_ENV[Model::CODE_SECRET_ENV] ?? null,
            getenv(Model::CODE_SECRET_ENV),
        ];
        $this->setSecret(null);
    }

    protected function tearDown(): void
    {
        [$env, $getenv] = $this->secret;

        if ($env === null) {
            unset($_ENV[Model::CODE_SECRET_ENV]);
        } else {
            $_ENV[Model::CODE_SECRET_ENV] = $env;
        }

        putenv(
            $getenv === false
                ? Model::CODE_SECRET_ENV
                : Model::CODE_SECRET_ENV . '=' . $getenv
        );
    }

    public function testCodesAreKeyedWithTheSecretTokensAreNot(): void
    {
        $this->assertSame(
            hash('sha256', '123456'),
            Model::hashCode('123456'),
            'no secret'
        );

        $this->setSecret('s3cret');
        $this->assertSame(
            hash_hmac('sha256', '123456', 's3cret'),
            Model::hashCode('123456')
        );

        $_ENV[Model::CODE_SECRET_ENV] = '';
        $this->assertSame(
            hash_hmac('sha256', '123456', 's3cret'),
            Model::hashCode('123456'),
            'getenv() when $_ENV has none'
        );

        $token = str_repeat('ab', 16);
        $this->assertSame(hash('sha256', $token), Model::hashToken($token));
        $this->assertSame(
            Model::hashToken($token),
            Model::hashValue($token),
            'deprecated alias'
        );
    }

    public function testLiveCodeIssuedUnderTheSecretNeedsTheSecret(): void
    {
        $this->needServer();
        $this->setSecret('s3cret');

        [$pin, $code] = Model::issueCode(
            self::PURPOSE,
            Channel::email,
            self::TARGET
        );
        $this->assertSame(
            hash_hmac('sha256', $code, 's3cret'),
            Model::createById($pin->getId())->getCodeHash()
        );

        $this->setSecret('another');
        $this->assertSame(
            Verdict::mismatch,
            Model::verifyCode(self::PURPOSE, self::TARGET, $code)->getVerdict()
        );

        $this->setSecret('s3cret');
        $this->assertTrue(
            Model::verifyCode(self::PURPOSE, self::TARGET, $code)->isOk()
        );
    }

    public function testLiveTokenHashStaysPlainSha256WithASecret(): void
    {
        $this->needServer();
        $this->setSecret('s3cret');

        [$pin, $token] = Model::issueToken(
            self::PURPOSE,
            Channel::email,
            self::TARGET,
            86400
        );

        $this->assertSame(
            hash('sha256', $token),
            Model::createById($pin->getId())->getCodeHash()
        );
        $this->assertTrue(Model::findToken(self::PURPOSE, $token)->exists());
    }

    /** Only someone who knows a right code can cancel the others. */
    public function testLiveVerifiedCodeInvalidatesTheOtherPendingCodes(): void
    {
        $this->needServer();

        [$a, $codeA] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        [, $codeB] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        [$other] = Model::issueCode(
            self::PURPOSE,
            Channel::email,
            'other@example.com'
        );
        [$otherPurpose] = Model::issueCode(
            self::OTHER_PURPOSE,
            Channel::email,
            self::TARGET
        );

        $this->assertTrue(
            Model::verifyCode(self::PURPOSE, self::TARGET, $codeB)->isOk()
        );
        $this->assertSame(
            Verdict::missing,
            Model::verifyCode(self::PURPOSE, self::TARGET, $codeA)->getVerdict()
        );
        $this->assertEquals(
            Status::invalidated,
            Model::createById($a->getId())->getStatus()
        );
        $this->assertEquals(
            Status::pending,
            Model::createById($other->getId())->getStatus(),
            'another target'
        );
        $this->assertEquals(
            Status::pending,
            Model::createById($otherPurpose->getId())->getStatus(),
            'another purpose'
        );
    }

    public function testLiveLockHeldElsewhereRefusesAsBusy(): void
    {
        $this->needServer();

        $names = QuickLockPin::lockNames(self::PURPOSE, self::TARGET, '10.0.0.1');
        $this->assertCount(2, $names);
        $this->assertStringContainsString(':ip:', $names[0], 'ip first');
        $this->assertCount(
            1,
            QuickLockPin::lockNames(self::PURPOSE, self::TARGET, null)
        );

        $holder = AuthorizationPinTestDb::secondConnection()->getDb();

        foreach ($names as $held) {
            $this->assertTrue($holder->acquireNamedLock($held, 1));

            try {
                QuickLockPin::issueCode(
                    self::PURPOSE,
                    Channel::email,
                    self::TARGET,
                    ['ip' => '10.0.0.1']
                );
                $this->fail('Issued while the lock was held elsewhere');
            } catch (RateLimitedException $e) {
                $this->assertSame(Model::LIMIT_BUSY, $e->getLimit());
            } finally {
                $holder->releaseNamedLock($held);
            }
        }

        $this->assertSame(0, AuthorizationPinTestDb::count('authorization_pin'));

        // The failed attempt released what it had taken: the next one gets both.
        QuickLockPin::issueCode(self::PURPOSE, Channel::email, self::TARGET, [
            'ip' => '10.0.0.1',
        ]);
        $this->assertSame(1, AuthorizationPinTestDb::count('authorization_pin'));
        $this->assertTrue(
            $holder->acquireNamedLock($names[0], 0),
            'released after success'
        );
        $holder->releaseNamedLock($names[0]);
    }

    /**
     * Without the locks every process of a synchronized burst passes the count before
     * any of them inserts (reproduced: 20 of 20 on MySQL, 14–20 on PostgreSQL).
     */
    public function testLiveParallelBurstCannotExceedTheTargetLimit(): void
    {
        $this->needServer();

        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix are needed to fork a burst');
        }

        $processes = 4 * Model::MAX_PER_TARGET;
        $start = microtime(true) + 1.5;
        $pids = [];

        for ($i = 0; $i < $processes; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                try {
                    AuthorizationPinTestDb::reconnectInChild();
                    time_sleep_until($start);
                    Model::issueCode(self::PURPOSE, Channel::email, self::TARGET, [
                        'ip' => '10.0.0.' . (($i % 2) + 1),
                    ]);
                } catch (\Throwable $e) {
                }

                // No shutdown: it would close the parent's inherited connections.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $this->assertGreaterThan(0, $pid, 'fork failed');
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $this->assertSame(
            Model::MAX_PER_TARGET,
            AuthorizationPinTestDb::count(
                'authorization_pin',
                "target = '" . self::TARGET . "'"
            )
        );
    }

    public function testCodeIsDigitsOfRequestedLength(): void
    {
        foreach ([4, 6, 8] as $length) {
            $this->assertMatchesRegularExpression(
                '/^\d{' . $length . '}$/',
                Model::generateCode($length)
            );
        }
    }

    public function testOnlyExactlyCodeLengthDigitsAreWellFormed(): void
    {
        $this->assertTrue(Model::isWellFormedCode('012345'));

        foreach (
            ['12345', '1234567', '12345a', ' 123456', "123456\n", '', '１２３４５６']
            as $code
        ) {
            $this->assertFalse(
                Model::isWellFormedCode($code),
                var_export($code, true)
            );
        }
    }

    public function testShortCodeIsNotAcceptedAsToken(): void
    {
        // Rejected by format before any query, so no server needed.
        $this->assertFalse(Model::isWellFormedToken('123456'));
        $this->assertFalse(Model::isWellFormedToken(str_repeat('Z', 32)));
        $this->assertFalse(Model::isWellFormedToken(str_repeat('a', 32) . "\n"));
        $this->assertTrue(Model::isWellFormedToken(str_repeat('a', 32)));
    }

    public function testAuthenticationPurposeIsReserved(): void
    {
        $this->assertSame(1, Purpose::authentication);
        $this->assertSame('authentication', Purpose::name(Purpose::authentication));
        $this->assertNotEmpty(Purpose::title(Purpose::authentication));
    }

    public function testPurgeHorizonCoversTheWidestLimitWindow(): void
    {
        $this->assertGreaterThanOrEqual(Model::RATE_WINDOW, Model::limitHorizon());
        $this->assertGreaterThanOrEqual(Model::DAY_WINDOW, Model::limitHorizon());
    }

    public function testLiveCodeVerifiesOnceAndIsStoredHashed(): void
    {
        $this->needServer();

        [$pin, $code] = Model::issueCode(
            self::PURPOSE,
            Channel::email,
            self::TARGET,
            [
                'user_id' => 7,
                'payload' => ['next' => '/lk'],
            ]
        );

        $this->assertSame(
            Model::hashCode($code),
            Model::createById($pin->getId())->getCodeHash()
        );
        $this->assertSame(
            Verdict::missing,
            Model::verifyCode(self::OTHER_PURPOSE, self::TARGET, $code)->getVerdict()
        );
        $this->assertSame(
            Verdict::missing,
            Model::verifyCode(
                self::PURPOSE,
                'other@example.com',
                $code
            )->getVerdict()
        );

        $result = Model::verifyCode(self::PURPOSE, self::TARGET, $code);
        $this->assertTrue($result->isOk());
        $verified = $result->getPin();
        $this->assertEquals(Status::verified, $verified->getStatus()); // mysqli returns numbers as strings
        $this->assertEquals(7, $verified->getUserId());
        $this->assertSame('/lk', $verified->getJsonData('payload', 'next'));

        $again = Model::verifyCode(self::PURPOSE, self::TARGET, $code);
        $this->assertSame(Verdict::missing, $again->getVerdict(), 'single use');
        $this->assertFalse($again->getPin()->exists(), 'empty model, not null');
    }

    public function testLiveWrongGuessesBurnAttemptsOfEveryLiveCode(): void
    {
        $this->needServer();

        [, $first] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        [, $second] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        $wrong = self::wrong($first, $second);

        for ($i = 1; $i < Model::MAX_ATTEMPTS; $i++) {
            $this->assertSame(
                Verdict::mismatch,
                Model::verifyCode(self::PURPOSE, self::TARGET, $wrong)->getVerdict()
            );
        }

        // The claimed attempt was the last one on every live code.
        $this->assertSame(
            Verdict::exhausted,
            Model::verifyCode(self::PURPOSE, self::TARGET, $wrong)->getVerdict()
        );
        $this->assertSame(
            Verdict::exhausted,
            Model::verifyCode(self::PURPOSE, self::TARGET, $first)->getVerdict()
        );
        $this->assertSame(
            Verdict::exhausted,
            Model::verifyCode(self::PURPOSE, self::TARGET, $second)->getVerdict()
        );
    }

    public function testLiveRightCodeOnTheLastAttemptStillPasses(): void
    {
        $this->needServer();

        [, $code] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);

        for ($i = 1; $i < Model::MAX_ATTEMPTS; $i++) {
            Model::verifyCode(self::PURPOSE, self::TARGET, self::wrong($code));
        }

        $this->assertTrue(
            Model::verifyCode(self::PURPOSE, self::TARGET, $code)->isOk()
        );
    }

    public function testLiveNewCodeDoesNotCancelEarlierOne(): void
    {
        $this->needServer();

        [, $first] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);

        $this->assertTrue(
            Model::verifyCode(self::PURPOSE, self::TARGET, $first)->isOk()
        );
    }

    public function testLiveCodeWithoutUserStoresNullUserId(): void
    {
        $this->needServer();

        [$none] = Model::issueCode(self::PURPOSE, Channel::email, 'none@example.com');
        [$zero] = Model::issueCode(self::PURPOSE, Channel::email, 'zero@example.com', [
            'user_id' => 0,
        ]);
        [$user] = Model::issueToken(self::PURPOSE, Channel::email, 'user@example.com', 60, [
            'user_id' => 7,
        ]);

        // Not 0: a project's foreign key to its users table would reject it.
        $this->assertSame(
            2,
            AuthorizationPinTestDb::count(
                'authorization_pin',
                "user_id IS NULL AND id IN ({$none->getId()}, {$zero->getId()})"
            )
        );
        $this->assertSame(
            1,
            AuthorizationPinTestDb::count('authorization_pin', "user_id = 7 AND id = {$user->getId()}")
        );
    }

    public function testLiveExpiredCodeFails(): void
    {
        $this->needServer();

        [, $code] = Model::issueCode(self::PURPOSE, Channel::sms, '+79991234567', [
            'ttl' => -1,
        ]);

        $result = Model::verifyCode(self::PURPOSE, '+79991234567', $code);
        $this->assertSame(Verdict::expired, $result->getVerdict());
        $this->assertFalse($result->getPin()->exists());
    }

    public function testLiveRateLimitByTargetAndIp(): void
    {
        $this->needServer();

        for ($i = 0; $i < Model::MAX_PER_TARGET; $i++) {
            Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        }

        try {
            Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
            $this->fail('Target limit not applied');
        } catch (RateLimitedException $e) {
            $this->assertSame(Model::LIMIT_TARGET, $e->getLimit());
        }

        // Another purpose has its own budget.
        Model::issueCode(self::OTHER_PURPOSE, Channel::email, self::TARGET);

        for ($i = 0; $i < Model::MAX_PER_IP; $i++) {
            Model::issueCode(
                self::OTHER_PURPOSE,
                Channel::email,
                "u$i@example.com",
                ['ip' => '10.0.0.1']
            );
        }

        $this->assertSame(
            Model::LIMIT_IP,
            Model::exceededRateLimit(
                self::OTHER_PURPOSE,
                'new@example.com',
                '10.0.0.1'
            )
        );
        $this->assertTrue(
            Model::isRateLimited(self::OTHER_PURPOSE, 'new@example.com', '10.0.0.1')
        );
        $this->assertNull(
            Model::exceededRateLimit(
                self::OTHER_PURPOSE,
                'new@example.com',
                '10.0.0.2'
            )
        );

        $this->expectException(RateLimitedException::class);
        Model::issueCode(self::OTHER_PURPOSE, Channel::email, 'new@example.com', [
            'ip' => '10.0.0.1',
        ]);
    }

    public function testLiveDailyCapCountsCodesOlderThanTheHour(): void
    {
        $this->needServer();

        for ($i = 0; $i < Model::MAX_PER_TARGET_DAY; $i++) {
            Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
            // Past the hourly window, inside the day.
            AuthorizationPinTestDb::age(
                'authorization_pin',
                '1 = 1',
                Model::RATE_WINDOW + 60
            );
        }

        $this->assertSame(
            Model::LIMIT_TARGET_DAY,
            Model::exceededRateLimit(self::PURPOSE, self::TARGET)
        );

        AuthorizationPinTestDb::age(
            'authorization_pin',
            '1 = 1',
            Model::DAY_WINDOW + 60
        );

        $this->assertNull(
            Model::exceededRateLimit(self::PURPOSE, self::TARGET),
            'a day later'
        );
    }

    public function testLiveTokensOfAnotherPurposeDoNotEatTheCodeBudget(): void
    {
        $this->needServer();

        for ($i = 0; $i < Model::MAX_PER_TARGET_DAY; $i++) {
            Model::issueToken(
                self::OTHER_PURPOSE,
                Channel::email,
                self::TARGET,
                86400
            );
        }

        $this->assertNull(Model::exceededRateLimit(self::PURPOSE, self::TARGET));
    }

    public function testLiveTokenIsFoundWithoutConsumingAndConsumedOnce(): void
    {
        $this->needServer();

        [, $token] = Model::issueToken(
            self::PURPOSE,
            Channel::telegram,
            self::TARGET,
            86400,
            ['user_id' => 5]
        );

        $this->assertFalse(Model::findToken(self::OTHER_PURPOSE, $token)->exists());
        $found = Model::findToken(self::PURPOSE, $token);
        $this->assertTrue($found->exists());
        $this->assertEquals(5, $found->getUserId());
        $this->assertTrue(
            Model::findToken(self::PURPOSE, $token)->exists(),
            'finding does not consume'
        );

        $this->assertFalse(
            Model::consumeToken(self::OTHER_PURPOSE, $token)->exists()
        );
        $this->assertTrue(Model::consumeToken(self::PURPOSE, $token)->exists());
        $this->assertFalse(
            Model::consumeToken(self::PURPOSE, $token)->exists(),
            'single use'
        );
        $this->assertFalse(Model::findToken(self::PURPOSE, $token)->exists());
    }

    public function testLiveExpiredTokenIsNotFound(): void
    {
        $this->needServer();

        [, $token] = Model::issueToken(
            self::PURPOSE,
            Channel::email,
            self::TARGET,
            -1
        );

        $this->assertFalse(Model::findToken(self::PURPOSE, $token)->exists());
        $this->assertFalse(Model::consumeToken(self::PURPOSE, $token)->exists());
    }

    public function testLivePurgeKeepsRowsInsideTheLimitHorizon(): void
    {
        $this->needServer();

        [$old] = Model::issueCode(self::PURPOSE, Channel::email, 'old@example.com', [
            'ttl' => -1,
        ]);
        [$recent] = Model::issueCode(
            self::PURPOSE,
            Channel::email,
            'recent@example.com',
            ['ttl' => -1]
        );
        [$live] = Model::issueToken(
            self::PURPOSE,
            Channel::email,
            'live@example.com',
            86400 * 30
        );

        AuthorizationPinTestDb::age(
            'authorization_pin',
            "id IN ({$old->getId()}, {$live->getId()})",
            Model::limitHorizon() + 60
        );
        // Past the hour, still counted by the daily cap.
        AuthorizationPinTestDb::age(
            'authorization_pin',
            "id = {$recent->getId()}",
            Model::RATE_WINDOW + 60
        );

        $this->assertSame(1, Collection::purge());
        $this->assertFalse(
            Model::createById($old->getId())->exists(),
            'expired past the horizon'
        );
        $this->assertTrue(
            Model::createById($recent->getId())->exists(),
            'still counted by the daily cap'
        );
        $this->assertTrue(
            Model::createById($live->getId())->exists(),
            'pending token'
        );
    }

    private function setSecret(?string $secret): void
    {
        if ($secret === null) {
            unset($_ENV[Model::CODE_SECRET_ENV]);
            putenv(Model::CODE_SECRET_ENV);
        } else {
            $_ENV[Model::CODE_SECRET_ENV] = $secret;
            putenv(Model::CODE_SECRET_ENV . '=' . $secret);
        }
    }

    private function needServer(): void
    {
        AuthorizationPinTestDb::need($this);
    }

    private static function wrong(string ...$codes): string
    {
        for ($i = 0; ; $i++) {
            $candidate = str_pad((string) $i, Model::CODE_LENGTH, '0', STR_PAD_LEFT);

            if (!in_array($candidate, $codes, true)) {
                return $candidate;
            }
        }
    }
}
