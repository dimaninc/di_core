<?php

namespace diCore\Tests\Entity;

use diCore\Database\Connection;
use diCore\Database\Engine;
use diCore\Entity\AuthorizationPin\Channel;
use diCore\Entity\AuthorizationPin\Collection;
use diCore\Entity\AuthorizationPin\Model;
use diCore\Entity\AuthorizationPin\RateLimitedException;
use diCore\Entity\AuthorizationPin\Status;
use PHPUnit\Framework\TestCase;

/**
 * Tests marked "live" need a server: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db
 * or mysql://…; the table is recreated there. Without it they are skipped.
 */
class AuthorizationPinTest extends TestCase
{
    const PURPOSE = 1;
    const OTHER_PURPOSE = 2;
    const TARGET = 'user@example.com';

    private static $live = false;

    public static function setUpBeforeClass(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn) {
            return;
        }

        // Parsed here: Engine::normalizeId() does not resolve the "mysql"/"postgresql" schemes.
        $p = parse_url($dsn);
        $conn = Connection::open(
            [
                'host' => $p['host'],
                'port' => $p['port'] ?? null,
                'login' => rawurldecode($p['user'] ?? ''),
                'password' => rawurldecode($p['pass'] ?? ''),
                'database' => ltrim($p['path'] ?? '', '/'),
            ],
            $p['scheme'] === 'mysql' ? Engine::MYSQL : Engine::POSTGRESQL
        );
        $file = $conn::isPostgres() ? 'postgres/authorization_pin.sql' : 'authorization_pin.sql';
        $db = $conn->getDb();
        $db->q('DROP TABLE IF EXISTS authorization_pin');

        foreach (explode(';', file_get_contents(__DIR__ . '/../../../sql/' . $file)) as $sql) {
            if (trim($sql)) {
                $db->q($sql);
            }
        }

        self::$live = true;
    }

    protected function setUp(): void
    {
        if (self::$live) {
            Connection::get()->getDb()->q('DELETE FROM authorization_pin');
        }
    }

    public function testCodeIsDigitsOfRequestedLength(): void
    {
        foreach ([4, 6, 8] as $length) {
            $this->assertMatchesRegularExpression('/^\d{' . $length . '}$/', Model::generateCode($length));
        }
    }

    public function testShortCodeIsNotAcceptedAsToken(): void
    {
        // Rejected by format before any query, so no server needed.
        $this->assertNull(Model::consumeToken(self::PURPOSE, '123456'));
        $this->assertNull(Model::consumeToken(self::PURPOSE, str_repeat('Z', 32)));
    }

    public function testLiveCodeVerifiesOnceAndIsStoredHashed(): void
    {
        $this->needServer();

        [$pin, $code] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET, [
            'user_id' => 7,
            'payload' => ['next' => '/lk'],
        ]);

        $this->assertSame(Model::hashValue($code), Model::createById($pin->getId())->getCodeHash());
        $this->assertNull(Model::verifyCode(self::OTHER_PURPOSE, self::TARGET, $code));
        $this->assertNull(Model::verifyCode(self::PURPOSE, 'other@example.com', $code));

        $verified = Model::verifyCode(self::PURPOSE, self::TARGET, $code);
        $this->assertNotNull($verified);
        $this->assertEquals(Status::verified, $verified->getStatus()); // mysqli returns numbers as strings
        $this->assertEquals(7, $verified->getUserId());
        $this->assertSame('/lk', $verified->getJsonData('payload', 'next'));

        $this->assertNull(Model::verifyCode(self::PURPOSE, self::TARGET, $code), 'single use');
    }

    public function testLiveWrongGuessesBurnAttemptsOfEveryLiveCode(): void
    {
        $this->needServer();

        [, $first] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        [, $second] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);

        for ($i = 0; $i < Model::MAX_ATTEMPTS; $i++) {
            $this->assertNull(Model::verifyCode(self::PURPOSE, self::TARGET, 'x'));
        }

        $this->assertNull(Model::verifyCode(self::PURPOSE, self::TARGET, $first));
        $this->assertNull(Model::verifyCode(self::PURPOSE, self::TARGET, $second));
    }

    public function testLiveNewCodeDoesNotCancelEarlierOne(): void
    {
        $this->needServer();

        [, $first] = Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);
        Model::issueCode(self::PURPOSE, Channel::email, self::TARGET);

        $this->assertNotNull(Model::verifyCode(self::PURPOSE, self::TARGET, $first));
    }

    public function testLiveExpiredCodeFails(): void
    {
        $this->needServer();

        [, $code] = Model::issueCode(self::PURPOSE, Channel::sms, '+79991234567', ['ttl' => -1]);

        $this->assertNull(Model::verifyCode(self::PURPOSE, '+79991234567', $code));
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
        }

        // Another purpose has its own budget.
        Model::issueCode(self::OTHER_PURPOSE, Channel::email, self::TARGET);

        for ($i = 0; $i < Model::MAX_PER_IP; $i++) {
            Model::issueCode(self::OTHER_PURPOSE, Channel::email, "u$i@example.com", ['ip' => '10.0.0.1']);
        }

        $this->expectException(RateLimitedException::class);
        Model::issueCode(self::OTHER_PURPOSE, Channel::email, 'new@example.com', ['ip' => '10.0.0.1']);
    }

    public function testLiveTokenIsConsumedOnce(): void
    {
        $this->needServer();

        [, $token] = Model::issueToken(self::PURPOSE, Channel::telegram, self::TARGET, 86400);

        $this->assertNull(Model::consumeToken(self::OTHER_PURPOSE, $token));
        $this->assertNotNull(Model::consumeToken(self::PURPOSE, $token));
        $this->assertNull(Model::consumeToken(self::PURPOSE, $token), 'single use');
    }

    public function testLivePurgeKeepsRowsInsideRateWindow(): void
    {
        $this->needServer();

        [$old] = Model::issueCode(self::PURPOSE, Channel::email, 'old@example.com', ['ttl' => -1]);
        [$recent] = Model::issueCode(self::PURPOSE, Channel::email, 'recent@example.com', ['ttl' => -1]);
        [$live] = Model::issueToken(self::PURPOSE, Channel::email, 'live@example.com', 86400 * 30);

        $past = \diDateTime::sqlFormat(time() - Model::RATE_WINDOW - 60);
        Connection::get()->getDb()->q(
            "UPDATE authorization_pin SET created_at = '$past' WHERE id IN ({$old->getId()}, {$live->getId()})"
        );

        $this->assertSame(1, Collection::purge());
        $this->assertFalse(Model::createById($old->getId())->exists(), 'expired past the window');
        $this->assertTrue(Model::createById($recent->getId())->exists(), 'still counted by the limit');
        $this->assertTrue(Model::createById($live->getId())->exists(), 'pending token');
    }

    private function needServer(): void
    {
        if (!self::$live) {
            $this->markTestSkipped('Set DI_CORE_TEST_DB to a postgresql:// or mysql:// DSN');
        }
    }
}
